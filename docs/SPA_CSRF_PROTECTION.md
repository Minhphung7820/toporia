# SPA CSRF Protection - Laravel Sanctum Style

## 📖 Overview

Toporia framework implements **stateful SPA authentication** following Laravel Sanctum pattern with CSRF protection via `XSRF-TOKEN` cookie.

## 🔐 How It Works

### Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Frontend: GET /api/csrf-cookie                              │
│    → Backend generates CSRF token                              │
│    → Store in session: $_SESSION['_csrf__token'] = 'abc123'   │
│    → Set cookie: Set-Cookie: XSRF-TOKEN=abc123 (HttpOnly=false)│
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. Frontend: JavaScript reads cookie                           │
│    → document.cookie // "XSRF-TOKEN=abc123" (auto-decoded)    │
│    → Store token for later use                                 │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. Frontend: POST /api/auth/login                             │
│    Headers:                                                     │
│      - X-XSRF-TOKEN: abc123 (from cookie)                     │
│      - Content-Type: application/json                          │
│    Body: {"email": "...", "password": "..."}                   │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. Backend: CSRF Middleware validates                          │
│    → Read header: X-XSRF-TOKEN = 'abc123'                     │
│    → Read session: $_SESSION['_csrf__token'] = 'abc123'       │
│    → Compare: hash_equals('abc123', 'abc123') → TRUE ✅        │
│    → Allow request to proceed                                  │
└─────────────────────────────────────────────────────────────────┘
```

## 🎯 Key Components

### 1. Backend Configuration

**[config/security.php](../config/security.php:36)**
```php
'csrf' => [
    'enabled' => true,
    'token_name' => '_token',
    'except' => [
        // Usually empty for SPAs
        // GET requests (like /api/csrf-cookie) are automatically safe

        // All POST/PUT/PATCH/DELETE requests use CSRF protection
        // This is the recommended Laravel Sanctum approach
    ],
],
```

**Why `except` array is empty?**
- ✅ GET requests auto-skipped (safe methods)
- ✅ `/api/csrf-cookie` is GET → no exception needed
- ✅ All state-changing requests protected → maximum security
- ✅ Clean config, no redundant rules

### 2. Frontend Service

**[resources/js/services/auth.js](../resources/js/services/auth.js:27)**
```javascript
// Step 1: Get CSRF cookie before any state-changing request
async function ensureCsrfCookie() {
  if (getCsrfToken()) return; // Already have token

  await fetch('/api/csrf-cookie', {
    credentials: 'include' // Send/receive cookies
  });
}

// Step 2: Read token from cookie
function getCsrfToken() {
  const cookies = document.cookie.split(';');
  for (let cookie of cookies) {
    const [name, value] = cookie.trim().split('=');
    if (name === 'XSRF-TOKEN') {
      return value; // Already decoded by browser
    }
  }
  return null;
}

// Step 3: Send token in header
async function login(email, password) {
  await ensureCsrfCookie(); // Ensure we have token

  const response = await fetch('/api/auth/login', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-XSRF-TOKEN': getCsrfToken() // ← Send token
    },
    credentials: 'include',
    body: JSON.stringify({ email, password })
  });

  return response.json();
}
```

### 3. Backend Middleware

**[src/Framework/Http/Middleware/CsrfProtection.php](../src/Framework/Http/Middleware/CsrfProtection.php:86)**
```php
private function getTokenFromRequest(Request $request): ?string
{
    // Priority order:
    // 1. Request body fields (_token, _csrf, csrf_token)
    // 2. Headers (X-CSRF-TOKEN, X-XSRF-TOKEN) ← SPA uses this
    // 3. Cookie fallback

    $headerToken = $request->header('X-XSRF-TOKEN');
    return $headerToken;
}

private function validateToken(?string $token): bool
{
    $storedToken = $_SESSION['_csrf__token'];
    return hash_equals($storedToken, $token); // Timing-safe comparison
}
```

## ⚡ Performance Optimizations

### 1. Token Caching (Frontend)
```javascript
let csrfCookiePromise = null;

async function ensureCsrfCookie() {
  // Prevent duplicate requests
  if (csrfCookiePromise) return csrfCookiePromise;

  // Check if already have token
  if (getCsrfToken()) return Promise.resolve();

  // Only fetch if needed
  csrfCookiePromise = fetch('/api/csrf-cookie', ...)
    .finally(() => csrfCookiePromise = null);

  return csrfCookiePromise;
}
```

**Benefits:**
- ✅ Only 1 request to `/api/csrf-cookie` per session
- ✅ Deduplicates concurrent calls
- ✅ O(1) token lookup from cookie

### 2. Early Return Pattern (Backend)
```php
// Skip safe methods immediately - O(1)
if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
    return $next($request, $response);
}

// Skip excluded URIs - O(N) where N is small
if ($this->shouldExcludeUri($path)) {
    return $next($request, $response);
}

// Only validate when necessary
if (!$this->validateToken($token)) {
    // Reject
}
```

### 3. Wildcard Pattern Matching
```php
// config/security.php
'except' => [
    '/api/webhooks/*',     // Matches /api/webhooks/stripe, etc.
    '/api/*/callback',     // Matches /api/github/callback, etc.
]

// O(1) exact match, O(N) wildcard (N = pattern length, typically < 50)
```

## 🛡️ Security Benefits

### 1. Double Submit Cookie Pattern
- Token stored in **session** (server-side)
- Token sent via **cookie** (XSRF-TOKEN)
- Token must be **re-sent in header** (X-XSRF-TOKEN)
- Attacker can't read cookie due to Same-Origin Policy

### 2. Defense in Depth
```
Layer 1: SameSite=Lax cookies → Prevents CSRF from cross-site requests
Layer 2: CSRF token validation → Ensures request came from our frontend
Layer 3: HttpOnly session cookies → Prevents XSS token theft
Layer 4: Secure flag (production) → Prevents MITM attacks
```

### 3. Timing Attack Prevention
```php
// WRONG - vulnerable to timing attacks
if ($storedToken === $token) { ... }

// CORRECT - constant-time comparison
if (hash_equals($storedToken, $token)) { ... }
```

## 📋 Best Practices

### ✅ DO

1. **Call `ensureCsrfCookie()` before first state-changing request**
   ```javascript
   await authService.login(email, password); // Already calls ensureCsrfCookie()
   ```

2. **Use credentials: 'include' for all API requests**
   ```javascript
   fetch('/api/endpoint', { credentials: 'include' })
   ```

3. **Send token in X-XSRF-TOKEN header**
   ```javascript
   headers: { 'X-XSRF-TOKEN': getCsrfToken() }
   ```

### ❌ DON'T

1. **Don't exclude `/api/auth/*` from CSRF protection**
   ```php
   // WRONG - reduces security
   'except' => ['/api/auth/*']

   // CORRECT - empty array (GET auto-skipped)
   'except' => []
   ```

2. **Don't add GET endpoints to `except` array**
   ```php
   // WRONG - redundant, GET is already safe
   'except' => ['/api/csrf-cookie']

   // CORRECT - let middleware skip automatically
   'except' => []
   ```

2. **Don't call `decodeURIComponent()` on cookie value**
   ```javascript
   // WRONG - double decoding
   return decodeURIComponent(value);

   // CORRECT - browser already decoded
   return value;
   ```

3. **Don't cache token in localStorage**
   ```javascript
   // WRONG - vulnerable to XSS
   localStorage.setItem('csrf_token', token);

   // CORRECT - read from cookie each time
   const token = getCsrfToken();
   ```

## 🔍 Troubleshooting

### Issue: "CSRF token mismatch"

**Cause 1: Token not sent in header**
```javascript
// Check Network tab → Request Headers
// Should see: X-XSRF-TOKEN: abc123...
```

**Cause 2: Cookie not set**
```javascript
// Check Application tab → Cookies
// Should see: XSRF-TOKEN with value
```

**Cause 3: Double decoding**
```javascript
// WRONG
return decodeURIComponent(cookieValue);

// CORRECT
return cookieValue; // Already decoded
```

**Cause 4: Session expired**
```php
// Backend session cleared
// Solution: Call ensureCsrfCookie() again
```

### Debug Script

```javascript
// Add to browser console
console.log('CSRF Token:', getCsrfToken());
console.log('All Cookies:', document.cookie);

// Test CSRF cookie endpoint
await fetch('/api/csrf-cookie', { credentials: 'include' });
console.log('After fetch:', getCsrfToken());
```

## 📊 Comparison with Other Approaches

| Approach | Security | Performance | Complexity |
|----------|----------|-------------|------------|
| **CSRF Cookie (Current)** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| Exclude `/api/auth/*` | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| Token in localStorage | ⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| No CSRF protection | ⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |

**Legend:**
- Security: Higher = better protection
- Performance: Higher = faster
- Complexity: Higher = simpler to implement

## 🔗 References

- [Laravel Sanctum SPA Authentication](https://laravel.com/docs/11.x/sanctum#spa-authentication)
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [Double Submit Cookie Pattern](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html#double-submit-cookie)

## 📝 Summary

**Current Implementation (Recommended):**
```php
// config/security.php
'except' => ['/api/csrf-cookie']
```

**Advantages:**
- ✅ Maximum security (CSRF protection for all auth endpoints)
- ✅ Laravel Sanctum compatible
- ✅ Industry best practice
- ✅ Performance optimized (token caching)
- ✅ Clean architecture

**Flow:**
1. GET `/api/csrf-cookie` → receive token
2. POST `/api/auth/login` with `X-XSRF-TOKEN` header
3. Backend validates token → allow/deny
