# Toporia Framework - Makefile
# Simple commands for Docker management

.PHONY: help up down build restart logs shell test clean

# Colors for output
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[1;33m
NC := \033[0m # No Color

help: ## Show this help message
	@echo "$(BLUE)Toporia Framework - Docker Commands$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-20s$(NC) %s\n", $$1, $$2}'

# ============================================
# Docker Services
# ============================================

up: ## Start all core services (app, nginx, mysql, redis)
	@echo "$(BLUE)Starting core services...$(NC)"
	docker compose up -d
	@echo "$(GREEN)✓ Services started. Access: http://localhost:8000$(NC)"

up-full: ## Start all services including Kafka, RabbitMQ, Elasticsearch
	@echo "$(BLUE)Starting full stack...$(NC)"
	docker compose --profile full up -d
	@echo "$(GREEN)✓ Full stack started$(NC)"

down: ## Stop all services
	@echo "$(BLUE)Stopping services...$(NC)"
	docker compose down
	@echo "$(GREEN)✓ Services stopped$(NC)"

down-clean: ## Stop services and remove volumes (WARNING: deletes data!)
	@echo "$(YELLOW)⚠ This will delete all data!$(NC)"
	@read -p "Are you sure? [y/N] " -n 1 -r; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker compose down -v; \
		echo "$(GREEN)✓ Services stopped and volumes removed$(NC)"; \
	fi

build: ## Build/rebuild Docker images
	@echo "$(BLUE)Building images...$(NC)"
	docker compose build --no-cache
	@echo "$(GREEN)✓ Build complete$(NC)"

restart: ## Restart all services
	@echo "$(BLUE)Restarting services...$(NC)"
	docker compose restart
	@echo "$(GREEN)✓ Services restarted$(NC)"

ps: ## Show running services
	docker compose ps

stats: ## Show resource usage
	docker stats

# ============================================
# Application Commands
# ============================================

shell: ## Access PHP container shell
	docker compose exec app bash

logs: ## Show all logs (follow mode)
	docker compose logs -f

logs-app: ## Show application logs
	docker compose logs -f app

logs-nginx: ## Show nginx logs
	docker compose logs -f nginx

install: ## Install Composer dependencies
	@echo "$(BLUE)Installing dependencies...$(NC)"
	docker compose exec app composer install
	@echo "$(GREEN)✓ Dependencies installed$(NC)"

migrate: ## Run database migrations
	@echo "$(BLUE)Running migrations...$(NC)"
	docker compose exec app php console migrate
	@echo "$(GREEN)✓ Migrations complete$(NC)"

migrate-fresh: ## Fresh migrate (drop all tables and re-run)
	@echo "$(YELLOW)⚠ This will drop all tables!$(NC)"
	docker compose exec app php console migrate:fresh

seed: ## Run database seeders
	@echo "$(BLUE)Seeding database...$(NC)"
	docker compose exec app php console db:seed
	@echo "$(GREEN)✓ Database seeded$(NC)"

# ============================================
# Testing
# ============================================

test: ## Run all PHPUnit tests
	@echo "$(BLUE)Running tests...$(NC)"
	docker compose exec app vendor/bin/phpunit

test-unit: ## Run unit tests only
	docker compose exec app vendor/bin/phpunit --testsuite Unit

test-feature: ## Run feature tests only
	docker compose exec app vendor/bin/phpunit --testsuite Feature

test-coverage: ## Run tests with coverage report
	docker compose exec app vendor/bin/phpunit --coverage-html coverage

# ============================================
# Cache & Optimization
# ============================================

cache-clear: ## Clear application cache
	docker compose exec app php console cache:clear

config-cache: ## Cache configuration
	docker compose exec app php console config:cache

route-cache: ## Cache routes
	docker compose exec app php console route:cache

optimize: ## Optimize application (cache config, routes)
	docker compose exec app php console optimize

optimize-clear: ## Clear all optimizations
	docker compose exec app php console optimize:clear

# ============================================
# Queue & Schedule
# ============================================

queue-work: ## Start queue worker
	docker compose exec app php console queue:work

queue-failed: ## List failed queue jobs
	docker compose exec app php console queue:failed

schedule-run: ## Run scheduled tasks
	docker compose exec app php console schedule:run

schedule-list: ## List all scheduled tasks
	docker compose exec app php console schedule:list

# ============================================
# Database Operations
# ============================================

mysql-cli: ## Access MySQL CLI
	docker compose exec mysql mysql -u root -proot toporia

redis-cli: ## Access Redis CLI
	docker compose exec redis redis-cli

db-backup: ## Backup database to backup.sql
	@echo "$(BLUE)Backing up database...$(NC)"
	docker compose exec mysql mysqladump -u root -proot toporia > backup.sql
	@echo "$(GREEN)✓ Database backed up to backup.sql$(NC)"

db-restore: ## Restore database from backup.sql
	@echo "$(BLUE)Restoring database...$(NC)"
	docker compose exec -T mysql mysql -u root -proot toporia < backup.sql
	@echo "$(GREEN)✓ Database restored$(NC)"

# ============================================
# Cleanup
# ============================================

clean: ## Remove stopped containers and dangling images
	@echo "$(BLUE)Cleaning up...$(NC)"
	docker compose rm -f
	docker image prune -f
	@echo "$(GREEN)✓ Cleanup complete$(NC)"

clean-all: ## Full cleanup (WARNING: removes all unused Docker resources)
	@echo "$(YELLOW)⚠ This will remove all unused Docker resources!$(NC)"
	@read -p "Are you sure? [y/N] " -n 1 -r; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker system prune -a --volumes -f; \
		echo "$(GREEN)✓ Full cleanup complete$(NC)"; \
	fi

# ============================================
# Initial Setup
# ============================================

setup: ## Complete initial setup (install, migrate, seed)
	@echo "$(BLUE)Running initial setup...$(NC)"
	@make install
	@make migrate
	@make seed
	@echo "$(GREEN)✓ Setup complete! Access: http://localhost:8000$(NC)"

setup-fresh: ## Fresh setup (down, up, install, migrate, seed)
	@echo "$(BLUE)Running fresh setup...$(NC)"
	@make down
	@make up
	@sleep 10
	@make install
	@make migrate
	@make seed
	@echo "$(GREEN)✓ Fresh setup complete!$(NC)"

# ============================================
# Health Checks
# ============================================

health: ## Check health of all services
	@echo "$(BLUE)Checking service health...$(NC)"
	@echo "PHP-FPM:"
	@docker compose exec app php -v | head -1
	@echo "MySQL:"
	@docker compose exec mysql mysqladmin ping -u root -proot 2>/dev/null && echo "MySQL is alive" || echo "MySQL is down"
	@echo "Redis:"
	@docker compose exec redis redis-cli ping
	@echo "Nginx:"
	@curl -s http://localhost:8000/health || echo "Nginx is down"

test-docker: ## Run Docker setup tests
	@bash docker/test.sh

# ============================================
# Development
# ============================================

npm-install: ## Install npm dependencies
	npm install

npm-dev: ## Start Vite dev server
	npm run dev

npm-build: ## Build frontend assets
	npm run build

composer-update: ## Update Composer dependencies
	docker compose exec app composer update

fix-permissions: ## Fix storage permissions (run from container)
	@echo "$(BLUE)Fixing permissions...$(NC)"
	docker compose exec app chmod -R 777 storage bootstrap/cache
	@echo "$(GREEN)✓ Permissions fixed$(NC)"

fix-storage: ## Fix storage permissions for WSL (777)
	@echo "$(BLUE)Fixing storage permissions...$(NC)"
	docker compose exec app chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache
	@echo "$(GREEN)✓ Storage permissions fixed (777)$(NC)"

fix-all-permissions: ## Fix ALL file permissions (WSL fix for new files)
	@echo "$(BLUE)Fixing all file permissions for WSL...$(NC)"
	@chmod -R 755 src config bootstrap routes public 2>/dev/null || true
	@find src -type f -exec chmod 644 {} \; 2>/dev/null || true
	@find config -type f -exec chmod 644 {} \; 2>/dev/null || true
	@find bootstrap -type f -exec chmod 644 {} \; 2>/dev/null || true
	@find routes -type f -exec chmod 644 {} \; 2>/dev/null || true
	@chmod -R 777 storage bootstrap/cache 2>/dev/null || true
	@echo "$(GREEN)✓ All permissions fixed$(NC)"

restart-fix: ## Restart app container (re-runs entrypoint permission fix)
	@echo "$(BLUE)Restarting app container to fix permissions...$(NC)"
	docker compose restart app
	@echo "$(GREEN)✓ Container restarted, permissions should be fixed$(NC)"

.DEFAULT_GOAL := help
