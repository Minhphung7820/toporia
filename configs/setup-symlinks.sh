#!/bin/bash
# Setup script to create symlinks for config files
# Run this after cloning the repository to ensure symlinks exist

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$PROJECT_ROOT"

echo "🔗 Setting up config file symlinks..."

# Check if configs directory exists
if [ ! -d "configs" ]; then
    echo "❌ Error: configs/ directory not found"
    exit 1
fi

# Create symlinks (remove existing files/symlinks first)
create_symlink() {
    local config_file="$1"  # Already includes dot prefix, e.g., ".npmrc"
    local target="configs/$config_file"
    local symlink="$config_file"  # Use as-is, already has dot

    if [ -f "$target" ]; then
        # Remove existing file or symlink
        rm -f "$symlink"

        # Create symlink
        ln -s "$target" "$symlink"

        if [ -L "$symlink" ]; then
            echo "✅ Created symlink: $symlink -> $target"
        else
            echo "❌ Failed to create symlink: $symlink"
            exit 1
        fi
    else
        echo "⚠️  Warning: Target file not found: $target"
    fi
}

# Create symlinks for all config files (with dot prefix)
create_symlink ".npmrc"
create_symlink ".nvmrc"
create_symlink ".dockerignore"

echo ""
echo "✅ All symlinks created successfully!"
echo ""
echo "Verification:"
echo "  .npmrc -> $(readlink .npmrc)"
echo "  .nvmrc -> $(readlink .nvmrc)"
echo "  .dockerignore -> $(readlink .dockerignore)"

