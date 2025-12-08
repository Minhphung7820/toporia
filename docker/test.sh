#!/bin/bash
# Docker Setup Test Script
# Validates that all services are working correctly

set -e

BLUE='\033[0;34m'
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Toporia Docker Setup Test ===${NC}\n"

# Function to test service
test_service() {
    local service=$1
    local test_command=$2
    local description=$3

    echo -n "Testing $description... "
    if eval "$test_command" > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC}"
        return 0
    else
        echo -e "${RED}✗${NC}"
        return 1
    fi
}

# Function to test HTTP endpoint
test_http() {
    local url=$1
    local description=$2

    echo -n "Testing $description... "
    if curl -sf "$url" > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC}"
        return 0
    else
        echo -e "${RED}✗${NC}"
        return 1
    fi
}

echo -e "${BLUE}1. Checking Docker Compose${NC}"
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}✗ docker-compose not found${NC}"
    exit 1
fi
echo -e "${GREEN}✓ docker-compose is installed${NC}\n"

echo -e "${BLUE}2. Checking Services Status${NC}"
docker-compose ps

echo ""
echo -e "${BLUE}3. Health Checks${NC}"

# Test PHP-FPM
test_service "PHP-FPM" \
    "docker-compose exec -T app php -v" \
    "PHP-FPM"

# Test MySQL
test_service "MySQL" \
    "docker-compose exec -T mysql mysqladmin ping -h localhost -u root -proot" \
    "MySQL"

# Test Redis
test_service "Redis" \
    "docker-compose exec -T redis redis-cli ping" \
    "Redis"

# Test Nginx
test_http "http://localhost:8000/health" "Nginx Health Endpoint"

echo ""
echo -e "${BLUE}4. Application Tests${NC}"

# Test PHP Extensions
echo -n "Checking PHP extensions... "
EXTENSIONS=$(docker-compose exec -T app php -m)
if echo "$EXTENSIONS" | grep -q "redis" && echo "$EXTENSIONS" | grep -q "pdo_mysql"; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

# Test Composer
test_service "Composer" \
    "docker-compose exec -T app composer --version" \
    "Composer"

# Test Database Connection
test_service "Database Connection" \
    "docker-compose exec -T app php -r 'new PDO(\"mysql:host=mysql;dbname=toporia\", \"root\", \"root\");'" \
    "Database Connection"

# Test Redis Connection
test_service "Redis Connection" \
    "docker-compose exec -T app php -r '\$r = new Redis(); \$r->connect(\"redis\", 6379); \$r->ping();'" \
    "Redis Connection"

echo ""
echo -e "${BLUE}5. Performance Checks${NC}"

# Check OPcache
echo -n "Checking OPcache... "
if docker-compose exec -T app php -i | grep -q "opcache.enable => On"; then
    echo -e "${GREEN}✓ Enabled${NC}"
else
    echo -e "${YELLOW}⚠ Not enabled${NC}"
fi

# Check Memory
echo -n "Checking PHP memory limit... "
MEMORY=$(docker-compose exec -T app php -i | grep "memory_limit" | head -1 | awk '{print $3}')
echo -e "${GREEN}$MEMORY${NC}"

echo ""
echo -e "${BLUE}6. Network Tests${NC}"

# Test Nginx → PHP-FPM connection
test_service "Nginx → PHP-FPM" \
    "docker-compose exec -T nginx ping -c 1 app" \
    "Nginx → PHP-FPM network"

# Test App → MySQL connection
test_service "App → MySQL" \
    "docker-compose exec -T app ping -c 1 mysql" \
    "App → MySQL network"

# Test App → Redis connection
test_service "App → Redis" \
    "docker-compose exec -T app ping -c 1 redis" \
    "App → Redis network"

echo ""
echo -e "${BLUE}7. Volume Tests${NC}"

# Test storage writable
test_service "Storage writable" \
    "docker-compose exec -T app test -w /var/www/html/storage/logs" \
    "Storage writable"

# Test bootstrap cache writable
test_service "Bootstrap cache writable" \
    "docker-compose exec -T app test -w /var/www/html/bootstrap/cache" \
    "Bootstrap cache writable"

echo ""
echo -e "${BLUE}=== Test Summary ===${NC}"

# Final HTTP test
echo -e "\nAccessing application at ${YELLOW}http://localhost:8000${NC}"
if curl -sf http://localhost:8000 > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Application is accessible${NC}"
else
    echo -e "${RED}✗ Application is not accessible${NC}"
fi

echo ""
echo -e "${GREEN}=== All Tests Completed ===${NC}"
echo -e "\nTo view logs: ${YELLOW}docker-compose logs -f${NC}"
echo -e "To access shell: ${YELLOW}docker-compose exec app bash${NC}"
echo -e "To stop services: ${YELLOW}docker-compose down${NC}"
echo ""
