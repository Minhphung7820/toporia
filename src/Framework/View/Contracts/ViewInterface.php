<?php

declare(strict_types=1);

namespace Toporia\Framework\View\Contracts;

/**
 * View Interface
 */
interface ViewInterface
{
    /**
     * Get the view path.
     *
     * @return string
     */
    public function path(): string;

    /**
     * Get the view name.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Add data to the view.
     *
     * @param string|array<string, mixed> $key
     * @param mixed $value
     * @return static
     */
    public function with(string|array $key, mixed $value = null): static;

    /**
     * Get all data.
     *
     * @return array<string, mixed>
     */
    public function data(): array;

    /**
     * Render the view.
     *
     * @return string
     */
    public function render(): string;
}
