#!/bin/bash
# Complete project setup script
# This script sets up the project after cloning

set -e

echo "🚀 Setting up Toporia project..."
echo ""

# Get project root directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$PROJECT_ROOT"

echo "📁 Creating config symlinks..."
if [ -d "configs" ]; then
    bash configs/setup-symlinks.sh
    echo ""
fi

echo "📦 Installing PHP dependencies..."
if [ -f "composer.json" ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
    echo ""
fi

echo "📦 Installing Node dependencies..."
if [ -f "package.json" ]; then
    npm install
    echo ""
fi

echo "🔐 Setting up environment..."
if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    cp .env.example .env
    echo "✅ Created .env file from .env.example"
    echo "⚠️  Remember to edit .env with your configuration"
    echo ""
fi

echo "🔑 Generating application key..."
if command -v php > /dev/null; then
    php console key:generate 2>/dev/null || echo "⚠️  Could not generate key (run manually: php console key:generate)"
    echo ""
fi

echo "✅ Project setup complete!"
echo ""
echo "Next steps:"
echo "  1. Edit .env file with your configuration"
echo "  2. Run migrations: php console migrate"
echo "  3. Start dev server: php -S localhost:8000 -t public"
echo "  4. Start Vite: npm run dev"

