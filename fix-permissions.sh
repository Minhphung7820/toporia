#!/bin/bash

# Fix permissions for Toporia project in WSL2
# This script runs automatically after npm install (via postinstall hook)
# You can also run it manually: bash fix-permissions.sh

echo "🔧 Fixing file permissions for node_modules..."

# Fix node_modules executables (all files in .bin directory)
if [ -d "node_modules/.bin" ]; then
    echo "  → Fixing node_modules/.bin executables..."
    find node_modules/.bin -type f -exec chmod +x {} \; 2>/dev/null
fi

# Fix vite binary specifically
if [ -f "node_modules/vite/bin/vite.js" ]; then
    chmod +x node_modules/vite/bin/vite.js 2>/dev/null
fi

# Fix esbuild binaries
if [ -d "node_modules/@esbuild" ]; then
    echo "  → Fixing esbuild binaries..."
    find node_modules/@esbuild -type f -name "esbuild*" -exec chmod +x {} \; 2>/dev/null
fi

# Fix other common binary directories
if [ -d "node_modules/.vite" ]; then
    find node_modules/.vite -type f -exec chmod +x {} \; 2>/dev/null 2>/dev/null
fi

echo "✅ Node modules permissions fixed!"
