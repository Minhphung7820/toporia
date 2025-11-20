#!/bin/bash

# Fix permissions for Toporia project in WSL2
# Run this after npm install or when permission errors occur

echo "🔧 Fixing file permissions..."

# Fix node_modules executables
if [ -d "node_modules/.bin" ]; then
    echo "  → Fixing node_modules/.bin executables..."
    find node_modules/.bin -type f -exec chmod +x {} \; 2>/dev/null
fi

# Fix esbuild binary
if [ -d "node_modules/@esbuild" ]; then
    echo "  → Fixing esbuild binary..."
    find node_modules/@esbuild -type f -name "esbuild" -exec chmod +x {} \; 2>/dev/null
fi

# Fix vite binary
if [ -f "node_modules/.bin/vite" ]; then
    chmod +x node_modules/.bin/vite
fi

# Fix PHP files (644 for files, 755 for directories)
echo "  → Fixing PHP file permissions..."
find src -type f -name "*.php" -exec chmod 644 {} \; 2>/dev/null
find src -type d -exec chmod 755 {} \; 2>/dev/null

# Fix bootstrap and config
find bootstrap -type f -name "*.php" -exec chmod 644 {} \; 2>/dev/null
find config -type f -name "*.php" -exec chmod 644 {} \; 2>/dev/null

echo "✅ Permissions fixed!"
echo ""
echo "Now you can run:"
echo "  npm run dev    → Start frontend"
echo "  php -S localhost:8000 -t public    → Start backend"
