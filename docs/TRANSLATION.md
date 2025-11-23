# Translation System

Hệ thống dịch (translation) chuyên nghiệp, tương thích với Laravel, được xây dựng theo Clean Architecture và SOLID principles.

## ✨ Features

- ✅ **Modern framework API** - Sử dụng `__()` và `trans()` giống Laravel
- ✅ **High Performance** - Cache translations, lazy loading, O(1) lookups
- ✅ **Clean Architecture** - Tách biệt rõ ràng các layers
- ✅ **SOLID Principles** - Mỗi component tuân thủ SOLID
- ✅ **High Reusability** - Dễ dàng mở rộng và tái sử dụng
- ✅ **Multiple Formats** - Hỗ trợ PHP arrays và JSON
- ✅ **Nested Keys** - Hỗ trợ dot notation (`messages.user.name`)
- ✅ **Placeholder Replacement** - Thay thế biến trong translations
- ✅ **Pluralization** - Hỗ trợ số nhiều/số ít
- ✅ **Fallback Locale** - Tự động fallback khi không tìm thấy translation

## 🚀 Quick Start

### 1. Sử dụng Helper Functions

```php
// Simple translation
echo __('messages.welcome'); // "Welcome" (en) hoặc "Chào mừng" (vi)

// With placeholders
echo __('messages.welcome_user', [':name' => 'John']);
// "Welcome, John!" (en) hoặc "Chào mừng, John!" (vi)

// With locale
echo __('messages.welcome', [], 'vi'); // "Chào mừng"

// Pluralization
echo trans_choice('messages.apples', 5);
// "5 apples" (en) hoặc "5 quả táo" (vi)
```

### 2. Sử dụng Facade

```php
use Toporia\Framework\Support\Accessors\Trans;

// Get translation
Trans::get('messages.welcome');
Trans::trans('messages.welcome', [':name' => 'John']);

// Pluralization
Trans::choice('messages.apples', 5);

// Check if exists
Trans::has('messages.welcome');

// Get/set locale
Trans::getLocale(); // 'en'
Trans::setLocale('vi');
```

### 3. Sử dụng Service

```php
$translator = app('translation');

$translator->get('messages.welcome');
$translator->trans('messages.welcome', [':name' => 'John'], 'vi');
$translator->choice('messages.apples', 5);
```

## 📁 File Structure

```
resources/lang/
├── en/
│   ├── messages.php
│   ├── validation.php
│   └── ...
├── vi/
│   ├── messages.php
│   ├── validation.php
│   └── ...
└── ...
```

## 📝 Translation Files

### PHP Array Format (Recommended)

```php
<?php
// resources/lang/en/messages.php

return [
    'welcome' => 'Welcome',
    'welcome_user' => 'Welcome, :name!',

    // Nested keys
    'user' => [
        'name' => 'Name',
        'email' => 'Email',
        'profile' => [
            'title' => 'User Profile',
        ],
    ],

    // Pluralization
    'apples' => '{0} No apples|{1} One apple|[2,*] :count apples',
];
```

### JSON Format

```json
{
  "welcome": "Welcome",
  "welcome_user": "Welcome, :name!",
  "user": {
    "name": "Name",
    "email": "Email"
  }
}
```

## 🔧 Configuration

File: `config/translation.php`

```php
return [
    'path' => env('TRANSLATION_PATH', base_path('resources/lang')),
    'fallback' => env('TRANSLATION_FALLBACK', 'en'),
    'available_locales' => [
        'en' => 'English',
        'vi' => 'Tiếng Việt',
    ],
    'cache' => env('TRANSLATION_CACHE', true),
    'cache_ttl' => env('TRANSLATION_CACHE_TTL', 3600),
];
```

## 💡 Usage Examples

### Basic Translation

```php
__('messages.welcome');
// "Welcome" (en) hoặc "Chào mừng" (vi)
```

### With Placeholders

```php
__('messages.welcome_user', [':name' => 'John']);
// "Welcome, John!" (en) hoặc "Chào mừng, John!" (vi)

__('messages.user_created', [':name' => 'Jane']);
// "User Jane has been created successfully."
```

### Nested Keys

```php
__('messages.user.name');
// "Name" (en) hoặc "Tên" (vi)

__('messages.user.profile.title');
// "User Profile" (en) hoặc "Hồ sơ người dùng" (vi)
```

### Pluralization

```php
// Simple pluralization
trans_choice('messages.apples', 0); // "No apples"
trans_choice('messages.apples', 1); // "One apple"
trans_choice('messages.apples', 5); // "5 apples"

// With replacements
trans_choice('messages.user_count', 5, [':name' => 'John']);
// "5 users found" (en) hoặc "Tìm thấy 5 người dùng" (vi)
```

### Check if Translation Exists

```php
if (trans_has('messages.welcome')) {
    echo __('messages.welcome');
}
```

### Change Locale

```php
// Using service
app('translation')->setLocale('vi');

// Using facade
Trans::setLocale('vi');

// Get current locale
$locale = Trans::getLocale(); // 'vi'
```

## 🎯 Advanced Features

### Namespace Prefix

```php
// Using namespace prefix
__('namespace::key');
__('custom::messages.welcome');
```

### Fallback Locale

Nếu translation không tìm thấy trong locale hiện tại, hệ thống tự động fallback về locale mặc định:

```php
// Current locale: 'vi'
// Fallback locale: 'en'

__('messages.unknown_key');
// Tìm trong 'vi' → không có → tìm trong 'en' → không có → trả về key
```

### Cache

Translations được cache tự động để tối ưu performance:

- **Memory cache**: Cache trong memory cho mỗi request
- **Persistent cache**: Cache vào file/Redis (nếu có cache service)

Clear cache:

```php
app('translation')->clearCache();
```

## 🏗️ Architecture

### Clean Architecture Layers

```
┌─────────────────────────────────────┐
│  Presentation Layer                 │
│  - Helper functions (__(), trans()) │
│  - Facade (Trans)                   │
└─────────────────────────────────────┘
           ↓
┌─────────────────────────────────────┐
│  Framework Layer                    │
│  - Translator (core service)        │
│  - FileLoader (load files)           │
│  - Contracts (interfaces)            │
└─────────────────────────────────────┘
```

### SOLID Principles

- **Single Responsibility**: Mỗi class có một trách nhiệm duy nhất
- **Open/Closed**: Dễ dàng mở rộng (thêm loader mới, format mới)
- **Liskov Substitution**: Tất cả implementations tuân thủ interfaces
- **Interface Segregation**: Interfaces nhỏ, tập trung
- **Dependency Inversion**: Phụ thuộc vào abstractions, không phải concretions

## ⚡ Performance

- **O(1) service lookup** - Cached sau lần đầu tiên
- **O(1) translation cache** - In-memory cache
- **Lazy loading** - Chỉ load khi cần
- **File existence check** - Kiểm tra file trước khi load
- **Persistent cache** - Cache vào file/Redis (optional)

## 📚 API Reference

### Helper Functions

- `__(string $key, array $replace = [], ?string $locale = null): string`
- `trans(string $key, array $replace = [], ?string $locale = null): string`
- `trans_choice(string $key, int|array $number, array $replace = [], ?string $locale = null): string`
- `trans_has(string $key, ?string $locale = null): bool`

### TranslatorInterface Methods

- `get(string $key, array $replace = [], ?string $locale = null): string`
- `trans(string $key, array $replace = [], ?string $locale = null): string`
- `choice(string $key, int|array $number, array $replace = [], ?string $locale = null): string`
- `has(string $key, ?string $locale = null): bool`
- `getLocale(): string`
- `setLocale(string $locale): void`
- `getFallback(): string`
- `setFallback(string $locale): void`
- `load(string $locale, string $namespace): array`

## 🔍 Examples

Xem các file translation mẫu:
- `resources/lang/en/messages.php`
- `resources/lang/vi/messages.php`
- `resources/lang/en/validation.php`
- `resources/lang/vi/validation.php`

## 🎉 Summary

Hệ thống translation này cung cấp:

✅ **Modern framework API** - Dễ dàng migrate từ Laravel
✅ **High Performance** - Tối ưu với cache và lazy loading
✅ **Clean Architecture** - Dễ maintain và test
✅ **SOLID Principles** - Code quality cao
✅ **High Reusability** - Dễ dàng mở rộng

Sử dụng `__()` và `trans()` giống như Laravel, nhưng với performance và architecture tốt hơn!

