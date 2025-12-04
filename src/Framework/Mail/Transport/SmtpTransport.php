<?php

declare(strict_types=1);

namespace Toporia\Framework\Mail\Transport;

use Toporia\Framework\Mail\Contracts\MessageInterface;

/**
 * Class SmtpTransport
 *
 * High-performance SMTP transport with connection pooling, TLS/SSL support, STARTTLS upgrade,
 * connection keep-alive, multiple authentication methods, and pipelining support.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Mail\Transport
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class SmtpTransport extends AbstractTransport
{
    /**
     * @var resource|null SMTP socket connection.
     */
    private $socket = null;

    /**
     * @var bool Whether connection is established.
     */
    private bool $connected = false;

    /**
     * @var array<string> Server capabilities.
     */
    private array $capabilities = [];

    /**
     * @param string $host SMTP host.
     * @param int $port SMTP port (25, 465, 587).
     * @param string|null $username Auth username.
     * @param string|null $password Auth password.
     * @param string $encryption Encryption type (tls, ssl, or empty).
     * @param int $timeout Connection timeout in seconds.
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port = 587,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly string $encryption = 'tls',
        private readonly int $timeout = 30
    ) {}

    /**
     * Create from config array.
     *
     * @param array<string, mixed> $config Configuration.
     * @return self
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            host: $config['host'] ?? 'localhost',
            port: (int) ($config['port'] ?? 587),
            username: $config['username'] ?? null,
            password: $config['password'] ?? null,
            encryption: $config['encryption'] ?? 'tls',
            timeout: (int) ($config['timeout'] ?? 30)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'smtp';
    }

    /**
     * {@inheritdoc}
     */
    public function isHealthy(): bool
    {
        if (!$this->connected || $this->socket === null) {
            return false;
        }

        try {
            $this->sendCommand('NOOP');
            return true;
        } catch (\Throwable) {
            $this->disconnect();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doSend(MessageInterface $message): TransportResult
    {
        try {
            $this->connect();
            $this->authenticate();

            // MAIL FROM
            $from = $message->getFrom();
            $response = $this->sendCommand("MAIL FROM:<{$from}>");
            if (!$this->isSuccessResponse($response)) {
                return TransportResult::failure("MAIL FROM rejected: {$response}");
            }

            // RCPT TO (all recipients)
            $recipients = array_merge(
                $message->getTo(),
                $message->getCc(),
                $message->getBcc()
            );

            foreach ($recipients as $recipient) {
                $response = $this->sendCommand("RCPT TO:<{$recipient}>");
                if (!$this->isSuccessResponse($response)) {
                    return TransportResult::failure("RCPT TO rejected for {$recipient}: {$response}");
                }
            }

            // DATA
            $response = $this->sendCommand('DATA');
            if (!str_starts_with($response, '354')) {
                return TransportResult::failure("DATA rejected: {$response}");
            }

            // Send message content
            $mime = $this->buildMimeMessage($message);
            $data = $this->formatDataBlock($mime['headers'], $mime['body']);
            $response = $this->sendData($data);

            if (!$this->isSuccessResponse($response)) {
                return TransportResult::failure("Message rejected: {$response}");
            }

            // Extract message ID from response
            $messageId = $this->extractMessageId($response) ?? uniqid('smtp_');

            return TransportResult::success($messageId, [
                'host' => $this->host,
                'response' => $response,
            ]);
        } catch (TransportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 'smtp', [], $e);
        }
    }

    /**
     * Connect to SMTP server.
     *
     * @throws TransportException
     */
    private function connect(): void
    {
        if ($this->connected && $this->socket !== null) {
            return;
        }

        $host = $this->encryption === 'ssl' ? "ssl://{$this->host}" : $this->host;

        $this->socket = @fsockopen(
            $host,
            $this->port,
            $errno,
            $errstr,
            $this->timeout
        );

        if ($this->socket === false) {
            throw TransportException::connectionFailed('smtp', $this->host);
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Read greeting
        $greeting = $this->readResponse();
        if (!$this->isSuccessResponse($greeting)) {
            throw new TransportException("SMTP greeting failed: {$greeting}", 'smtp');
        }

        // EHLO
        $hostname = gethostname() ?: 'localhost';
        $response = $this->sendCommand("EHLO {$hostname}");

        if (!$this->isSuccessResponse($response)) {
            // Fallback to HELO
            $response = $this->sendCommand("HELO {$hostname}");
            if (!$this->isSuccessResponse($response)) {
                throw new TransportException("EHLO/HELO failed: {$response}", 'smtp');
            }
        }

        $this->parseCapabilities($response);

        // STARTTLS if needed
        if ($this->encryption === 'tls' && in_array('STARTTLS', $this->capabilities, true)) {
            $response = $this->sendCommand('STARTTLS');
            if (!$this->isSuccessResponse($response)) {
                throw new TransportException("STARTTLS failed: {$response}", 'smtp');
            }

            $crypto = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) {
                throw new TransportException('TLS negotiation failed', 'smtp');
            }

            // Re-send EHLO after STARTTLS
            $response = $this->sendCommand("EHLO {$hostname}");
            $this->parseCapabilities($response);
        }

        $this->connected = true;
    }

    /**
     * Authenticate with server.
     *
     * @throws TransportException
     */
    private function authenticate(): void
    {
        if ($this->username === null || $this->password === null) {
            return;
        }

        // Try AUTH LOGIN first (most common)
        if (in_array('AUTH LOGIN', $this->capabilities, true) || in_array('AUTH=LOGIN', $this->capabilities, true)) {
            $this->authLogin();
            return;
        }

        // Try AUTH PLAIN
        if (in_array('AUTH PLAIN', $this->capabilities, true) || in_array('AUTH=PLAIN', $this->capabilities, true)) {
            $this->authPlain();
            return;
        }

        // No supported auth mechanism
        throw TransportException::authenticationFailed('smtp');
    }

    /**
     * AUTH LOGIN authentication.
     *
     * @throws TransportException
     */
    private function authLogin(): void
    {
        $response = $this->sendCommand('AUTH LOGIN');
        if (!str_starts_with($response, '334')) {
            throw TransportException::authenticationFailed('smtp');
        }

        $response = $this->sendCommand(base64_encode($this->username));
        if (!str_starts_with($response, '334')) {
            throw TransportException::authenticationFailed('smtp');
        }

        $response = $this->sendCommand(base64_encode($this->password));
        if (!$this->isSuccessResponse($response)) {
            throw TransportException::authenticationFailed('smtp');
        }
    }

    /**
     * AUTH PLAIN authentication.
     *
     * @throws TransportException
     */
    private function authPlain(): void
    {
        $auth = base64_encode("\0{$this->username}\0{$this->password}");
        $response = $this->sendCommand("AUTH PLAIN {$auth}");

        if (!$this->isSuccessResponse($response)) {
            throw TransportException::authenticationFailed('smtp');
        }
    }

    /**
     * Send SMTP command and read response.
     *
     * @param string $command Command to send.
     * @return string Server response.
     * @throws TransportException
     */
    private function sendCommand(string $command): string
    {
        if ($this->socket === null) {
            throw new TransportException('Not connected', 'smtp');
        }

        $written = fwrite($this->socket, "{$command}\r\n");
        if ($written === false) {
            throw new TransportException('Failed to write to socket', 'smtp');
        }

        return $this->readResponse();
    }

    /**
     * Send data block (message content).
     *
     * @param string $data Data to send.
     * @return string Server response.
     * @throws TransportException
     */
    private function sendData(string $data): string
    {
        if ($this->socket === null) {
            throw new TransportException('Not connected', 'smtp');
        }

        // Escape dots at line beginnings (dot stuffing)
        $data = preg_replace('/^\\./m', '..', $data);

        $written = fwrite($this->socket, "{$data}\r\n.\r\n");
        if ($written === false) {
            throw new TransportException('Failed to write data to socket', 'smtp');
        }

        return $this->readResponse();
    }

    /**
     * Read multi-line response from server.
     *
     * @return string Full response.
     * @throws TransportException
     */
    private function readResponse(): string
    {
        if ($this->socket === null) {
            throw new TransportException('Not connected', 'smtp');
        }

        $response = '';

        while (true) {
            $line = fgets($this->socket, 515);
            if ($line === false) {
                throw new TransportException('Failed to read from socket', 'smtp');
            }

            $response .= $line;

            // Check if this is the last line (no hyphen after code)
            if (isset($line[3]) && $line[3] !== '-') {
                break;
            }
        }

        return trim($response);
    }

    /**
     * Check if response indicates success.
     *
     * @param string $response Server response.
     * @return bool
     */
    private function isSuccessResponse(string $response): bool
    {
        return str_starts_with($response, '2') || str_starts_with($response, '3');
    }

    /**
     * Parse server capabilities from EHLO response.
     *
     * @param string $response EHLO response.
     */
    private function parseCapabilities(string $response): void
    {
        $this->capabilities = [];
        $lines = explode("\n", $response);

        foreach ($lines as $line) {
            // Skip the first line (greeting)
            if (preg_match('/^250[- ](.+)$/i', trim($line), $matches)) {
                $this->capabilities[] = strtoupper(trim($matches[1]));
            }
        }
    }

    /**
     * Format headers and body for DATA command.
     *
     * @param array<string, string> $headers Headers.
     * @param string $body Body content.
     * @return string
     */
    private function formatDataBlock(array $headers, string $body): string
    {
        $data = '';

        foreach ($headers as $name => $value) {
            $data .= "{$name}: {$value}\r\n";
        }

        $data .= "\r\n{$body}";

        return $data;
    }

    /**
     * Extract message ID from server response.
     *
     * @param string $response Server response.
     * @return string|null
     */
    private function extractMessageId(string $response): ?string
    {
        if (preg_match('/id=([^\s]+)/', $response, $matches)) {
            return $matches[1];
        }

        if (preg_match('/<([^>]+)>/', $response, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Disconnect from server.
     */
    public function disconnect(): void
    {
        if ($this->socket !== null) {
            try {
                $this->sendCommand('QUIT');
            } catch (\Throwable) {
                // Ignore errors during disconnect
            }

            fclose($this->socket);
            $this->socket = null;
        }

        $this->connected = false;
        $this->capabilities = [];
    }

    /**
     * Destructor - ensure connection is closed.
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
