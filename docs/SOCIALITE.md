# Socialite (OAuth) Documentation

## Overview

Toporia Framework provides a comprehensive OAuth authentication system called **Socialite**, allowing users to authenticate using popular social providers like Google, Facebook, GitHub, and more.

## Features

- ✅ **Multiple Providers**: Google, Facebook, GitHub (extensible)
- ✅ **OAuth 2.0 Flow**: Full authorization code flow
- ✅ **State Protection**: CSRF protection via state parameter
- ✅ **Token Management**: Access token and refresh token support
- ✅ **User Mapping**: Automatic user data mapping
- ✅ **Social Accounts**: Link multiple social accounts to users
- ✅ **Clean Architecture**: SOLID principles, dependency injection

## Architecture

### Clean Architecture

- **Domain Layer**: Models (`SocialAccount`)
- **Application Layer**: Manager (`SocialiteManager`)
- **Infrastructure Layer**: Providers (Google, Facebook, GitHub)
- **Presentation Layer**: Controllers

### SOLID Principles

- **Single Responsibility**: Each provider handles one OAuth provider
- **Open/Closed**: Extensible via custom providers
- **Liskov Substitution**: All providers are interchangeable
- **Interface Segregation**: Focused provider interface
- **Dependency Inversion**: Depends on abstractions

## Configuration

### Environment Variables

```env
# Google OAuth
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=/auth/socialite/google/callback

# Facebook OAuth
FACEBOOK_CLIENT_ID=your-facebook-app-id
FACEBOOK_CLIENT_SECRET=your-facebook-app-secret
FACEBOOK_REDIRECT_URI=/auth/socialite/facebook/callback

# GitHub OAuth
GITHUB_CLIENT_ID=your-github-client-id
GITHUB_CLIENT_SECRET=your-github-client-secret
GITHUB_REDIRECT_URI=/auth/socialite/github/callback
```

### Config File

Edit `config/socialite.php`:

```php
return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/socialite/google/callback'),
        'scopes' => ['openid', 'profile', 'email'],
    ],
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID', ''),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET', ''),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/socialite/facebook/callback'),
        'scopes' => ['email', 'public_profile'],
    ],
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID', ''),
        'client_secret' => env('GITHUB_CLIENT_SECRET', ''),
        'redirect' => env('GITHUB_REDIRECT_URI', '/auth/socialite/github/callback'),
        'scopes' => ['user:email'],
    ],
];
```

## Basic Usage

### Redirect to Provider

```php
use Toporia\Framework\Support\Accessors\Socialite;

// Redirect user to Google OAuth
return redirect(Socialite::driver('google')->redirect());

// Or use the controller route
// GET /auth/socialite/{provider}/redirect
```

### Handle Callback

```php
use Toporia\Framework\Support\Accessors\Socialite;

// Get user data from OAuth provider
$user = Socialite::driver('google')->user($request);

// Access user data
$userId = $user->id;
$name = $user->name;
$email = $user->email;
$avatar = $user->avatar;
$nickname = $user->nickname;

// Or use the controller route
// GET /auth/socialite/{provider}/callback
```

### Complete Authentication Flow

```php
namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Http\{Request, RedirectResponse, JsonResponse};
use Toporia\Framework\Support\Accessors\Socialite;
use Toporia\Framework\Socialite\Models\SocialAccount;
use App\Domain\User;

class AuthController
{
    public function redirectToProvider(Request $request, string $provider): RedirectResponse
    {
        $driver = Socialite::driver($provider);
        $url = $driver->redirect($request);

        return new RedirectResponse($url);
    }

    public function handleProviderCallback(Request $request, string $provider): RedirectResponse
    {
        try {
            $driver = Socialite::driver($provider);
            $socialUser = $driver->user($request);

            // Find or create user
            $user = $this->findOrCreateUser($socialUser, $provider);

            // Link social account
            $this->linkSocialAccount($user, $socialUser, $provider);

            // Login user
            auth()->login($user);

            return redirect('/dashboard');
        } catch (\Throwable $e) {
            return redirect('/login')->with('error', 'Authentication failed');
        }
    }

    private function findOrCreateUser($socialUser, string $provider): User
    {
        // Check if social account exists
        $socialAccount = SocialAccount::findByProvider($provider, $socialUser->id);

        if ($socialAccount) {
            return $socialAccount->user;
        }

        // Check if user exists by email
        $user = User::where('email', $socialUser->email)->first();

        if (!$user) {
            // Create new user
            $user = User::create([
                'name' => $socialUser->name,
                'email' => $socialUser->email,
                'avatar' => $socialUser->avatar,
                'email_verified_at' => now(), // OAuth emails are pre-verified
            ]);
        }

        return $user;
    }

    private function linkSocialAccount(User $user, $socialUser, string $provider): void
    {
        SocialAccount::updateOrCreate(
            [
                'provider' => $provider,
                'provider_id' => $socialUser->id,
            ],
            [
                'user_id' => $user->id,
                'name' => $socialUser->name,
                'email' => $socialUser->email,
                'avatar' => $socialUser->avatar,
                'nickname' => $socialUser->nickname,
                'metadata' => $socialUser->attributes,
            ]
        );
    }
}
```

## Routes

The framework provides default routes:

```php
// Redirect to provider
GET /auth/socialite/{provider}/redirect

// OAuth callback
GET /auth/socialite/{provider}/callback
```

### Custom Routes

```php
use Toporia\Framework\Support\Accessors\Route;
use App\Presentation\Http\Controllers\AuthController;

Route::get('/login/{provider}', [AuthController::class, 'redirectToProvider']);
Route::get('/login/{provider}/callback', [AuthController::class, 'handleProviderCallback']);
```

## Managing Social Accounts

### Link Multiple Accounts

```php
use Toporia\Framework\Socialite\Models\SocialAccount;

// Link Google account
$account = SocialAccount::create([
    'user_id' => $user->id,
    'provider' => 'google',
    'provider_id' => $googleUser->id,
    'name' => $googleUser->name,
    'email' => $googleUser->email,
    'avatar' => $googleUser->avatar,
]);

// Link Facebook account to same user
$account = SocialAccount::create([
    'user_id' => $user->id,
    'provider' => 'facebook',
    'provider_id' => $facebookUser->id,
    'name' => $facebookUser->name,
    'email' => $facebookUser->email,
]);
```

### Get User's Social Accounts

```php
$user = auth()->user();

// Get all social accounts
$accounts = $user->socialAccounts;

// Get specific provider
$googleAccount = SocialAccount::where('user_id', $user->id)
    ->where('provider', 'google')
    ->first();
```

### Unlink Social Account

```php
$account = SocialAccount::where('user_id', $user->id)
    ->where('provider', 'google')
    ->first();

$account->delete();
```

## Custom Providers

### Creating a Custom Provider

```php
namespace App\Infrastructure\Socialite\Providers;

use Toporia\Framework\Socialite\AbstractProvider;
use Toporia\Framework\Socialite\User;

class CustomProvider extends AbstractProvider
{
    protected function getAuthUrl(): string
    {
        return 'https://oauth.example.com/authorize';
    }

    protected function getTokenUrl(): string
    {
        return 'https://oauth.example.com/token';
    }

    protected function getUserUrl(): string
    {
        return 'https://api.example.com/user';
    }

    protected function mapUserToObject(array $user): User
    {
        return new User(
            id: (string) $user['id'],
            name: $user['name'],
            email: $user['email'],
            avatar: $user['avatar_url'] ?? null,
            nickname: $user['username'] ?? null,
            attributes: $user
        );
    }
}
```

### Registering Custom Provider

```php
use Toporia\Framework\Support\Accessors\Socialite;

Socialite::getInstance()->extend('custom', function ($httpClient, $config) {
    return new \App\Infrastructure\Socialite\Providers\CustomProvider(
        $config['client_id'],
        $config['client_secret'],
        $config['redirect'],
        $httpClient,
        $config['scopes'] ?? []
    );
});
```

## Advanced Usage

### Using Access Token Directly

```php
use Toporia\Framework\Support\Accessors\Socialite;

$driver = Socialite::driver('google');

// Get access token from callback
$token = $driver->getAccessToken($request);

// Get user data using token
$user = $driver->getUserFromToken($token);
```

### Custom Scopes

```php
// In config/socialite.php
'google' => [
    'scopes' => [
        'openid',
        'profile',
        'email',
        'https://www.googleapis.com/auth/calendar', // Custom scope
    ],
],
```

### Storing Tokens

```php
use Toporia\Framework\Socialite\Models\SocialAccount;

$account = SocialAccount::create([
    'user_id' => $user->id,
    'provider' => 'google',
    'provider_id' => $socialUser->id,
    'provider_token' => $accessToken,
    'provider_refresh_token' => $refreshToken,
    'provider_expires_at' => now()->addHours(1),
]);
```

## Security Best Practices

1. **State Parameter**: Always verify state parameter (handled automatically)
2. **HTTPS Only**: Always use HTTPS for OAuth redirects
3. **Secret Storage**: Store client secrets securely (use environment variables)
4. **Token Security**: Never expose access tokens to frontend
5. **Scope Limitation**: Request only necessary scopes

## Error Handling

```php
try {
    $user = Socialite::driver('google')->user($request);
} catch (\RuntimeException $e) {
    // Handle OAuth errors
    if (str_contains($e->getMessage(), 'Invalid state')) {
        return redirect('/login')->with('error', 'Invalid OAuth state');
    }

    if (str_contains($e->getMessage(), 'Access token not found')) {
        return redirect('/login')->with('error', 'Failed to get access token');
    }

    return redirect('/login')->with('error', 'Authentication failed');
}
```

## Testing

```php
use Toporia\Framework\Socialite\SocialiteManager;

// Mock provider
$mockProvider = Mockery::mock(ProviderInterface::class);
$mockProvider->shouldReceive('user')
    ->once()
    ->andReturn(new User('123', 'Test User', 'test@example.com'));

$manager = new SocialiteManager($container, $httpClient, []);
$manager->extend('test', fn() => $mockProvider);

$user = $manager->driver('test')->user($request);
```

## Migration

Run migrations to create social accounts table:

```bash
php console migrate
```

This creates:
- `social_accounts` - Links OAuth provider accounts to users

## Examples

### Vue.js Frontend Integration

```vue
<template>
  <div>
    <button @click="loginWithGoogle">Login with Google</button>
    <button @click="loginWithFacebook">Login with Facebook</button>
    <button @click="loginWithGitHub">Login with GitHub</button>
  </div>
</template>

<script setup>
const loginWithGoogle = () => {
  window.location.href = '/auth/socialite/google/redirect';
};

const loginWithFacebook = () => {
  window.location.href = '/auth/socialite/facebook/redirect';
};

const loginWithGitHub = () => {
  window.location.href = '/auth/socialite/github/redirect';
};
</script>
```

### API Response Format

After successful OAuth callback, user data is stored in session:

```php
// In callback handler
$_SESSION['socialite_user'] = [
    'id' => '123456789',
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'avatar' => 'https://example.com/avatar.jpg',
    'nickname' => 'johndoe',
    'attributes' => [...],
];

$_SESSION['socialite_provider'] = 'google';
```

## API Reference

### SocialiteManager

- `driver(string $provider): ProviderInterface`
- `extend(string $name, \Closure $callback): void`

### ProviderInterface

- `redirect(?Request $request = null): string`
- `user(Request $request): User`
- `getAccessToken(Request $request): string`
- `getUserFromToken(string $token): User`

### User

- `id: string` - Provider user ID
- `name: string` - User name
- `email: string` - User email
- `avatar: ?string` - Avatar URL
- `nickname: ?string` - Nickname
- `attributes: array` - Additional provider data
- `getAttribute(string $key, mixed $default = null): mixed`
- `toArray(): array`

