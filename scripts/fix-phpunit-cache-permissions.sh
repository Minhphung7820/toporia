#!/bin/bash
set -e

echo "🔧 Fixing .phpunit.cache permissions..."
echo ""

CURRENT_USER=$(whoami)
CURRENT_DIR=$(pwd)
CACHE_DIR=".phpunit.cache"

if [ ! -d "$CACHE_DIR" ]; then
    echo "✅ Folder .phpunit.cache/ does not exist (will be created automatically when running PHPUnit)"
    exit 0
fi

echo "📋 Current ownership:"
ls -ld "$CACHE_DIR" 2>/dev/null || true

echo ""
echo "🔐 Attempting to change ownership to $CURRENT_USER..."
echo "   Note: This requires sudo if the folder is owned by root"
echo ""

# Try to change ownership (will prompt for password if needed)
if sudo chown -R "$CURRENT_USER:$CURRENT_USER" "$CACHE_DIR" 2>/dev/null; then
    echo "✅ Successfully changed ownership to $CURRENT_USER"
    echo ""
    echo "📋 New ownership:"
    ls -ld "$CACHE_DIR"
else
    echo "❌ Failed to change ownership"
    echo ""
    echo "💡 Alternative solution: Delete the folder (it will be recreated automatically)"
    echo "   Run: sudo rm -rf .phpunit.cache"
    echo ""
    echo "   Or manually change ownership:"
    echo "   sudo chown -R $CURRENT_USER:$CURRENT_USER .phpunit.cache"
    exit 1
fi

# Also fix permissions
chmod -R u+w "$CACHE_DIR" 2>/dev/null || true

echo ""
echo "✅ Permissions fixed!"
echo ""
echo "💡 Note: If you still have issues, you can delete the folder:"
echo "   rm -rf .phpunit.cache"
echo "   (It will be automatically recreated when running PHPUnit tests)"

