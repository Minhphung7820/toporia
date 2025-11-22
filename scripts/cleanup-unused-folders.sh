#!/bin/bash
set -e

echo "🧹 Cleaning up unused folders..."
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to remove folder with confirmation
remove_folder() {
    local folder="$1"
    local description="$2"
    local force="$3"

    if [ ! -d "$folder" ]; then
        echo -e "${YELLOW}⚠️  Folder not found: $folder${NC}"
        return
    fi

    local size=$(du -sh "$folder" 2>/dev/null | cut -f1)

    if [ "$force" = "true" ]; then
        echo -e "${GREEN}🗑️  Removing: $folder ($size) - $description${NC}"
        rm -rf "$folder"
        echo -e "${GREEN}✅ Removed: $folder${NC}"
    else
        echo -e "${YELLOW}❓ Should remove: $folder ($size) - $description${NC}"
        read -p "   Remove this folder? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            rm -rf "$folder"
            echo -e "${GREEN}✅ Removed: $folder${NC}"
        else
            echo -e "${YELLOW}⏭️  Skipped: $folder${NC}"
        fi
    fi
}

# Force remove cache folders (safe, will be recreated)
echo "📦 Removing cache folders (safe to remove, will be recreated)..."
remove_folder ".phpunit.cache" "PHPUnit cache (will be recreated when running tests)" "true"

# Optional folders (ask for confirmation)
echo ""
echo "📋 Optional folders (may be useful)..."
echo ""

remove_folder "examples" "Example code (4 files, may be useful for learning)" "false"
remove_folder "deployment" "Deployment configs (supervisor, systemd - may be needed for production)" "false"

# Clean storage cache files (keep structure)
if [ -d "storage/cache" ]; then
    echo ""
    echo "🧹 Cleaning storage cache files..."
    find storage/cache -type f ! -name ".gitkeep" -delete 2>/dev/null || true
    echo -e "${GREEN}✅ Cleaned storage/cache (kept structure)${NC}"
fi

# Clean storage logs (keep structure)
if [ -d "storage/logs" ]; then
    echo ""
    echo "🧹 Cleaning storage logs..."
    find storage/logs -type f ! -name ".gitkeep" -name "*.log" -delete 2>/dev/null || true
    echo -e "${GREEN}✅ Cleaned storage/logs (kept structure)${NC}"
fi

# Clean storage sessions (keep structure)
if [ -d "storage/sessions" ]; then
    echo ""
    echo "🧹 Cleaning storage sessions..."
    find storage/sessions -type f ! -name ".gitkeep" -delete 2>/dev/null || true
    echo -e "${GREEN}✅ Cleaned storage/sessions (kept structure)${NC}"
fi

# Clean storage temp (keep structure)
if [ -d "storage/temp" ]; then
    echo ""
    echo "🧹 Cleaning storage/temp..."
    find storage/temp -type f ! -name ".gitkeep" -delete 2>/dev/null || true
    echo -e "${GREEN}✅ Cleaned storage/temp (kept structure)${NC}"
fi

# Clean bootstrap cache (keep structure)
if [ -d "bootstrap/cache" ]; then
    echo ""
    echo "🧹 Cleaning bootstrap/cache..."
    find bootstrap/cache -type f ! -name ".gitkeep" -delete 2>/dev/null || true
    echo -e "${GREEN}✅ Cleaned bootstrap/cache (kept structure)${NC}"
fi

echo ""
echo -e "${GREEN}✅ Cleanup complete!${NC}"
echo ""
echo "📊 Summary:"
echo "  - Removed cache folders"
echo "  - Cleaned storage directories (kept structure)"
echo ""
echo "💡 Note: Cache folders will be recreated automatically when needed."

