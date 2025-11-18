# Session Security Guide

## Tổng Quan

`SessionSecurity` là một tính năng bảo mật nâng cao cho sessions, cung cấp:
- **Session Rotation**: Tự động regenerate session ID định kỳ
- **IP Binding**: Bind session với IP address (chống session hijacking)
- **Device Fingerprinting**: Bind session với device (User-Agent, Accept-Language, etc.)
- **Session Timeout**: Force session expiration sau thời gian nhất định

## Cách Sử Dụng

### 1. Sử dụng Middleware (Khuyến nghị)

**Cách 1: Thêm vào middleware group (tự động cho tất cả web routes)**

Trong `config/middleware.php`:

```php
'web' => [
    // Session security middleware (tự động load config từ config/session.php)
    fn($container) => \Toporia\Framework\Session\Middleware\ValidateSessionSecurity::fromContainer($container),

    // ... other middleware
    AddSecurityHeaders::class,
    CsrfProtection::class,
],
```

**Cách 2: Áp dụng cho route cụ thể**

```php
use Toporia\Framework\Support\Accessors\Route;
use Toporia\Framework\Session\Middleware\ValidateSessionSecurity;

// Áp dụng cho một route
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware([ValidateSessionSecurity::class]);

// Áp dụng cho route group
Route::group(['middleware' => [ValidateSessionSecurity::class]], function() {
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::post('/profile', [ProfileController::class, 'update']);
});
```

### 2. Sử dụng trực tiếp trong code

```php
use Toporia\Framework\Session\Security\SessionSecurity;
use Toporia\Framework\Session\Contracts\SessionStoreInterface;

// Lấy session từ container
$session = app('session');

// Tạo SessionSecurity instance
$security = new SessionSecurity(
    $session,
    enableIpBinding: true,        // Bind session với IP
    enableFingerprinting: true,   // Bind session với device
    rotationInterval: 300,        // Rotate session mỗi 5 phút
    maxLifetime: 3600             // Session tối đa 1 giờ
);

// Initialize (gọi khi session start)
$security->initialize();

// Validate (gọi trên mỗi request)
try {
    $security->validate();
    // Session hợp lệ, tiếp tục xử lý
} catch (\RuntimeException $e) {
    // Session security violation - xử lý lỗi
    $session->flush();
    return response()->json(['error' => $e->getMessage()], 401);
}
```

## Configuration

Cấu hình trong `config/session.php`:

```php
'security' => [
    'enable_ip_binding' => env('SESSION_SECURITY_IP_BINDING', true),
    'enable_fingerprinting' => env('SESSION_SECURITY_FINGERPRINTING', true),
    'rotation_interval' => env('SESSION_SECURITY_ROTATION_INTERVAL', 300), // 5 minutes
    'max_lifetime' => env('SESSION_SECURITY_MAX_LIFETIME', 0), // 0 = no limit
],
```

Hoặc trong `.env`:

```env
SESSION_SECURITY_IP_BINDING=true
SESSION_SECURITY_FINGERPRINTING=true
SESSION_SECURITY_ROTATION_INTERVAL=300
SESSION_SECURITY_MAX_LIFETIME=0
```

## Các Tính Năng

### 1. Session Rotation

Tự động regenerate session ID định kỳ để giảm nguy cơ session fixation attacks.

```php
// Rotate mỗi 5 phút
$security = new SessionSecurity($session, rotationInterval: 300);

// Disable rotation
$security = new SessionSecurity($session, rotationInterval: 0);
```

### 2. IP Binding

Bind session với IP address. Nếu IP thay đổi, session sẽ bị invalidate.

**Lưu ý**: Có thể gây vấn đề với:
- Users đằng sau proxy/load balancer (IP thay đổi)
- Mobile users (IP thay đổi khi chuyển network)

**Giải pháp**: Disable IP binding nếu users thường xuyên thay đổi IP:

```php
$security = new SessionSecurity($session, enableIpBinding: false);
```

### 3. Device Fingerprinting

Bind session với device fingerprint (User-Agent + Accept-Language + Accept-Encoding).

**Lưu ý**: Có thể gây vấn đề nếu:
- User update browser (User-Agent thay đổi)
- User thay đổi language settings

**Giải pháp**: Disable fingerprinting nếu cần:

```php
$security = new SessionSecurity($session, enableFingerprinting: false);
```

### 4. Session Timeout

Force session expiration sau thời gian nhất định (bất kể activity).

```php
// Session tối đa 1 giờ (3600 seconds)
$security = new SessionSecurity($session, maxLifetime: 3600);

// No limit (chỉ dựa vào session lifetime config)
$security = new SessionSecurity($session, maxLifetime: 0);
```

## Best Practices

1. **Enable cho production**: Bật tất cả tính năng trong production
2. **Disable IP binding cho mobile apps**: Nếu users thường xuyên thay đổi IP
3. **Tune rotation interval**:
   - Ngắn hơn (60-120s) cho high-security apps
   - Dài hơn (600-1800s) cho better UX
4. **Set max lifetime**: Force logout sau thời gian nhất định (ví dụ: 8 giờ)

## Ví Dụ Hoàn Chỉnh

```php
// config/middleware.php
'web' => [
    // Session security với config tự động
    fn($container) => \Toporia\Framework\Session\Middleware\ValidateSessionSecurity::fromContainer($container),

    AddSecurityHeaders::class,
    CsrfProtection::class,
],

// config/session.php
'security' => [
    'enable_ip_binding' => env('SESSION_SECURITY_IP_BINDING', true),
    'enable_fingerprinting' => env('SESSION_SECURITY_FINGERPRINTING', true),
    'rotation_interval' => env('SESSION_SECURITY_ROTATION_INTERVAL', 300),
    'max_lifetime' => env('SESSION_SECURITY_MAX_LIFETIME', 28800), // 8 hours
],
```

## Performance

- **O(1) operations**: Tất cả checks đều O(1)
- **Minimal overhead**: Chỉ thêm vài session lookups
- **Cached**: Session data được cache trong request

## Security Benefits

1. **Session Fixation Protection**: Rotation thay đổi session ID định kỳ
2. **Session Hijacking Protection**: IP binding và fingerprinting detect unauthorized access
3. **Session Timeout**: Force logout sau thời gian nhất định
4. **Automatic Invalidation**: Session tự động invalidate khi detect security violation

