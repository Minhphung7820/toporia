#!/bin/bash
# Fix permissions for Node.js executables
# This script ensures all binaries in node_modules/.bin/ have execute permissions
# and all JavaScript files in bin/ directories can be executed

set -e

echo "=== Fixing Node.js executable permissions ==="

# Get the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$PROJECT_ROOT"

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "⚠️  node_modules directory not found. Skipping permission fix."
    exit 0
fi

echo "📁 Fixing permissions in node_modules/.bin/..."

# Fix permissions for all symlinks and files in node_modules/.bin/
if [ -d "node_modules/.bin" ]; then
    # Add execute permission to all files and symlinks in .bin directory
    find node_modules/.bin -type f -exec chmod +x {} \; 2>/dev/null || true
    find node_modules/.bin -type l -exec chmod +x {} \; 2>/dev/null || true

    echo "✓ Fixed permissions for node_modules/.bin/"
else
    echo "⚠️  node_modules/.bin/ directory not found"
fi

echo "📁 Fixing permissions for JavaScript bin files..."

# Fix permissions for all JavaScript files in bin/ directories
# These are the actual executables that symlinks point to
find node_modules -type f -path "*/bin/*.js" -exec chmod +x {} \; 2>/dev/null || true
find node_modules -type f -path "*/bin/*.sh" -exec chmod +x {} \; 2>/dev/null || true
find node_modules -type f -path "*/bin/*" ! -name "*.json" -exec chmod +x {} \; 2>/dev/null || true

echo "✓ Fixed permissions for bin files"

# Fix specific common binaries that might have permission issues
if [ -f "node_modules/vite/bin/vite.js" ]; then
    chmod +x node_modules/vite/bin/vite.js
    echo "✓ Fixed vite.js"
fi

if [ -f "node_modules/esbuild/bin/esbuild" ]; then
    chmod +x node_modules/esbuild/bin/esbuild 2>/dev/null || true
    echo "✓ Fixed esbuild"
fi

echo "✅ All permissions fixed successfully!"
echo ""

# Verify that vite can be executed
if [ -f "node_modules/.bin/vite" ]; then
    if [ -x "node_modules/.bin/vite" ]; then
        echo "✓ vite is executable"
    else
        echo "⚠️  WARNING: vite is still not executable"
        exit 1
    fi
fi
