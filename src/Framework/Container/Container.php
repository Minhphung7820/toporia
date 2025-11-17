<?php

declare(strict_types=1);

namespace Toporia\Framework\Container;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Container\Exception\{ContainerException, NotFoundException};
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Dependency Injection Container
 *
 * Professional DI container with advanced features:
 * - Auto-wiring with reflection
 * - Singleton pattern support
 * - Contextual bindings
 * - Tagged bindings
 * - Extending bindings
 * - Resolving callbacks
 * - Method injection
 * - Circular dependency detection
 *
 * Performance:
 * - O(1) singleton lookup (cached)
 * - O(N) dependency resolution where N = depth
 * - Reflection caching for better performance
 *
 * Clean Architecture:
 * - Single Responsibility: Dependency injection only
 * - Dependency Inversion: Depends on ContainerInterface
 * - Open/Closed: Extensible via bindings
 */
final class Container implements ContainerInterface
{
    /**
     * @var array<string, array{concrete: callable|string|null, shared: bool}> Service bindings
     */
    private array $bindings = [];

    /**
     * @var array<string, mixed> Resolved singleton instances
     */
    private array $instances = [];

    /**
     * @var array<string, array<string, callable|string>> Contextual bindings
     * Format: ['Abstract' => ['Concrete' => 'Implementation']]
     */
    private array $contextual = [];

    /**
     * @var array<string, array<string>> Tagged bindings
     * Format: ['tag' => ['Service1', 'Service2']]
     */
    private array $tags = [];

    /**
     * @var array<string, array<callable>> Extending bindings
     * Format: ['Service' => [callback1, callback2]]
     */
    private array $extenders = [];

    /**
     * @var array<string, array<callable>> Resolving callbacks
     * Format: ['Service' => [callback1, callback2]]
     */
    private array $resolvingCallbacks = [];

    /**
     * @var array<string, callable> Global resolving callbacks
     */
    private array $globalResolvingCallbacks = [];

    /**
     * @var array<string, bool> Resolution stack for circular dependency detection
     */
    private array $resolving = [];

    /**
     * @var array<string, ReflectionClass> Reflection class cache for performance
     */
    private array $reflectionClassCache = [];

    /**
     * @var array<string, ReflectionMethod> Reflection method cache for performance
     */
    private array $reflectionMethodCache = [];

    /**
     * {@inheritdoc}
     */
    public function get(string $id): mixed
    {
        // Check if already resolved as singleton
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Detect circular dependencies
        if (isset($this->resolving[$id])) {
            throw new ContainerException("Circular dependency detected while resolving '{$id}'");
        }

        $this->resolving[$id] = true;

        try {
            $instance = $this->resolve($id);

            // Fire resolving callbacks
            $this->fireResolvingCallbacks($id, $instance);

            // Cache if singleton
            if (isset($this->bindings[$id]['shared']) && $this->bindings[$id]['shared']) {
                $this->instances[$id] = $instance;
            }

            return $instance;
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id])
            || isset($this->instances[$id])
            || class_exists($id);
    }

    /**
     * {@inheritdoc}
     */
    public function bind(string $id, callable|string|null $concrete = null, bool $shared = false): void
    {
        // If concrete is null, use id as concrete (auto-bind to itself)
        $concrete = $concrete ?? $id;

        $this->bindings[$id] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];

        // Remove from instances if re-binding
        unset($this->instances[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $id, callable|string|null $concrete = null): void
    {
        $this->bind($id, $concrete, shared: true);
    }

    /**
     * {@inheritdoc}
     */
    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
        unset($this->bindings[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function call(callable|array|string $callable, array $parameters = []): mixed
    {
        if (is_string($callable) && str_contains($callable, '::')) {
            $callable = explode('::', $callable, 2);
        }

        if (is_array($callable)) {
            [$class, $method] = $callable;

            // Resolve class if it's a string
            if (is_string($class)) {
                $class = $this->get($class);
            }

            $reflection = $this->getReflectionMethod($class, $method);
            $dependencies = $this->resolveMethodDependencies($reflection, $parameters);

            return $reflection->invokeArgs($class, $dependencies);
        }

        $reflection = new ReflectionFunction($callable);
        $dependencies = $this->resolveMethodDependencies($reflection, $parameters);

        return $reflection->invokeArgs($dependencies);
    }

    /**
     * Alias for get() method.
     *
     * @param string $id Service identifier
     * @return mixed
     */
    public function make(string $id): mixed
    {
        return $this->get($id);
    }

    /**
     * Register a contextual binding.
     *
     * When resolving $abstract in the context of $concrete, use $implementation.
     *
     * @param string $abstract Abstract class/interface
     * @param string $concrete Concrete class that needs the abstract
     * @param callable|string $implementation Implementation to use
     * @return void
     */
    public function when(string $abstract): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $abstract);
    }

    /**
     * Tag a binding.
     *
     * @param array<string> $abstracts Service identifiers to tag
     * @param string $tag Tag name
     * @return void
     */
    public function tag(array $abstracts, string $tag): void
    {
        foreach ($abstracts as $abstract) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            $this->tags[$tag][] = $abstract;
        }
    }

    /**
     * Resolve all services with a given tag.
     *
     * @param string $tag Tag name
     * @return array<mixed> Resolved services
     */
    public function tagged(string $tag): array
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        $services = [];
        foreach ($this->tags[$tag] as $abstract) {
            $services[] = $this->get($abstract);
        }

        return $services;
    }

    /**
     * Extend a binding.
     *
     * Allows modifying a service after it's resolved.
     *
     * @param string $abstract Service identifier
     * @param callable $extender Callback to extend the service
     * @return void
     */
    public function extend(string $abstract, callable $extender): void
    {
        if (!isset($this->extenders[$abstract])) {
            $this->extenders[$abstract] = [];
        }
        $this->extenders[$abstract][] = $extender;
    }

    /**
     * Register a resolving callback.
     *
     * @param string|callable $abstract Service identifier or callback for all services
     * @param callable|null $callback Callback to fire when resolving
     * @return void
     */
    public function resolving(string|callable $abstract, ?callable $callback = null): void
    {
        if (is_callable($abstract)) {
            // Global resolving callback
            $this->globalResolvingCallbacks[] = $abstract;
        } else {
            // Service-specific callback
            if (!isset($this->resolvingCallbacks[$abstract])) {
                $this->resolvingCallbacks[$abstract] = [];
            }
            $this->resolvingCallbacks[$abstract][] = $callback;
        }
    }

    /**
     * Check if a binding is shared (singleton).
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function isShared(string $id): bool
    {
        return isset($this->bindings[$id]['shared']) && $this->bindings[$id]['shared'];
    }

    /**
     * Check if a binding exists.
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function bound(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Forget a binding.
     *
     * @param string $id Service identifier
     * @return void
     */
    public function forget(string $id): void
    {
        unset($this->bindings[$id], $this->instances[$id], $this->resolvingCallbacks[$id], $this->extenders[$id]);
    }

    /**
     * Flush all bindings and instances.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->contextual = [];
        $this->tags = [];
        $this->extenders = [];
        $this->resolvingCallbacks = [];
        $this->globalResolvingCallbacks = [];
        $this->reflectionClassCache = [];
        $this->reflectionMethodCache = [];
    }

    /**
     * Resolve a service from the container.
     *
     * @param string $id Service identifier
     * @param array<string> $parameters Additional parameters
     * @return mixed Resolved service
     * @throws NotFoundException
     * @throws ContainerException
     */
    private function resolve(string $id, array $parameters = []): mixed
    {
        // Check for contextual binding
        $concrete = $this->getContextualConcrete($id);

        // Use binding if exists
        if ($concrete === null && isset($this->bindings[$id])) {
            $concrete = $this->bindings[$id]['concrete'];
        }

        // Resolve concrete
        if ($concrete !== null) {
            $instance = $this->build($concrete, $parameters);
        } elseif (class_exists($id)) {
            // Auto-wire class
            $instance = $this->build($id, $parameters);
        } else {
            throw new NotFoundException("Service '{$id}' not found in container");
        }

        // Apply extenders
        if (isset($this->extenders[$id])) {
            foreach ($this->extenders[$id] as $extender) {
                $instance = $extender($instance, $this);
            }
        }

        return $instance;
    }

    /**
     * Get contextual concrete for a service.
     *
     * @param string $abstract Abstract service identifier
     * @return callable|string|null
     */
    private function getContextualConcrete(string $abstract): callable|string|null
    {
        // Check if we're resolving in a context
        if (empty($this->resolving)) {
            return null;
        }

        // Get the class we're currently resolving (last in stack)
        $concrete = array_key_last($this->resolving);
        if ($concrete === null) {
            return null;
        }

        return $this->contextual[$concrete][$abstract] ?? null;
    }

    /**
     * Build a concrete instance.
     *
     * @param callable|string $concrete Concrete implementation
     * @param array<string> $parameters Additional parameters
     * @return mixed Built instance
     */
    private function build(callable|string $concrete, array $parameters = []): mixed
    {
        // If it's a callable, invoke it
        if (is_callable($concrete)) {
            return $concrete($this, ...$parameters);
        }

        // If it's a class name, auto-wire it
        if (is_string($concrete) && class_exists($concrete)) {
            return $this->autowire($concrete, $parameters);
        }

        throw new ContainerException("Cannot build concrete: " . (is_string($concrete) ? $concrete : gettype($concrete)));
    }

    /**
     * Automatically resolve and instantiate a class with its dependencies.
     *
     * @param string $className Full class name
     * @param array<string> $parameters Additional parameters
     * @return object Instantiated object
     * @throws ContainerException
     */
    private function autowire(string $className, array $parameters = []): object
    {
        $reflection = $this->getReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class '{$className}' is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        // No constructor, just instantiate
        if ($constructor === null) {
            return $reflection->newInstance();
        }

        // Resolve constructor dependencies
        $dependencies = $this->resolveMethodDependencies($constructor, $parameters);

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve method/constructor dependencies.
     *
     * @param ReflectionMethod|ReflectionFunction $reflection
     * @param array<string> $parameters Additional parameters
     * @return array Resolved dependencies
     * @throws ContainerException
     */
    private function resolveMethodDependencies(
        ReflectionMethod|ReflectionFunction $reflection,
        array $parameters
    ): array {
        $dependencies = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            // Use provided parameter if available
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            // Try to resolve by type
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                $instance = $this->get($className);

                // Auto-validate FormRequest instances
                if ($instance instanceof \Toporia\Framework\Http\FormRequest) {
                    $instance->validate();
                }

                $dependencies[] = $instance;
                continue;
            }

            // Use default value if available
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // Cannot resolve
            $context = $reflection instanceof ReflectionMethod
                ? $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName()
                : $reflection->getName();

            throw new ContainerException(
                "Cannot resolve parameter '\${$name}' for {$context}"
            );
        }

        return $dependencies;
    }

    /**
     * Fire resolving callbacks.
     *
     * @param string $id Service identifier
     * @param mixed $instance Resolved instance
     * @return void
     */
    private function fireResolvingCallbacks(string $id, mixed $instance): void
    {
        // Fire service-specific callbacks
        if (isset($this->resolvingCallbacks[$id])) {
            foreach ($this->resolvingCallbacks[$id] as $callback) {
                $callback($instance, $this);
            }
        }

        // Fire global callbacks
        foreach ($this->globalResolvingCallbacks as $callback) {
            $callback($instance, $this);
        }
    }

    /**
     * Get reflection class with caching.
     *
     * @param string $className
     * @return ReflectionClass
     * @throws ContainerException
     */
    private function getReflectionClass(string $className): ReflectionClass
    {
        if (!isset($this->reflectionClassCache[$className])) {
            try {
                $this->reflectionClassCache[$className] = new ReflectionClass($className);
            } catch (ReflectionException $e) {
                throw new ContainerException("Cannot reflect class '{$className}': {$e->getMessage()}", 0, $e);
            }
        }

        return $this->reflectionClassCache[$className];
    }

    /**
     * Get reflection method with caching.
     *
     * @param object|string $class
     * @param string $method
     * @return ReflectionMethod
     */
    private function getReflectionMethod(object|string $class, string $method): ReflectionMethod
    {
        $key = (is_string($class) ? $class : $class::class) . '::' . $method;

        if (!isset($this->reflectionMethodCache[$key])) {
            $this->reflectionMethodCache[$key] = new ReflectionMethod($class, $method);
        }

        return $this->reflectionMethodCache[$key];
    }

    /**
     * Add contextual binding (internal use by ContextualBindingBuilder).
     *
     * @param string $concrete Concrete class
     * @param string $abstract Abstract class/interface
     * @param callable|string $implementation Implementation
     * @return void
     */
    public function addContextualBinding(string $concrete, string $abstract, callable|string $implementation): void
    {
        if (!isset($this->contextual[$concrete])) {
            $this->contextual[$concrete] = [];
        }
        $this->contextual[$concrete][$abstract] = $implementation;
    }
}
