# Professional Authentication System

**Version:** 2.0.0
**Inspired by:** Laravel Sanctum + Passport
**Architecture:** Clean Architecture + SOLID Principles

---

## 🎯 Overview

Complete authentication system with:

- ✅ **Token Authentication** (Sanctum-style) - SPA + Mobile API
- ✅ **OAuth2 Server** (Passport-style) - Third-party integration
- ✅ **Personal Access Tokens** - CLI/API clients
- ✅ **Token Abilities (Scopes)** - Fine-grained permissions
- ✅ **Token Expiration & Refresh** - Security
- ✅ **Multi-Device Management** - Track all sessions
- ✅ **Rate Limiting** - Per-token limits

---

## 📊 Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    APPLICATION LAYER                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   LoginUse   │  │  RegisterUse │  │  LogoutUse   │      │
│  │     Case     │  │     Case     │  │     Case     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│                     DOMAIN LAYER                            │
│  ┌──────────────────────────────────────────────────┐       │
│  │          TokenRepository Interface               │       │
│  │          UserRepository Interface                │       │
│  │          AuthenticationService Interface         │       │
│  └──────────────────────────────────────────────────┘       │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│                  INFRASTRUCTURE LAYER                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Database   │  │    Redis     │  │     JWT      │      │
│  │  Repository  │  │    Cache     │  │   Service    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│                   PRESENTATION LAYER                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  Auth API    │  │  OAuth2 API  │  │  Middleware  │      │
│  │ Controllers  │  │ Controllers  │  │   Guards     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
```

---

## 🔐 Token Types

### 1. **Sanctum Tokens** (Personal Access Tokens)
```php
// For SPA, Mobile apps, CLI clients
$token = $user->createToken('mobile-app', ['read', 'write']);

// Simple, fast, database-backed
// No encryption overhead
```

### 2. **OAuth2 Tokens** (Passport-style)
```php
// For third-party applications
// Authorization Code Grant
// Client Credentials Grant
// Password Grant
// Refresh Token support
```

### 3. **Session Tokens**
```php
// For traditional web apps
// Cookie-based authentication
```

---

## 📁 File Structure

```
src/Framework/Auth/
├── Contracts/
│   ├── TokenRepositoryInterface.php
│   ├── AuthServiceInterface.php
│   ├── HasApiTokensInterface.php
│   └── OAuth2ServerInterface.php
│
├── Guards/
│   ├── SessionGuard.php           (existing)
│   ├── TokenGuard.php              (existing - will enhance)
│   ├── SanctumGuard.php           (NEW - Sanctum-style)
│   └── OAuth2Guard.php             (NEW - Passport-style)
│
├── Tokens/
│   ├── PersonalAccessToken.php    (NEW)
│   ├── TokenAbility.php           (NEW)
│   ├── NewAccessToken.php         (NEW)
│   └── TransientToken.php         (NEW)
│
├── OAuth2/
│   ├── Server/
│   │   ├── AuthorizationServer.php
│   │   ├── ResourceServer.php
│   │   └── Grants/
│   │       ├── AuthorizationCodeGrant.php
│   │       ├── ClientCredentialsGrant.php
│   │       ├── PasswordGrant.php
│   │       └── RefreshTokenGrant.php
│   │
│   ├── Entities/
│   │   ├── Client.php
│   │   ├── AccessToken.php
│   │   ├── RefreshToken.php
│   │   └── AuthCode.php
│   │
│   └── Repositories/
│       ├── ClientRepository.php
│       ├── AccessTokenRepository.php
│       ├── RefreshTokenRepository.php
│       └── AuthCodeRepository.php
│
├── Middleware/
│   ├── Authenticate.php            (existing - will enhance)
│   ├── EnsureTokenIsValid.php     (NEW)
│   ├── CheckScopes.php             (NEW)
│   ├── CheckForAnyScope.php        (NEW)
│   └── ThrottleWithToken.php       (NEW)
│
└── Traits/
    ├── HasApiTokens.php            (NEW - for User model)
    └── MustVerifyEmail.php         (NEW)
```

---

## 🚀 Quick Start

### Setup

```bash
# Run migrations
php console migrate

# Create OAuth2 clients
php console passport:install

# Create personal access client
php console passport:client --personal
```

### Usage Examples

#### 1. Sanctum-Style Token Authentication

```php
// Register user
POST /api/register
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secret123"
}

// Login and get token
POST /api/login
{
    "email": "john@example.com",
    "password": "secret123"
}

Response:
{
    "token": "1|abc123...",
    "type": "Bearer",
    "expires_at": "2025-02-16T00:00:00Z"
}

// Use token
GET /api/user
Authorization: Bearer 1|abc123...
```

#### 2. Create Personal Access Token

```php
$user = User::find(1);

// Simple token
$token = $user->createToken('my-app');

// Token with abilities/scopes
$token = $user->createToken('mobile-app', [
    'posts:read',
    'posts:write',
    'comments:read'
]);

// Get plain text token (only shown once!)
echo $token->plainTextToken; // "1|abc123xyz..."
```

#### 3. Protect Routes with Abilities

```php
// routes/api.php
$router->group(['middleware' => ['auth:sanctum']], function ($router) {

    // Requires 'posts:write' ability
    $router->post('/posts', [PostController::class, 'store'])
        ->middleware(['ability:posts:write']);

    // Requires ANY of these abilities
    $router->get('/posts', [PostController::class, 'index'])
        ->middleware(['abilities:posts:read,posts:write']);
});
```

#### 4. OAuth2 Authorization

```php
// Step 1: Redirect user to authorization endpoint
GET /oauth/authorize?
    client_id=CLIENT_ID&
    redirect_uri=https://app.com/callback&
    response_type=code&
    scope=read+write

// Step 2: User approves, receives auth code
https://app.com/callback?code=AUTH_CODE

// Step 3: Exchange code for access token
POST /oauth/token
{
    "grant_type": "authorization_code",
    "client_id": "CLIENT_ID",
    "client_secret": "CLIENT_SECRET",
    "code": "AUTH_CODE",
    "redirect_uri": "https://app.com/callback"
}

Response:
{
    "token_type": "Bearer",
    "expires_in": 3600,
    "access_token": "eyJ0eXAi...",
    "refresh_token": "def50200..."
}
```

---

## 🔧 Configuration

### config/auth.php

```php
return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'token',
            'provider' => 'users',
        ],
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
        'oauth2' => [
            'driver' => 'passport',
            'provider' => 'users',
        ],
    ],

    'sanctum' => [
        'expiration' => 60 * 24 * 15, // 15 days
        'token_prefix' => 'toporia_',
        'hash_algo' => 'sha256',
    ],

    'passport' => [
        'private_key' => env('PASSPORT_PRIVATE_KEY'),
        'public_key' => env('PASSPORT_PUBLIC_KEY'),
        'personal_access_client' => [
            'id' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_ID'),
            'secret' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET'),
        ],
    ],
];
```

---

## 📚 API Reference

### HasApiTokens Trait

```php
use Toporia\Framework\Auth\Traits\HasApiTokens;

class User extends Model implements Authenticatable {
    use HasApiTokens;
}

// Methods:
$user->createToken(string $name, array $abilities = []): NewAccessToken
$user->tokens(): Collection<PersonalAccessToken>
$user->currentAccessToken(): ?PersonalAccessToken
$user->tokenCan(string $ability): bool
$user->tokenCant(string $ability): bool
```

### PersonalAccessToken Model

```php
PersonalAccessToken::findToken(string $token): ?PersonalAccessToken
$token->can(string $ability): bool
$token->cant(string $ability): bool
$token->revoke(): void
$token->delete(): void
```

---

## 🔒 Security Features

### 1. Token Hashing
- Tokens hashed with SHA-256 before storage
- Plain text token only shown once at creation
- Database breach = tokens unusable

### 2. Token Expiration
```php
// Automatically expires after configured time
// Check expiration on every request
if ($token->hasExpired()) {
    throw new TokenExpiredException();
}
```

### 3. Rate Limiting per Token
```php
$router->post('/api/resource', [Controller::class, 'store'])
    ->middleware(['throttle.token:60,1']); // 60 req/min per token
```

### 4. Token Abilities (Scopes)
```php
// Fine-grained permissions
$token = $user->createToken('app', ['posts:read', 'posts:write']);

// Check in controller
if ($request->user()->tokenCan('posts:write')) {
    // Allow
}
```

### 5. Multi-Device Management
```php
// Get all user tokens
$tokens = $user->tokens;

// Revoke specific token
$user->tokens()->where('id', $tokenId)->delete();

// Revoke all except current
$user->tokens()->where('id', '!=', $currentToken->id)->delete();

// Revoke all tokens
$user->tokens()->delete();
```

---

## ⚡ Performance Optimizations

### 1. Token Lookup Cache (Redis)
```php
// Cache token lookups for 5 minutes
$cachedToken = cache()->tags(['tokens'])->remember(
    "token:{$hashedToken}",
    300,
    fn() => PersonalAccessToken::findToken($hashedToken)
);
```

### 2. Batch Token Validation
```php
// Validate multiple tokens in 1 query
$tokens = PersonalAccessToken::whereIn('token', $hashedTokens)->get();
```

### 3. Lazy Loading User
```php
// Don't load user until needed
$token->user; // Lazy loaded
```

---

## 📊 Database Schema

### personal_access_tokens table
```sql
CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_tokenable (tokenable_type, tokenable_id),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### oauth_clients table
```sql
CREATE TABLE oauth_clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    secret VARCHAR(100) NULL,
    provider VARCHAR(255) NULL,
    redirect TEXT NOT NULL,
    personal_access_client BOOLEAN NOT NULL DEFAULT 0,
    password_client BOOLEAN NOT NULL DEFAULT 0,
    revoked BOOLEAN NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 🧪 Testing

```php
// Test token authentication
$user = User::factory()->create();
$token = $user->createToken('test');

$this->get('/api/user', [
    'Authorization' => 'Bearer ' . $token->plainTextToken
])->assertOk();

// Test token abilities
$token = $user->createToken('test', ['posts:read']);

$this->post('/api/posts', [], [
    'Authorization' => 'Bearer ' . $token->plainTextToken
])->assertForbidden(); // No 'posts:write' ability
```

---

## 📖 Related Documentation

- [Guards & Providers](GUARDS_AND_PROVIDERS.md)
- [OAuth2 Implementation](OAUTH2_IMPLEMENTATION.md)
- [Token Security](TOKEN_SECURITY.md)
- [Rate Limiting](RATE_LIMITING.md)

---

**Next:** Implement the complete system! 🚀
