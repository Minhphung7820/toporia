# Model Observers Registration

Framework hỗ trợ **3 cách** để đăng ký observers cho models. Bạn **KHÔNG nhất thiết** phải khai báo `$observers` property trong model class.

## Cách 1: Đăng ký qua Config File (Recommended)

**Ưu điểm:**
- Tập trung quản lý observers ở một nơi
- Dễ dàng thêm/xóa observers mà không cần sửa model
- Hỗ trợ priority và event-specific observers

**Cách làm:**

1. Mở file `config/observers.php`
2. Thêm observer vào config:

```php
return [
    \App\Domain\Product::class => [
        \App\Application\Observers\ProductObserver::class,
    ],

    // Multiple observers với priority
    \App\Domain\User::class => [
        \App\Application\Observers\UserObserver::class,
        ['class' => \App\Application\Observers\LogObserver::class, 'priority' => 10],
    ],
];
```

3. **Xóa** `$observers` property khỏi model (nếu có):

```php
// Product.php - KHÔNG CẦN property này nữa
// protected static array $observers = [
//     ProductObserver::class,
// ];
```

## Cách 2: Khai báo trong Model Class

**Ưu điểm:**
- Gần với model, dễ thấy observer của model
- Không cần config file

**Nhược điểm:**
- Phải sửa model mỗi khi thêm/xóa observer
- Khó quản lý khi có nhiều observers

**Cách làm:**

```php
namespace App\Domain;

use App\Application\Observers\ProductObserver;
use Toporia\Framework\Database\ORM\Model;

final class Product extends Model
{
    protected static array $observers = [
        ProductObserver::class,
    ];

    // ... rest of model
}
```

## Cách 3: Đăng ký thủ công (Manual Registration)

**Ưu điểm:**
- Linh hoạt, có thể đăng ký động
- Có thể đăng ký trong ServiceProvider hoặc bootstrap

**Nhược điểm:**
- Phải nhớ đăng ký, dễ quên
- Khó quản lý khi có nhiều models

**Cách làm:**

### Option A: Trong ServiceProvider

```php
namespace App\Providers;

use App\Domain\Product;
use App\Application\Observers\ProductObserver;
use Toporia\Framework\Foundation\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Product::observe(ProductObserver::class);
    }
}
```

### Option B: Trong bootstrap file

```php
// bootstrap/app.php hoặc routes/web.php
\App\Domain\Product::observe(\App\Application\Observers\ProductObserver::class);
```

## So sánh các cách

| Cách | Ưu điểm | Nhược điểm | Khi nào dùng |
|------|---------|------------|--------------|
| **Config** | Tập trung, dễ quản lý, hỗ trợ priority | Cần config file | **Recommended** - Dự án lớn, nhiều observers |
| **Model Property** | Gần model, dễ thấy | Phải sửa model mỗi lần | Dự án nhỏ, ít observers |
| **Manual** | Linh hoạt, động | Dễ quên, khó quản lý | Cần đăng ký động theo điều kiện |

## Recommendation

**Nên dùng Cách 1 (Config)** vì:
1. ✅ Tập trung quản lý observers
2. ✅ Dễ maintain và scale
3. ✅ Hỗ trợ advanced features (priority, event-specific)
4. ✅ Không cần sửa model khi thêm/xóa observers
5. ✅ Tuân thủ Clean Architecture (config ở Infrastructure layer)

## Ví dụ đầy đủ

### Config File (`config/observers.php`)

```php
return [
    // Simple observer
    \App\Domain\Product::class => [
        \App\Application\Observers\ProductObserver::class,
    ],

    // Multiple observers với priority
    \App\Domain\User::class => [
        \App\Application\Observers\UserObserver::class,
        ['class' => \App\Application\Observers\LogObserver::class, 'priority' => 10],
        ['class' => \App\Application\Observers\CacheObserver::class, 'event' => 'created', 'priority' => 5],
    ],
];
```

### Model (`src/App/Domain/Product.php`)

```php
namespace App\Domain;

use Toporia\Framework\Database\ORM\Model;

final class Product extends Model
{
    // KHÔNG CẦN $observers property nếu dùng config
    // protected static array $observers = [...];

    protected static string $table = 'products';
    // ... rest of model
}
```

## Lưu ý

1. **Chỉ cần dùng 1 trong 3 cách** - không cần dùng cả 3
2. Nếu dùng config, **xóa** `$observers` property khỏi model để tránh duplicate
3. Observers được **lazy-loaded** - chỉ instantiate khi cần
4. Observers được **cached** - mỗi observer class chỉ có 1 instance

