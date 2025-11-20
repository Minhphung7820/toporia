# SPA Authentication với HttpOnly Cookies

Framework Toporia đã hỗ trợ đầy đủ tính năng authentication với **HttpOnly cookies** cho React/Vue SPA.

## 🔒 Tính năng bảo mật

### HttpOnly Cookies
- ✅ **Backend tự động gửi cookie** - Không cần frontend can thiệp
- ✅ **Frontend KHÔNG thể đọc cookie** - JavaScript bị chặn (HttpOnly flag)
- ✅ **Browser tự động gửi cookie** - Mỗi request đều có cookie
- ✅ **Secure flag** - Chỉ gửi qua HTTPS trong production
- ✅ **SameSite protection** - Chống CSRF attacks

## 📋 Cấu hình

### 1. Session Configuration (`config/session.php`)
```php
'name' => env('SESSION_NAME', 'PHPSESSID'),
'lifetime' => env('SESSION_LIFETIME', 7200), // 2 hours
```

### 2. Security Configuration (`config/security.php`)
```php
'cookie' => [
    'http_only' => true,  // ✅ Frontend không thể đọc
    'secure' => env('APP_ENV') === 'production', // HTTPS only
    'same_site' => 'Lax', // CSRF protection
],
```

### 3. CORS Configuration (cho SPA)
```php
'cors' => [
    'enabled' => true,
    'credentials' => true, // ✅ Cho phép cookies
    'allowed_origins' => [
        'http://localhost:5173', // Vite dev server
        'https://yourdomain.com', // Production
    ],
],
```

## 🚀 Sử dụng

### Backend API Routes

```php
// routes/api.php
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/user', [AuthController::class, 'user']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
```

### Frontend (React/Vue)

#### Login Request
```javascript
// React/Vue
const response = await fetch('http://localhost:8000/api/auth/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    credentials: 'include', // ✅ Quan trọng: Gửi cookies
    body: JSON.stringify({
        email: 'user@example.com',
        password: 'password',
        remember: false
    })
});

const data = await response.json();
// Cookie PHPSESSID được tự động set bởi browser (HttpOnly)
// Frontend KHÔNG thể đọc cookie này
```

#### Authenticated Requests
```javascript
// Mỗi request sau đều tự động gửi cookie
const response = await fetch('http://localhost:8000/api/auth/user', {
    method: 'GET',
    credentials: 'include', // ✅ Gửi cookie tự động
});

const user = await response.json();
```

#### Logout
```javascript
await fetch('http://localhost:8000/api/auth/logout', {
    method: 'POST',
    credentials: 'include',
});
// Cookie được xóa tự động
```

## 🔐 Bảo mật

### 1. HttpOnly Flag
- Cookie không thể đọc bằng JavaScript
- Chống XSS attacks
- Chỉ browser mới có thể gửi cookie

### 2. Secure Flag (Production)
- Cookie chỉ gửi qua HTTPS
- Bảo vệ khỏi man-in-the-middle attacks

### 3. SameSite Protection
- `Lax`: Cookie chỉ gửi trong same-site requests
- Chống CSRF attacks

### 4. Session Security
- Session ID rotation
- IP binding (optional)
- Device fingerprinting (optional)

## 📝 Ví dụ đầy đủ

### Vue.js Example
```javascript
// services/auth.js
export const authService = {
    async login(email, password) {
        const response = await fetch('/api/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include', // ✅ Quan trọng
            body: JSON.stringify({ email, password })
        });
        return response.json();
    },

    async getUser() {
        const response = await fetch('/api/auth/user', {
            credentials: 'include' // ✅ Cookie tự động gửi
        });
        return response.json();
    },

    async logout() {
        await fetch('/api/auth/logout', {
            method: 'POST',
            credentials: 'include'
        });
    }
};
```

### React Example
```javascript
// hooks/useAuth.js
import { useState, useEffect } from 'react';

export function useAuth() {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetch('/api/auth/user', {
            credentials: 'include' // ✅ Cookie tự động
        })
            .then(res => res.json())
            .then(data => {
                setUser(data.user);
                setLoading(false);
            });
    }, []);

    const login = async (email, password) => {
        const res = await fetch('/api/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include', // ✅ Quan trọng
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();
        if (data.success) {
            setUser(data.user);
        }
        return data;
    };

    const logout = async () => {
        await fetch('/api/auth/logout', {
            method: 'POST',
            credentials: 'include'
        });
        setUser(null);
    };

    return { user, loading, login, logout };
}
```

## ⚠️ Lưu ý quan trọng

1. **`credentials: 'include'`** - Bắt buộc trong mọi request để gửi cookie
2. **CORS `credentials: true`** - Phải bật trong config
3. **Allowed Origins** - Phải cấu hình đúng domain của frontend
4. **Same Domain** - Nếu frontend và backend cùng domain, không cần CORS

## ✅ Kết luận

Framework Toporia đã có đầy đủ tính năng:
- ✅ HttpOnly cookies (frontend không đọc được)
- ✅ Backend tự động gửi cookie
- ✅ Browser tự động gửi cookie với mỗi request
- ✅ Secure & SameSite protection
- ✅ Session management tự động

**Không cần thêm code gì, chỉ cần sử dụng API endpoints!**

