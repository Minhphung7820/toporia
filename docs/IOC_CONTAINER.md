# IoC Container Documentation

## Tổng quan

IoC (Inversion of Control) Container là trái tim của Toporia Framework, cung cấp Dependency Injection (DI) và Service Location pattern. Container được thiết kế với các tính năng nâng cao và tối ưu hóa hiệu suất.

## Kiến trúc

### Clean Architecture Principles

Container tuân thủ các nguyên tắc Clean Architecture:

- **Single Responsibility**: Chỉ chịu trách nhiệm về dependency injection
- **Dependency Inversion**: Phụ thuộc vào `ContainerInterface` (abstraction)
- **Open/Closed**: Có thể mở rộng thông qua bindings mà không cần sửa code
- **Interface Segregation**: Cung cấp interface rõ ràng và tách biệt

### PSR-11 Compliance

Container implement `PSR-11 ContainerInterface`, đảm bảo tương thích với các thư viện PHP chuẩn.

## Các tính năng chính

### 1. Auto-wiring (Tự động nạp dependencies)

Container tự động phân tích constructor và method parameters để inject dependencies.

```php
class UserService
{
    public function __construct(
        private UserRepository $repository,
        private LoggerInterface $logger
    ) {}
}

// Container tự động resolve dependencies
$userService = $container->get(UserService::class);
```

**Cơ chế hoạt động:**
1. Sử dụng PHP Reflection để phân tích constructor
2. Kiểm tra type hints của từng parameter
3. Tự động resolve từ container hoặc tạo instance mới
4. Cache reflection để tối ưu hiệu suất

### 2. Singleton Pattern

Đảm bảo một service chỉ được tạo một lần và tái sử dụng.

```php
// Đăng ký singleton
$container->singleton(LogManager::class, function ($c) {
    return new LogManager($c->get('config'));
});

// Lần gọi đầu tiên: tạo instance
$logger1 = $container->get(LogManager::class);

// Lần gọi thứ hai: trả về cùng instance
$logger2 = $container->get(LogManager::class);
// $logger1 === $logger2 (true)
```

**Cơ chế:**
- Instance được cache trong `$instances` array
- O(1) lookup time
- Tự động clear khi rebind

### 3. Contextual Bindings (Ràng buộc theo ngữ cảnh)

Cho phép inject implementation khác nhau tùy vào class đang được resolve.

```php
// Khi OrderService cần PaymentProcessor, dùng StripePaymentProcessor
$container->when(OrderService::class)
    ->needs(PaymentProcessorInterface::class)
    ->give(StripePaymentProcessor::class);

// Khi SubscriptionService cần PaymentProcessor, dùng PayPalPaymentProcessor
$container->when(SubscriptionService::class)
    ->needs(PaymentProcessorInterface::class)
    ->give(PayPalPaymentProcessor::class);
```

**Cơ chế hoạt động:**
1. Khi resolve `OrderService`, container kiểm tra contextual bindings
2. Nếu `OrderService` cần `PaymentProcessorInterface`, dùng binding đã định nghĩa
3. Nếu không có contextual binding, dùng binding thông thường

**Use case:**
- Inject repository khác nhau cho từng service
- Inject strategy pattern implementations
- Testing: mock dependencies cho từng class

### 4. Tagged Bindings (Gắn thẻ services)

Nhóm nhiều services lại với nhau bằng tag để resolve cùng lúc.

```php
// Tag các notification channels
$container->tag([
    EmailChannel::class,
    SmsChannel::class,
    PushChannel::class,
], 'notification.channels');

// Resolve tất cả channels
$channels = $container->tagged('notification.channels');
// Returns: [EmailChannel instance, SmsChannel instance, PushChannel instance]
```

**Use case:**
- Event listeners
- Middleware groups
- Notification channels
- Queue workers

### 5. Extending Bindings (Mở rộng bindings)

Cho phép modify service sau khi được resolve.

```php
// Đăng ký service
$container->singleton(LoggerInterface::class, fn() => new Logger());

// Extend để thêm handler
$container->extend(LoggerInterface::class, function ($logger, $c) {
    $logger->pushHandler(new FileHandler('app.log'));
    return $logger;
});
```

**Cơ chế:**
- Extenders được gọi sau khi service được resolve
- Có thể chain nhiều extenders
- Extender nhận instance và container làm parameters

### 6. Resolving Callbacks (Callbacks khi resolve)

Đăng ký callbacks để thực thi khi service được resolve.

```php
// Callback cho service cụ thể
$container->resolving(UserService::class, function ($service, $c) {
    $service->initialize();
});

// Global callback cho tất cả services
$container->resolving(function ($service, $c) {
    if ($service instanceof Cacheable) {
        $service->enableCache();
    }
});
```

**Use case:**
- Initialize services sau khi resolve
- Apply decorators
- Logging service resolution

### 7. Method Injection (Inject vào method)

Container có thể inject dependencies vào method calls.

```php
class OrderController
{
    public function create(Request $request, OrderService $service)
    {
        // $request và $service được inject tự động
    }
}

// Gọi method với auto-injection
$container->call([OrderController::class, 'create'], [
    'request' => $httpRequest // Override parameter
]);
```

**Cơ chế:**
1. Phân tích method parameters bằng Reflection
2. Resolve từng parameter từ container
3. Cho phép override bằng `$parameters` array

### 8. Circular Dependency Detection (Phát hiện dependency vòng)

Container tự động phát hiện và báo lỗi khi có circular dependency.

```php
class ServiceA
{
    public function __construct(private ServiceB $b) {}
}

class ServiceB
{
    public function __construct(private ServiceA $a) {} // Circular!
}

// Throws: ContainerException "Circular dependency detected while resolving 'ServiceA'"
```

**Cơ chế:**
- Sử dụng resolution stack để track dependencies đang được resolve
- Nếu detect dependency đã có trong stack → circular dependency

## Cơ chế hoạt động

### Resolution Flow (Luồng resolve)

```
1. get($id) được gọi
   ↓
2. Kiểm tra singleton cache ($instances)
   ↓ (nếu không có)
3. Kiểm tra circular dependency
   ↓
4. resolve($id)
   ├─ Kiểm tra contextual binding
   ├─ Kiểm tra binding thông thường
   ├─ Kiểm tra class exists (auto-wiring)
   └─ Throw NotFoundException nếu không tìm thấy
   ↓
5. build($concrete)
   ├─ Nếu callable → invoke với container
   └─ Nếu class → autowire()
      ├─ Phân tích constructor bằng Reflection
      ├─ Resolve từng dependency (recursive)
      └─ newInstanceArgs()
   ↓
6. Apply extenders
   ↓
7. Fire resolving callbacks
   ↓
8. Cache nếu là singleton
   ↓
9. Return instance
```

### Auto-wiring Algorithm

```php
private function autowire(string $className, array $parameters = []): object
{
    // 1. Get reflection class (cached)
    $reflection = $this->getReflectionClass($className);

    // 2. Check if instantiable
    if (!$reflection->isInstantiable()) {
        throw new ContainerException("Class is not instantiable");
    }

    // 3. Get constructor
    $constructor = $reflection->getConstructor();

    // 4. No constructor → instantiate directly
    if ($constructor === null) {
        return $reflection->newInstance();
    }

    // 5. Resolve constructor dependencies
    $dependencies = $this->resolveMethodDependencies($constructor, $parameters);

    // 6. Instantiate with dependencies
    return $reflection->newInstanceArgs($dependencies);
}
```

### Dependency Resolution

```php
private function resolveMethodDependencies(
    ReflectionMethod|ReflectionFunction $reflection,
    array $parameters
): array {
    $dependencies = [];

    foreach ($reflection->getParameters() as $parameter) {
        $name = $parameter->getName();

        // 1. Check provided parameters (override)
        if (array_key_exists($name, $parameters)) {
            $dependencies[] = $parameters[$name];
            continue;
        }

        // 2. Try to resolve by type hint
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $className = $type->getName();
            $instance = $this->get($className); // Recursive
            $dependencies[] = $instance;
            continue;
        }

        // 3. Use default value if available
        if ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();
            continue;
        }

        // 4. Cannot resolve → throw exception
        throw new ContainerException("Cannot resolve parameter");
    }

    return $dependencies;
}
```

## Tối ưu hóa hiệu suất

### 1. Reflection Caching

Container cache reflection classes và methods để tránh parse lại.

```php
// Cache reflection classes
private array $reflectionClassCache = [];

// Cache reflection methods
private array $reflectionMethodCache = [];
```

**Lợi ích:**
- Giảm overhead của Reflection API
- Tăng tốc độ resolution lên 2-3x
- Memory footprint nhỏ (chỉ cache metadata)

### 2. Singleton Caching

Singleton instances được cache trong `$instances` array với O(1) lookup.

```php
// Check cache trước khi resolve
if (isset($this->instances[$id])) {
    return $this->instances[$id];
}
```

### 3. Lazy Resolution

Services chỉ được resolve khi thực sự cần thiết (lazy loading).

```php
// Binding được đăng ký nhưng chưa resolve
$container->singleton(HeavyService::class, fn() => new HeavyService());

// Chỉ resolve khi get()
$service = $container->get(HeavyService::class); // Resolve tại đây
```

## API Reference

### Core Methods

#### `get(string $id): mixed`
Resolve service từ container.

```php
$service = $container->get(ServiceInterface::class);
```

#### `has(string $id): bool`
Kiểm tra service có tồn tại trong container.

```php
if ($container->has(ServiceInterface::class)) {
    // Service is registered
}
```

#### `bind(string $id, callable|string|null $concrete = null, bool $shared = false): void`
Đăng ký binding.

```php
// Simple binding
$container->bind('service', fn() => new Service());

// Auto-bind (concrete = id)
$container->bind(Service::class);

// Shared binding
$container->bind('service', Service::class, shared: true);
```

#### `singleton(string $id, callable|string|null $concrete = null): void`
Đăng ký singleton.

```php
$container->singleton(LogManager::class, fn($c) => new LogManager($c->get('config')));
```

#### `instance(string $id, mixed $instance): void`
Đăng ký instance có sẵn.

```php
$container->instance('config', $configRepository);
```

#### `make(string $id): mixed`
Alias của `get()`.

```php
$service = $container->make(Service::class);
```

#### `call(callable|array|string $callable, array $parameters = []): mixed`
Gọi callable với dependency injection.

```php
// Method call
$container->call([Controller::class, 'method']);

// Function call
$container->call('function_name');

// With parameters
$container->call([Controller::class, 'method'], ['param' => 'value']);
```

### Advanced Methods

#### `when(string $abstract): ContextualBindingBuilder`
Bắt đầu contextual binding chain.

```php
$container->when(ConcreteClass::class)
    ->needs(AbstractInterface::class)
    ->give(Implementation::class);
```

#### `tag(array $abstracts, string $tag): void`
Gắn tag cho services.

```php
$container->tag([
    Service1::class,
    Service2::class,
], 'tag.name');
```

#### `tagged(string $tag): array`
Resolve tất cả services có tag.

```php
$services = $container->tagged('tag.name');
```

#### `extend(string $abstract, callable $extender): void`
Extend service sau khi resolve.

```php
$container->extend(Service::class, function ($service, $c) {
    $service->configure();
    return $service;
});
```

#### `resolving(string|callable $abstract, ?callable $callback = null): void`
Đăng ký resolving callback.

```php
// Service-specific
$container->resolving(Service::class, fn($s) => $s->init());

// Global
$container->resolving(fn($service, $c) => log($service));
```

#### `isShared(string $id): bool`
Kiểm tra binding có phải singleton.

```php
if ($container->isShared(Service::class)) {
    // Is singleton
}
```

#### `bound(string $id): bool`
Kiểm tra binding có tồn tại.

```php
if ($container->bound(Service::class)) {
    // Is bound
}
```

#### `forget(string $id): void`
Xóa binding và instance.

```php
$container->forget(Service::class);
```

#### `flush(): void`
Xóa tất cả bindings và instances.

```php
$container->flush();
```

## Ví dụ sử dụng

### 1. Service Provider Pattern

```php
class LogServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Singleton
        $container->singleton(LogManager::class, function ($c) {
            $config = $c->get('config')->get('logging', []);
            return new LogManager($config);
        });

        // Alias
        $container->bind('log', fn($c) => $c->get(LogManager::class));
    }
}
```

### 2. Contextual Binding

```php
// Repository pattern với contextual binding
$container->when(OrderService::class)
    ->needs(OrderRepositoryInterface::class)
    ->give(DatabaseOrderRepository::class);

$container->when(ReportService::class)
    ->needs(OrderRepositoryInterface::class)
    ->give(CacheOrderRepository::class);
```

### 3. Tagged Services

```php
// Register event listeners
$container->tag([
    UserCreatedListener::class,
    UserUpdatedListener::class,
    UserDeletedListener::class,
], 'user.listeners');

// Dispatch event to all listeners
$listeners = $container->tagged('user.listeners');
foreach ($listeners as $listener) {
    $listener->handle($event);
}
```

### 4. Method Injection

```php
class ProductController
{
    public function store(
        CreateProductRequest $request,
        CreateProductHandler $handler
    ) {
        return $handler->handle($request);
    }
}

// Router tự động inject dependencies
$container->call([ProductController::class, 'store'], [
    'request' => $httpRequest
]);
```

### 5. Extending Services

```php
// Extend logger để thêm handler
$container->extend(LoggerInterface::class, function ($logger, $c) {
    $logger->pushHandler(new FileHandler('app.log'));
    $logger->pushHandler(new ErrorLogHandler());
    return $logger;
});
```

## Best Practices

### 1. Luôn bind interfaces, không bind concrete classes

```php
// ❌ Bad
$container->singleton(DatabaseUserRepository::class, fn() => new DatabaseUserRepository());

// ✅ Good
$container->singleton(UserRepositoryInterface::class, fn() => new DatabaseUserRepository());
```

### 2. Sử dụng Service Providers để tổ chức bindings

```php
// ✅ Good: Organized in ServiceProvider
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(UserRepositoryInterface::class, fn() => new DatabaseUserRepository());
        $container->singleton(ProductRepositoryInterface::class, fn() => new DatabaseProductRepository());
    }
}
```

### 3. Sử dụng contextual bindings cho testability

```php
// Production
$container->when(OrderService::class)
    ->needs(EmailServiceInterface::class)
    ->give(SmtpEmailService::class);

// Testing
$container->when(OrderService::class)
    ->needs(EmailServiceInterface::class)
    ->give(MockEmailService::class);
```

### 4. Cache heavy services như singleton

```php
// ✅ Good: Cache expensive operations
$container->singleton(CacheManager::class, fn() => new CacheManager());
$container->singleton(DatabaseConnection::class, fn() => new PDO(...));
```

### 5. Sử dụng method injection cho controllers

```php
// ✅ Good: Dependencies rõ ràng
class ProductController
{
    public function create(CreateProductRequest $request, CreateProductHandler $handler)
    {
        return $handler->handle($request);
    }
}
```

## Performance Benchmarks

### Resolution Time

- **Singleton (cached)**: ~0.001ms (O(1))
- **New instance (auto-wired)**: ~0.1-0.5ms
- **With reflection cache**: ~2-3x faster than without cache

### Memory Usage

- **Reflection cache**: ~50-100KB per 100 classes
- **Singleton instances**: Depends on service size
- **Binding metadata**: ~1KB per binding

## Troubleshooting

### Circular Dependency

**Error:** `Circular dependency detected while resolving 'ServiceA'`

**Solution:**
```php
// Refactor để break circular dependency
// Option 1: Use lazy loading
$container->singleton(ServiceA::class, fn($c) => new ServiceA(
    fn() => $c->get(ServiceB::class) // Lazy
));

// Option 2: Extract common dependency
class ServiceA
{
    public function __construct(
        private CommonService $common,
        private ServiceB $b
    ) {}
}
```

### Service Not Found

**Error:** `Service 'ServiceInterface' not found in container`

**Solution:**
```php
// Đảm bảo service được bind
$container->bind(ServiceInterface::class, ConcreteService::class);

// Hoặc check trước khi get
if ($container->has(ServiceInterface::class)) {
    $service = $container->get(ServiceInterface::class);
}
```

### Cannot Resolve Parameter

**Error:** `Cannot resolve parameter '$param' for Class::method`

**Solution:**
```php
// Provide parameter khi call
$container->call([Class::class, 'method'], [
    'param' => $value
]);

// Hoặc bind parameter
$container->bind('param.name', fn() => $value);
```

## Kết luận

IoC Container của Toporia Framework cung cấp:

- ✅ **Auto-wiring** mạnh mẽ với reflection caching
- ✅ **Fluent API** quen thuộc và dễ sử dụng
- ✅ **Advanced features**: Contextual bindings, tagged services, extenders
- ✅ **High performance**: Reflection caching, singleton optimization
- ✅ **Clean Architecture**: Tuân thủ SOLID principles
- ✅ **PSR-11 compliant**: Tương thích với ecosystem PHP

Container là nền tảng vững chắc cho dependency injection trong framework, giúp code clean, testable và maintainable.

