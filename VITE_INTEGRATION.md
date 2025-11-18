# Vite Integration for Toporia Framework

Complete Vite asset bundler integration for Toporia Framework.
Uses custom Toporia Vite Plugin (no Laravel dependency).

## Features

- ✅ **Hot Module Replacement (HMR)** in development
- ✅ **Manifest-based asset loading** in production
- ✅ **Automatic dev/prod mode detection**
- ✅ **CSS extraction and injection**
- ✅ **Multiple entry points support**
- ✅ **Zero configuration** - works out of the box

## Installation

### 1. Install Dependencies

```bash
npm install --save-dev vite
```

**Note:** Toporia uses its own Vite plugin (`ToporiaVitePlugin.js`), no Laravel dependency required.

### 2. Configure Vite

The `vite.config.js` file is already created in the project root. Customize it as needed:

```javascript
import toporiaVitePlugin from './src/Framework/Support/Vite/ToporiaVitePlugin.js';

export default defineConfig({
    plugins: [
        toporiaVitePlugin({
            input: [
                'resources/js/app.js',
                'resources/js/admin.js',  // Add more entry points
            ],
            manifestPath: 'public/build/.vite/manifest.json',
            publicDir: 'public',
        }),
    ],
});
```

### 3. Update Configuration

Edit `config/vite.php` to match your setup:

```php
return [
    'manifest_path' => base_path('public/build/.vite/manifest.json'),
    'dev_server_url' => env('VITE_DEV_SERVER_URL', 'http://localhost:5173'),
    'dev_server_enabled' => env('VITE_DEV_SERVER_ENABLED', true),
    'build_path' => env('VITE_BUILD_PATH', '/build'),
    'entrypoints' => [
        'resources/js/app.js',
    ],
];
```

### 4. Environment Variables (Optional)

Add to `.env`:

```env
VITE_DEV_SERVER_URL=http://localhost:5173
VITE_DEV_SERVER_ENABLED=true
VITE_BUILD_PATH=/build
```

## Usage

### In View Templates

#### Basic Usage

```php
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My App</title>

    <!-- CSS (production only, dev handled by Vite) -->
    {!! vite_css('resources/js/app.js') !!}
</head>
<body>
    <div id="app"></div>

    <!-- JavaScript -->
    {!! vite('resources/js/app.js') !!}
</body>
</html>
```

#### Combined Assets

```php
<!-- Output both CSS and JS in one call -->
{!! vite_assets('resources/js/app.js') !!}
```

#### With Attributes

```php
<!-- Add custom attributes -->
{!! vite('resources/js/app.js', ['defer' => true, 'data-app' => 'myapp']) !!}
{!! vite_css('resources/js/app.js', ['media' => 'screen']) !!}
```

### In PHP Code

```php
use function vite;
use function vite_css;

// Get Vite instance
$vite = app('vite');

// Generate script tag
$script = $vite->script('resources/js/app.js');

// Generate CSS tags
$css = $vite->css('resources/js/app.js');

// Check if in development
if ($vite->isDevelopment()) {
    // Development-specific code
}
```

## Development Workflow

### Start Development Server

```bash
npm run dev
```

This starts Vite dev server with HMR at `http://localhost:5173`.

### Build for Production

```bash
npm run build
```

This generates:
- Optimized assets in `public/build/assets/`
- Manifest file at `public/build/.vite/manifest.json`

## How It Works

### Development Mode

When `VITE_DEV_SERVER_ENABLED=true` and Vite dev server is running:

1. `vite()` generates: `<script type="module" src="http://localhost:5173/resources/js/app.js"></script>`
2. `vite_css()` returns empty string (CSS handled by Vite)
3. Hot Module Replacement (HMR) works automatically

### Production Mode

When manifest file exists or `VITE_DEV_SERVER_ENABLED=false`:

1. `vite()` reads manifest and generates: `<script type="module" src="/build/assets/app-abc123.js"></script>`
2. `vite_css()` reads manifest and generates: `<link rel="stylesheet" href="/build/assets/app-def456.css">`
3. Assets are served from `public/build/` directory

## File Structure

```
toporia/
├── config/
│   └── vite.php                    # Vite configuration
├── public/
│   └── build/                      # Production build output
│       ├── assets/                  # Compiled assets
│       └── .vite/
│           └── manifest.json       # Asset manifest
├── resources/
│   └── js/
│       └── app.js                   # Entry point
├── src/
│   └── Framework/
│       ├── Providers/
│       │   └── ViteServiceProvider.php
│       └── Support/
│           └── Vite/
│               ├── Vite.php         # Main Vite class
│               └── Manifest.php     # Manifest parser
├── vite.config.js                   # Vite configuration
└── VITE_INTEGRATION.md              # This file
```

## API Reference

### Helper Functions

#### `vite(string $entry, array $attributes = []): string`

Generate script tag for Vite entry point.

**Parameters:**
- `$entry`: Entry point file path (e.g., 'resources/js/app.js')
- `$attributes`: Additional HTML attributes

**Returns:** HTML script tag string

#### `vite_css(string $entry, array $attributes = []): string`

Generate CSS link tags for Vite entry point.

**Parameters:**
- `$entry`: Entry point file path
- `$attributes`: Additional HTML attributes

**Returns:** HTML link tags string (empty in development)

#### `vite_assets(string $entry, array $scriptAttributes = [], array $cssAttributes = []): string`

Generate both script and CSS tags.

**Parameters:**
- `$entry`: Entry point file path
- `$scriptAttributes`: Script tag attributes
- `$cssAttributes`: CSS link tag attributes

**Returns:** Combined HTML tags string

### Vite Class Methods

#### `script(string $entry, array $attributes = []): string`

Generate script tag.

#### `css(string $entry, array $attributes = []): string`

Generate CSS link tags.

#### `assets(string $entry, array $scriptAttributes = [], array $cssAttributes = []): string`

Generate both script and CSS tags.

#### `isDevelopment(): bool`

Check if running in development mode.

## Troubleshooting

### Manifest Not Found Error

**Error:** `Vite manifest not found: ...`

**Solution:**
1. Run `npm run build` to generate the manifest
2. Ensure `manifest_path` in `config/vite.php` is correct
3. Check file permissions on `public/build/` directory

### Dev Server Not Working

**Error:** Assets not loading from dev server

**Solution:**
1. Ensure Vite dev server is running: `npm run dev`
2. Check `VITE_DEV_SERVER_URL` in `.env` matches Vite server URL
3. Verify `VITE_DEV_SERVER_ENABLED=true` in `.env`
4. Check browser console for CORS errors

### Permission Denied

**Error:** Cannot write to manifest or build directory

**Solution:**
```bash
chmod -R 775 public/build
chown -R www-data:www-data public/build  # If using web server user
```

## Advanced Configuration

### Custom Build Path

```php
// config/vite.php
'build_path' => env('VITE_BUILD_PATH', '/assets'),
```

### Multiple Entry Points

```php
// config/vite.php
'entrypoints' => [
    'resources/js/app.js',
    'resources/js/admin.js',
    'resources/js/dashboard.js',
];
```

### Custom Manifest Path

```php
// config/vite.php
'manifest_path' => base_path('storage/vite-manifest.json'),
```

## Examples

### React Application

```php
<!-- src/App/Presentation/Views/app.php -->
<!DOCTYPE html>
<html>
<head>
    {!! vite_css('resources/js/app.js') !!}
</head>
<body>
    <div id="root"></div>
    {!! vite('resources/js/app.js') !!}
</body>
</html>
```

### Vue Application

```php
<!-- src/App/Presentation/Views/app.php -->
<!DOCTYPE html>
<html>
<head>
    {!! vite_css('resources/js/app.js') !!}
</head>
<body>
    <div id="app"></div>
    {!! vite('resources/js/app.js') !!}
</body>
</html>
```

### Multiple Entry Points

```php
<!-- Admin panel -->
{!! vite_assets('resources/js/admin.js') !!}

<!-- Public site -->
{!! vite_assets('resources/js/app.js') !!}
```

## Performance

- **Development:** Zero overhead - direct Vite server connection
- **Production:** O(1) manifest lookup after initial parse
- **Lazy Loading:** Manifest only loaded when needed
- **Caching:** Manifest parsed once and cached in memory

## License

Part of Toporia Framework.

