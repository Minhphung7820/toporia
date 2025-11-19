<?php

declare(strict_types=1);

namespace Toporia\Framework\Translation\Contracts;

/**
 * Translation Loader Interface
 *
 * Defines the contract for loading translation files.
 * Supports multiple file formats (JSON, PHP arrays).
 *
 * Clean Architecture:
 * - Interface in Framework layer
 * - Implementation in Infrastructure (if needed) or Framework
 *
 * SOLID Principles:
 * - Single Responsibility: Only loads translation data
 * - Open/Closed: Extensible via new loader implementations
 * - Dependency Inversion: Translator depends on this abstraction
 */
interface LoaderInterface
{
    /**
     * Load translations for a given locale and namespace.
     *
     * @param string $locale Locale code (e.g., 'en', 'vi')
     * @param string $namespace Namespace/group (e.g., 'messages', 'validation')
     * @return array<string, mixed> Translation array (nested arrays supported)
     */
    public function load(string $locale, string $namespace): array;

    /**
     * Add a namespace path for translations.
     *
     * @param string $namespace Namespace name
     * @param string $path Path to translation files
     * @return void
     */
    public function addNamespace(string $namespace, string $path): void;

    /**
     * Get all namespaces.
     *
     * @return array<string, string> Namespace => path mapping
     */
    public function getNamespaces(): array;
}
