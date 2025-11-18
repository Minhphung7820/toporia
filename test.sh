#!/bin/bash

# Test Helper Script
# Chạy tests trong Docker container (khuyến nghị)

# Màu sắc
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Running tests in Docker container...${NC}"
echo ""

# Chạy command trong Docker container
docker exec project_topo_app sh -c "cd /var/www/html && php console test $@"

