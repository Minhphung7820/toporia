<?php

declare(strict_types=1);

namespace Toporia\Framework\Http;

use Toporia\Framework\Http\Contracts\ResponseInterface;

/**
 * Class Response
 *
 * HTTP Response implementation handling response generation including
 * status codes, headers, and content output (HTML, JSON, redirects).
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Http
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
class Response implements ResponseInterface
{
    use \Toporia\Framework\Support\Macroable;
    /**
     * @var int HTTP status code.
     */
    private int $status = 200;

    /**
     * @var array<string, string> Response headers.
     */
    private array $headers = [];

    /**
     * @var bool Whether headers have been sent.
     */
    private bool $headersSent = false;

    /**
     * @var string Response content.
     */
    private string $content = '';

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = array_merge([
            'Content-Type' => 'text/html; charset=UTF-8'
        ], $headers);
    }

    /**
     * Get response content.
     *
     * @return string Response content
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set response content.
     *
     * @param string $content Response content
     * @return $this
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get response status code.
     *
     * @return int Status code
     */
    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * Get response headers.
     *
     * @return array<string, string> Headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus(int $code): self
    {
        $this->status = $code;
        http_response_code($code);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        if (!$this->headersSent) {
            header($name . ': ' . $value, replace: true);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function html(string $content, int $status = 200): void
    {
        $this->setStatus($status);
        $this->header('Content-Type', 'text/html; charset=UTF-8');
        $this->setContent($content);
        $this->send($content);
    }

    /**
     * {@inheritdoc}
     */
    public function json(mixed $data, int $status = 200): void
    {
        $this->setStatus($status);
        $this->header('Content-Type', 'application/json');

        // Laravel-style JSON encoding with proper object handling
        $processedData = $this->prepareDataForJson($data);

        $json = json_encode(
            $processedData,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );

        if ($json === false) {
            $this->setStatus(500);
            $json = json_encode([
                'error' => 'Failed to encode JSON',
                'message' => json_last_error_msg()
            ]);
        }

        $this->send($json);
    }

    /**
     * Prepare data for JSON encoding (Laravel-style).
     *
     * Handles JsonSerializable, Arrayable objects, and collections properly.
     *
     * @param mixed $data
     * @return mixed
     */
    private function prepareDataForJson(mixed $data): mixed
    {
        if ($data instanceof \JsonSerializable) {
            return $data->jsonSerialize();
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        if (is_array($data)) {
            return array_map([$this, 'prepareDataForJson'], $data);
        }

        if (is_object($data)) {
            // Convert objects to arrays, but preserve stdClass as objects
            if ($data instanceof \stdClass) {
                return $data;
            }

            // For other objects, try to convert to array
            if (method_exists($data, '__toArray')) {
                return $data->__toArray();
            }

            // Fallback: convert public properties to array
            return get_object_vars($data);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function redirect(string $url, int $status = 302): void
    {
        $this->setStatus($status);
        $this->header('Location', $url);
        $this->send('');
    }

    /**
     * {@inheritdoc}
     */
    public function send(string $content): void
    {
        $this->headersSent = true;
        echo $content;
    }

    /**
     * Send a file download response.
     *
     * @param string $path File path.
     * @param string|null $name Download filename (optional).
     * @return void
     */
    public function download(string $path, ?string $name = null): void
    {
        if (!file_exists($path)) {
            $this->html('<h1>404 File Not Found</h1>', 404);
            return;
        }

        $name = $name ?? basename($path);
        $this->header('Content-Type', 'application/octet-stream');
        $this->header('Content-Disposition', 'attachment; filename="' . $name . '"');
        $this->header('Content-Length', (string) filesize($path));

        $this->send(file_get_contents($path));
    }

    /**
     * Send a no-content response.
     *
     * @return void
     */
    public function noContent(): void
    {
        $this->setStatus(204);
        $this->send('');
    }


    /**
     * Get the current status code.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }
}
