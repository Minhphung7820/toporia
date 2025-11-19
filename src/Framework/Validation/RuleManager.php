<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation;

use Toporia\Framework\Validation\Contracts\RuleInterface;

/**
 * Rule Manager
 *
 * Manages rule registration, resolution, and caching for optimal performance.
 *
 * Clean Architecture:
 * - Service Layer: Manages rule lifecycle
 * - Dependency Inversion: Depends on RuleInterface abstraction
 *
 * SOLID Principles:
 * - Single Responsibility: Only manages rules
 * - Open/Closed: Extensible via rule registration
 * - Dependency Inversion: Works with RuleInterface, not concrete rules
 *
 * Performance Optimizations:
 * - Rule instance caching (reuse stateless rules)
 * - Lazy rule resolution (only resolve when needed)
 * - String rule caching (avoid repeated parsing)
 * - Singleton pattern for shared rules
 *
 * @package Toporia\Framework\Validation
 */
final class RuleManager
{
    /**
     * @var array<string, RuleInterface> Cached rule instances (singleton pattern)
     */
    private static array $ruleCache = [];

    /**
     * @var array<string, callable> Custom rule callables
     */
    private static array $customRules = [];

    /**
     * @var array<string, string> Custom rule messages
     */
    private static array $customMessages = [];

    /**
     * Resolve rule from string or object.
     *
     * Performance: O(1) for cached rules, O(n) for string parsing
     *
     * @param string|RuleInterface $rule Rule string (e.g., "max:255") or Rule object
     * @return RuleInterface
     */
    public static function resolve(string|RuleInterface $rule): RuleInterface
    {
        // Already a Rule object - return directly (O(1))
        if ($rule instanceof RuleInterface) {
            return $rule;
        }

        // Check cache first (O(1))
        $cacheKey = self::getCacheKey($rule);
        if (isset(self::$ruleCache[$cacheKey])) {
            return self::$ruleCache[$cacheKey];
        }

        // Parse and resolve rule
        [$ruleName, $parameters] = self::parseRule($rule);

        // Check custom rules first
        if (isset(self::$customRules[$ruleName])) {
            $resolved = self::resolveCustomRule($ruleName, $parameters);
            self::$ruleCache[$cacheKey] = $resolved;
            return $resolved;
        }

        // Resolve built-in rule
        $resolved = self::resolveBuiltInRule($ruleName, $parameters);
        self::$ruleCache[$cacheKey] = $resolved;
        return $resolved;
    }

    /**
     * Register custom rule.
     *
     * @param string $name Rule name
     * @param callable|RuleInterface $rule Rule callback or Rule object
     * @param string|null $message Custom error message
     * @return void
     */
    public static function register(string $name, callable|RuleInterface $rule, ?string $message = null): void
    {
        self::$customRules[$name] = $rule;

        if ($message !== null) {
            self::$customMessages[$name] = $message;
        }

        // Clear cache for this rule name
        self::clearCache($name);
    }

    /**
     * Get custom message for rule.
     *
     * @param string $ruleName Rule name
     * @return string|null
     */
    public static function getCustomMessage(string $ruleName): ?string
    {
        return self::$customMessages[$ruleName] ?? null;
    }

    /**
     * Clear rule cache.
     *
     * @param string|null $ruleName Specific rule name, or null to clear all
     * @return void
     */
    public static function clearCache(?string $ruleName = null): void
    {
        if ($ruleName === null) {
            self::$ruleCache = [];
            return;
        }

        // Remove all cached rules matching this name
        foreach (array_keys(self::$ruleCache) as $key) {
            if (str_starts_with($key, $ruleName . ':')) {
                unset(self::$ruleCache[$key]);
            }
        }
    }

    /**
     * Parse rule string into name and parameters.
     *
     * @param string $rule Rule string (e.g., "max:255,min:10")
     * @return array{string, array<string>}
     */
    private static function parseRule(string $rule): array
    {
        if (!str_contains($rule, ':')) {
            return [$rule, []];
        }

        [$ruleName, $params] = explode(':', $rule, 2);
        return [$ruleName, explode(',', $params)];
    }

    /**
     * Get cache key for rule.
     *
     * @param string $rule Rule string
     * @return string
     */
    private static function getCacheKey(string $rule): string
    {
        return $rule;
    }

    /**
     * Resolve custom rule.
     *
     * @param string $ruleName Rule name
     * @param array<string> $parameters Rule parameters
     * @return RuleInterface
     */
    private static function resolveCustomRule(string $ruleName, array $parameters): RuleInterface
    {
        $rule = self::$customRules[$ruleName];

        // Already a Rule object
        if ($rule instanceof RuleInterface) {
            return $rule;
        }

        // Wrap callable in Rule object
        return new class($rule, $parameters, $ruleName) implements \Toporia\Framework\Validation\Contracts\RuleInterface {
            /**
             * @var callable Validation callback
             */
            private readonly mixed $callback;

            /**
             * @param callable $callback Validation callback
             * @param array<string> $parameters Rule parameters
             * @param string $ruleName Rule name
             */
            public function __construct(
                mixed $callback,
                private readonly array $parameters,
                private readonly string $ruleName
            ) {
                $this->callback = $callback;
            }

            public function passes(string $attribute, mixed $value): bool
            {
                return (bool) ($this->callback)($value, $this->parameters, []);
            }

            public function message(): string
            {
                $customMessage = RuleManager::getCustomMessage($this->ruleName);
                return $customMessage ?? "The :attribute is invalid.";
            }
        };
    }

    /**
     * Resolve built-in rule.
     *
     * Built-in rules are handled by Validator methods, not Rule objects.
     * This returns a wrapper that delegates to Validator.
     *
     * @param string $ruleName Rule name
     * @param array<string> $parameters Rule parameters
     * @return RuleInterface
     */
    private static function resolveBuiltInRule(string $ruleName, array $parameters): RuleInterface
    {
        // Built-in rules are handled by Validator directly
        // Return a marker rule that signals Validator to use built-in method
        return new class($ruleName, $parameters) implements \Toporia\Framework\Validation\Contracts\RuleInterface {
            public function __construct(
                public readonly string $ruleName,
                public readonly array $parameters
            ) {}

            public function passes(string $attribute, mixed $value): bool
            {
                // This should never be called - Validator handles built-in rules directly
                return true;
            }

            public function message(): string
            {
                return "The :attribute is invalid.";
            }
        };
    }
}
