#!/bin/bash

# Test Runner Script
# Runs PHPUnit tests in Docker container

set -e

CONTAINER_NAME="project_topo_app"
WORK_DIR="/var/www/html"

echo "🧪 Running PHPUnit tests in Docker container..."

# Check if container is running
if ! docker ps | grep -q "$CONTAINER_NAME"; then
    echo "❌ Container $CONTAINER_NAME is not running"
    echo "💡 Start it with: docker-compose up -d"
    exit 1
fi

# Run tests
docker exec -it "$CONTAINER_NAME" sh -c "cd $WORK_DIR && vendor/bin/phpunit $@"

