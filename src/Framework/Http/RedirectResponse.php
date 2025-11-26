<?php

declare(strict_types=1);

namespace Toporia\Framework\Http;

use Toporia\Framework\Http\Contracts\RedirectResponseInterface;
use Toporia\Framework\Support\Macroable;

/**
 * Redirect Response
 *
 * Laravel-compatible redirect response with session flash data support.
 *
 * @author      Toporia Framework Team
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Http
 */
final class RedirectResponse extends Response implements RedirectResponseInterface
{
    use Macroable;

    /**
     * @var string Target URL for redirection
     */
    private string $targetUrl;

    /**
     * @var array<string, mixed> Flash data for session
     */
    private array $flashData = [];

    /**
     * Create a new redirect response.
     *
     * @param string $url Target URL
     * @param int $status HTTP status code
     * @param array<string, string> $headers Response headers
     */
    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        parent::__construct('', $status, $headers);

        $this->setTargetUrl($url);
    }

    /**
     * {@inheritdoc}
     */
    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function setTargetUrl(string $url): static
    {
        $this->targetUrl = $url;
        $this->header('Location', $url);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function with(string $key, mixed $value): static
    {
        $this->flashData[$key] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withInput(?array $input = null): static
    {
        $input = $input ?? $_POST ?? [];

        return $this->with('_old_input', $input);
    }

    /**
     * {@inheritdoc}
     */
    public function withErrors(array|string $errors): static
    {
        if (is_string($errors)) {
            $errors = ['error' => $errors];
        }

        return $this->with('errors', $errors);
    }

    /**
     * Send the response.
     *
     * @param string $content Content to send (required by parent)
     * @return void
     */
    public function send(string $content = ''): void
    {
        // Flash data to session before redirect
        $this->flashToSession();

        // For redirects, we don't need content, just headers
        parent::send($content);
    }

    /**
     * Send the redirect response (convenience method).
     *
     * @return void
     */
    public function sendResponse(): void
    {
        $this->send('');
    }

    /**
     * Flash data to session.
     *
     * @return void
     */
    private function flashToSession(): void
    {
        if (empty($this->flashData)) {
            return;
        }

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Flash each piece of data
        foreach ($this->flashData as $key => $value) {
            $_SESSION["_flash_{$key}"] = $value;
        }
    }
}
