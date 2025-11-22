#!/bin/bash
set -e

echo "🗑️  Removing .phpunit.cache/ folder..."
echo ""

CACHE_DIR=".phpunit.cache"

if [ ! -d "$CACHE_DIR" ]; then
    echo "✅ Folder .phpunit.cache/ does not exist"
    exit 0
fi

# Try to remove without sudo first
if rm -rf "$CACHE_DIR" 2>/dev/null; then
    echo "✅ Successfully removed .phpunit.cache/"
else
    echo "⚠️  Cannot remove (permission denied - owned by root)"
    echo ""
    echo "💡 Solution: Run with sudo to remove root-owned folder"
    echo "   Command: sudo rm -rf .phpunit.cache"
    echo ""
    echo "   Or change ownership first, then remove:"
    echo "   sudo chown -R \$(whoami):\$(whoami) .phpunit.cache"
    echo "   rm -rf .phpunit.cache"
    echo ""
    echo "⚠️  Note: This folder will be automatically recreated when running PHPUnit tests"
    exit 1
fi

echo ""
echo "✅ Done! Folder will be automatically recreated when running PHPUnit tests"
echo "   (It's already ignored in .gitignore)"

