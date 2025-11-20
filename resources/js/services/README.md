# HTTP Client Service - Centralized Error Handling

## 📖 Overview

HTTP client service với centralized error handling, tự động redirect đến error pages khi API trả về lỗi.

## 🎯 Features

- ✅ **Centralized Error Handling** - Tất cả lỗi API được xử lý tập trung
- ✅ **Auto Redirect** - Tự động redirect đến error pages (403, 404, 500, etc.)
- ✅ **CSRF Protection** - Tự động thêm CSRF token cho state-changing requests
- ✅ **Cookie-based Auth** - Session authentication qua HttpOnly cookies
- ✅ **Performance Optimized** - Single axios instance, lazy CSRF fetching
- ✅ **Clean & Maintainable** - Separation of concerns, easy to extend

## 🚀 Usage

### 1. Import HTTP Client

```javascript
import http from '@/services/http';
```

### 2. Make API Calls

```javascript
// GET request
const response = await http.get('/users');
const users = response.data;

// POST request (auto CSRF token)
const response = await http.post('/users', {
  name: 'John Doe',
  email: 'john@example.com'
});

// PUT request
await http.put(`/users/${id}`, { name: 'Jane Doe' });

// DELETE request
await http.delete(`/users/${id}`);
```

### 3. Error Handling

Errors are handled automatically by interceptor. No need to handle in components unless you need custom behavior:

```javascript
// Automatic error handling (recommended)
try {
  const response = await http.get('/api/protected-resource');
  // Success - use response.data
} catch (error) {
  // Error already handled by interceptor
  // Component will be redirected to error page
  // This catch is optional
}

// Custom error handling (advanced)
try {
  const response = await http.post('/api/users', userData);
} catch (error) {
  if (error.response?.status === 422) {
    // Handle validation errors in component
    const errors = error.response.data.errors;
    // Show validation errors to user
  }
  // Other errors already redirected by interceptor
}
```

## 🎨 Error Pages

Các lỗi tự động redirect đến pages tương ứng:

| Status Code | Route | Component | Behavior |
|------------|-------|-----------|----------|
| 401 | `/login?redirect=...` | Login.vue | Chuyển đến login, lưu redirect URL |
| 403 | `/error/403` | Error403.vue | Forbidden - không có quyền |
| 404 | `/error/404` | Error404.vue | Not Found - không tìm thấy |
| 419 | Auto reload CSRF | - | CSRF mismatch - reload token |
| 429 | `/error/429` | Error429.vue | Too Many Requests - rate limit |
| 500+ | `/error/500` | Error500.vue | Server Error |

## 🔧 Configuration

### Interceptor Logic

**Request Interceptor:**
- Tự động thêm CSRF token cho POST/PUT/PATCH/DELETE
- Lazy load CSRF cookie (chỉ fetch khi cần)
- Cache CSRF promise để tránh duplicate requests

**Response Interceptor:**
- Bắt tất cả error responses
- Auto redirect dựa trên status code
- Log errors để debug
- Return error để component có thể handle nếu cần

### Performance Optimizations

1. **Single Axios Instance** - Reuse connection pool
2. **CSRF Token Caching** - Chỉ fetch 1 lần
3. **Promise Deduplication** - Tránh duplicate CSRF requests
4. **Cookie Auto-send** - Browser tự động gửi cookies
5. **Error Page Redirect** - Ngăn redundant API calls

## 📝 Migration Guide

### Migrate từ authService

**Before:**
```javascript
import { authService } from '@/services/auth';

// authService tự xử lý CSRF
await authService.login(email, password);
const user = await authService.getUser();
```

**After:**
```javascript
import http from '@/services/http';

// http client tự xử lý CSRF
await http.post('/auth/login', { email, password });
const response = await http.get('/auth/user');
const user = response.data;
```

### Sử dụng cả 2 (recommended)

Giữ `authService` cho auth-specific logic, nhưng refactor để dùng `http` client:

```javascript
// services/auth.js (refactored)
import http from './http';

export const authService = {
  async login(email, password, remember = false) {
    const response = await http.post('/auth/login', {
      email, password, remember
    });
    return response.data;
  },

  async getUser() {
    const response = await http.get('/auth/user');
    return response.data;
  },

  async logout() {
    const response = await http.post('/auth/logout');
    return response.data;
  }
};
```

## 🛠️ Extending

### Add Custom Error Pages

1. Create error component:
```javascript
// resources/js/pages/errors/Error429.vue
```

2. Add route:
```javascript
// router/index.js
{
  path: '/error/429',
  name: 'error-429',
  component: Error429
}
```

3. Add to interceptor:
```javascript
// services/http.js
case 429:
  router.push({ name: 'error-429' }).catch(() => {});
  break;
```

### Customize Error Handling

```javascript
// services/http.js
http.interceptors.response.use(
  response => response,
  error => {
    const { status } = error.response;

    // Custom logic for specific status codes
    if (status === 402) {
      // Payment Required - custom handling
      router.push({ name: 'payment-required' });
    }

    return Promise.reject(error);
  }
);
```

## 🔍 Debugging

Enable console logs trong interceptor để debug:

```javascript
// Request logging
console.log('[HTTP] Request:', config.method, config.url);

// Response logging
console.log('[HTTP] Response:', response.status, response.data);

// Error logging
console.error('[HTTP] Error:', error.response?.status, error.response?.data);
```

## ⚠️ Important Notes

1. **Cookie Credentials**: `withCredentials: true` bắt buộc cho session auth
2. **CSRF Token**: Auto-fetched cho POST/PUT/PATCH/DELETE
3. **Error Pages**: Validation errors (422) không redirect, để component xử lý
4. **401 Redirect**: Auto redirect về login với query param redirect
5. **Router Catch**: `.catch(() => {})` tránh navigation errors

## 📚 Related

- [auth.js](./auth.js) - Authentication service
- [Router](../router/index.js) - Route definitions
- [Error Pages](../pages/errors/) - Error components
