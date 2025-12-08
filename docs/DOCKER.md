# Docker Setup Guide

Complete guide for running Toporia Framework with Docker - simple, stable, and high-performance like Laravel Sail.

## Quick Start

### Minimal Setup (PHP + MySQL + Redis)
```bash
# Start core services
docker-compose up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f app
```

### Full Setup (All Services)
```bash
# Start all services including Kafka, RabbitMQ, Elasticsearch
docker-compose --profile full up -d

# Stop all services
docker-compose --profile full down
```

## Architecture

```
┌──────────────────────────────────────────────────────────┐
│                    Nginx (Port 8000)                     │
│            Reverse Proxy + Static Files                  │
└────────────────────┬─────────────────────────────────────┘
                     │
                     ↓
┌──────────────────────────────────────────────────────────┐
│              PHP-FPM Application (Port 9000)             │
│         PHP 8.2 + Redis + RdKafka Extensions            │
└─────┬─────────┬──────────┬─────────────┬────────────────┘
      │         │          │             │
      ↓         ↓          ↓             ↓
   ┌─────┐  ┌─────┐  ┌────────┐  ┌──────────────┐
   │MySQL│  │Redis│  │RabbitMQ│  │Elasticsearch│
   │:3306│  │:6379│  │:5672   │  │:9200        │
   └─────┘  └─────┘  └────────┘  └──────────────┘
                         │
                         ↓
                  ┌──────────────┐
                  │Kafka + ZK    │
                  │:9092 :2181   │
                  └──────────────┘
```

## Services

### Core Services (Always Running)

| Service | Port | Description | Health Check |
|---------|------|-------------|--------------|
| **app** | 9000 | PHP-FPM application | PHP-FPM process + port |
| **nginx** | 8000 | Web server | HTTP /health endpoint |
| **mysql** | 3306 | Database | mysqladmin ping |
| **redis** | 6379 | Cache & queue | redis-cli ping |

### Optional Services (--profile full)

| Service | Port | Description | Health Check |
|---------|------|-------------|--------------|
| **rabbitmq** | 5672, 15672 | Message broker + UI | rabbitmq-diagnostics |
| **zookeeper** | 2181 | Kafka coordination | netcat check |
| **kafka** | 9092 | Event streaming | kafka-broker-api-versions |
| **elasticsearch** | 9200, 9300 | Search engine | cluster health API |

## Configuration

### Environment Variables

Create `.env` file in project root:

```bash
# Application
APP_ENV=local
APP_DEBUG=true
APP_PORT=8000

# Database
DB_DATABASE=toporia
DB_USERNAME=root
DB_PASSWORD=root
DB_PORT=3306

# Redis
REDIS_PORT=6379

# RabbitMQ
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
```

### Service Profiles

**Default profile** (no profile needed):
- PHP-FPM
- Nginx
- MySQL
- Redis

**Full profile** (`--profile full`):
- All default services
- RabbitMQ
- Kafka + Zookeeper
- Elasticsearch

## Common Commands

### Start Services
```bash
# Start minimal setup
docker-compose up -d

# Start with full stack
docker-compose --profile full up -d

# Start and rebuild images
docker-compose up -d --build

# Start specific services only
docker-compose up -d app mysql redis
```

### View Logs
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f app
docker-compose logs -f nginx

# Last 100 lines
docker-compose logs --tail=100 app
```

### Execute Commands
```bash
# Access PHP container shell
docker-compose exec app bash

# Run artisan commands
docker-compose exec app php console migrate
docker-compose exec app php console db:seed

# Run Composer
docker-compose exec app composer install
docker-compose exec app composer update

# Run PHPUnit tests
docker-compose exec app vendor/bin/phpunit

# Check PHP version
docker-compose exec app php -v

# Check installed extensions
docker-compose exec app php -m
```

### Service Management
```bash
# Stop all services
docker-compose down

# Stop and remove volumes (WARNING: deletes database!)
docker-compose down -v

# Restart specific service
docker-compose restart app
docker-compose restart nginx

# View service status
docker-compose ps

# View resource usage
docker stats
```

### Database Operations
```bash
# Access MySQL shell
docker-compose exec mysql mysql -u root -proot toporia

# Backup database
docker-compose exec mysql mysqldump -u root -proot toporia > backup.sql

# Restore database
docker-compose exec -T mysql mysql -u root -proot toporia < backup.sql

# Create new database
docker-compose exec mysql mysql -u root -proot -e "CREATE DATABASE test_db"
```

### Redis Operations
```bash
# Access Redis CLI
docker-compose exec redis redis-cli

# Clear all cache
docker-compose exec redis redis-cli FLUSHALL

# Monitor Redis
docker-compose exec redis redis-cli MONITOR

# Check memory usage
docker-compose exec redis redis-cli INFO memory
```

## Troubleshooting

### 502 Bad Gateway

**Symptoms**: Nginx returns 502 error

**Causes & Solutions**:

1. **PHP-FPM not started**
   ```bash
   # Check if PHP-FPM is running
   docker-compose exec app ps aux | grep php-fpm

   # Restart PHP-FPM container
   docker-compose restart app
   ```

2. **PHP-FPM timeout**
   ```bash
   # Check PHP-FPM logs
   docker-compose logs app

   # Increase timeouts in docker/nginx/default.conf
   fastcgi_read_timeout 300s;
   ```

3. **Network issues**
   ```bash
   # Check if Nginx can reach PHP-FPM
   docker-compose exec nginx ping app

   # Verify network
   docker network ls
   docker network inspect toporia_toporia
   ```

### Permission Denied Errors

**Symptoms**: Cannot write to storage/logs

**Solutions**:
```bash
# Fix permissions on host
sudo chown -R $USER:$USER storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Or fix inside container
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

### Vite Permission Denied

**Symptoms**: `npm run dev` fails with permission denied

**Solutions**:
```bash
# Remove problematic configs
# Already fixed in package.json - no custom userconfig

# Reinstall node modules
rm -rf node_modules package-lock.json
npm install

# Run Vite dev server
npm run dev
```

### Database Connection Refused

**Symptoms**: Cannot connect to MySQL

**Solutions**:
```bash
# Check if MySQL is healthy
docker-compose ps

# Wait for MySQL to be ready
docker-compose exec mysql mysqladmin ping -h localhost -u root -proot

# Check MySQL logs
docker-compose logs mysql

# Verify environment variables
docker-compose exec app env | grep DB_
```

### Slow Performance

**Causes & Solutions**:

1. **No OPcache**
   - Already enabled in Dockerfile with optimal settings
   - Verify: `docker-compose exec app php -i | grep opcache`

2. **Shared volumes on Windows/Mac**
   ```bash
   # Use Docker volume instead of bind mount for vendor/
   # Add to docker-compose.yml:
   volumes:
     - vendor:/var/www/html/vendor
   ```

3. **Resource limits**
   ```bash
   # Increase Docker Desktop resources
   # Settings → Resources → Advanced
   # RAM: 4GB+, CPUs: 2+
   ```

### Port Already in Use

**Symptoms**: Cannot start service, port conflict

**Solutions**:
```bash
# Find process using port
lsof -i :8000    # Nginx
lsof -i :3306    # MySQL

# Change port in .env
echo "APP_PORT=8080" >> .env
echo "DB_PORT=3307" >> .env

# Restart services
docker-compose down && docker-compose up -d
```

## Performance Optimization

### Production Deployment

```dockerfile
# Use multi-stage build
FROM toporia_app AS production

# Disable development tools
RUN composer install --no-dev --optimize-autoloader
RUN php console config:cache
RUN php console route:cache
```

### Resource Limits

Add to `docker-compose.yml`:
```yaml
services:
  app:
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 1G
        reservations:
          memory: 512M
```

### Caching Strategies

```bash
# Enable OPcache (already done in Dockerfile)
opcache.enable=1
opcache.memory_consumption=256

# Use Redis for sessions
SESSION_DRIVER=redis

# Use Redis for cache
CACHE_DRIVER=redis
```

## Health Checks

All services have health checks configured:

```yaml
# Example health check
healthcheck:
  test: ["CMD", "php-fpm-healthcheck"]
  interval: 10s
  timeout: 3s
  retries: 3
  start_period: 30s
```

Check health status:
```bash
# View health status
docker-compose ps

# View detailed health logs
docker inspect toporia_app --format='{{json .State.Health}}'
```

## Security Best Practices

1. **Use non-root user** (already configured as www-data)
2. **Disable PHP functions** (configured in docker/php/www.conf)
3. **Secure Nginx** (headers configured in docker/nginx/default.conf)
4. **Environment secrets** (use .env file, never commit)
5. **Update images regularly**
   ```bash
   docker-compose pull
   docker-compose up -d --build
   ```

## Monitoring

### View Container Stats
```bash
# Real-time resource usage
docker stats

# Specific containers
docker stats toporia_app toporia_nginx toporia_mysql
```

### Log Aggregation
```bash
# Centralized logging
docker-compose logs -f | tee logs/docker-$(date +%Y%m%d).log

# Filter by service
docker-compose logs nginx | grep ERROR
```

## Cleanup

```bash
# Remove stopped containers
docker-compose rm

# Remove unused images
docker image prune -a

# Remove unused volumes (WARNING: deletes data!)
docker volume prune

# Full cleanup
docker system prune -a --volumes
```

## Comparison with Laravel Sail

| Feature | Toporia Docker | Laravel Sail |
|---------|---------------|--------------|
| **Setup** | Single docker-compose.yml | Requires installation |
| **Services** | Minimal by default, full with --profile | All services always |
| **Customization** | Easy to modify | Requires publishing |
| **Health Checks** | All services | Limited |
| **Performance** | Optimized OPcache, buffers | Good |
| **Simplicity** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |

## Next Steps

- Read [INSTALLATION.md](../INSTALLATION.md) for application setup
- See [CLAUDE.md](../CLAUDE.md) for development guide
- Check [TESTING.md](TESTING.md) for running tests in Docker
