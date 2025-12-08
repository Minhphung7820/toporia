# Docker Quick Start 🚀

Get Toporia Framework running in **under 2 minutes** with Docker.

## Prerequisites

- Docker Desktop installed ([Download](https://www.docker.com/products/docker-desktop))
- Git installed

## 1. Clone & Setup

```bash
# Clone repository
git clone https://github.com/tmp-dev/toporia.git
cd toporia

# Copy environment file
cp .env.example .env

# Generate application key
echo "APP_KEY=$(openssl rand -base64 32)" >> .env
```

## 2. Start Services

### Option A: Minimal Setup (Recommended)

```bash
# Start PHP + Nginx + MySQL + Redis
docker-compose up -d

# Wait ~30 seconds for services to be healthy
docker-compose ps
```

### Option B: Full Stack

```bash
# Start all services (Kafka, RabbitMQ, Elasticsearch)
docker-compose --profile full up -d
```

## 3. Initialize Application

```bash
# Install dependencies
docker-compose exec app composer install

# Run migrations
docker-compose exec app php console migrate

# (Optional) Seed database
docker-compose exec app php console db:seed
```

## 4. Access Application

**Web Application**: http://localhost:8000

**API Endpoint**: http://localhost:8000/api

**Health Check**: http://localhost:8000/health

## 5. Optional: Frontend Setup

```bash
# Install Node.js dependencies (on host machine)
npm install

# Start Vite dev server
npm run dev
```

Visit: http://localhost:5173

## Service URLs

| Service | URL | Credentials |
|---------|-----|-------------|
| **Application** | http://localhost:8000 | - |
| **MySQL** | localhost:3306 | root / root |
| **Redis** | localhost:6379 | - |
| **RabbitMQ UI** | http://localhost:15672 | guest / guest |
| **Elasticsearch** | http://localhost:9200 | - |

## Common Commands

```bash
# View logs
docker-compose logs -f app

# Access container shell
docker-compose exec app bash

# Run tests
docker-compose exec app vendor/bin/phpunit

# Stop services
docker-compose down

# Restart services
docker-compose restart
```

## Troubleshooting

### 502 Bad Gateway
```bash
# Restart PHP-FPM
docker-compose restart app

# Check logs
docker-compose logs app
```

### Permission Issues
```bash
# Fix storage permissions
sudo chown -R $USER:$USER storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### Port Conflicts
```bash
# Change ports in .env
echo "APP_PORT=8080" >> .env
echo "DB_PORT=3307" >> .env

# Restart
docker-compose down && docker-compose up -d
```

### Database Connection Failed
```bash
# Wait for MySQL to be ready
docker-compose exec mysql mysqladmin ping -u root -proot

# Check environment
docker-compose exec app env | grep DB_
```

## What's Running?

```bash
# Check service status
docker-compose ps

# View resource usage
docker stats

# Check health
docker-compose exec app php -v
docker-compose exec mysql mysqladmin ping -u root -proot
docker-compose exec redis redis-cli ping
```

## Full Documentation

- **Complete Docker Guide**: [docs/DOCKER.md](docs/DOCKER.md)
- **Development Guide**: [CLAUDE.md](CLAUDE.md)
- **Installation Guide**: [INSTALLATION.md](INSTALLATION.md)
- **Testing Guide**: [docs/TESTING.md](docs/TESTING.md)

## Architecture

```
Nginx :8000 → PHP-FPM :9000 → MySQL :3306
                            → Redis :6379
                            → Kafka :9092 (optional)
                            → RabbitMQ :5672 (optional)
                            → Elasticsearch :9200 (optional)
```

## Next Steps

1. ✅ Read [CLAUDE.md](CLAUDE.md) for development workflow
2. ✅ Check [docs/](docs/) for feature documentation
3. ✅ Run tests: `docker-compose exec app composer test`
4. ✅ Build something awesome! 🎉

---

**Need Help?** See [docs/DOCKER.md](docs/DOCKER.md) for detailed troubleshooting.
