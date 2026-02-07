# Makefile - Commandes simplifiées OpsTracker
.PHONY: help install start stop restart logs shell db-migrate db-fixtures clean build

# Variables
DOCKER_COMPOSE = docker compose
EXEC_APP = $(DOCKER_COMPOSE) exec app
EXEC_PHP = $(EXEC_APP) php

# Couleurs
GREEN = \033[0;32m
YELLOW = \033[0;33m
RED = \033[0;31m
NC = \033[0m

help: ## Afficher cette aide
	@echo "$(GREEN)OpsTracker - Commandes Docker$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(YELLOW)%-20s$(NC) %s\n", $$1, $$2}'

# ===========================================
# INSTALLATION
# ===========================================

install: ## Installation complete (premiere fois)
	@echo "$(GREEN)Installation OpsTracker...$(NC)"
	@test -f .env.local || cp .env .env.local
	@test -f docker-compose.override.yml || cp docker-compose.override.yml.dist docker-compose.override.yml
	$(DOCKER_COMPOSE) build --no-cache
	$(DOCKER_COMPOSE) up -d
	@echo "$(YELLOW)Attente PostgreSQL...$(NC)"
	@sleep 15
	$(MAKE) db-migrate
	@echo "$(GREEN)Installation terminee !$(NC)"
	@echo "$(GREEN)Application : http://localhost$(NC)"

install-prod: ## Installation production
	@echo "$(GREEN)Installation Production...$(NC)"
	$(DOCKER_COMPOSE) -f docker-compose.yml -f docker-compose.prod.yml build --no-cache
	$(DOCKER_COMPOSE) -f docker-compose.yml -f docker-compose.prod.yml up -d
	@sleep 15
	$(MAKE) db-migrate
	$(EXEC_PHP) bin/console cache:clear --env=prod
	@echo "$(GREEN)Deploiement production termine !$(NC)"

# ===========================================
# CYCLE DE VIE
# ===========================================

start: ## Demarrer les conteneurs
	$(DOCKER_COMPOSE) up -d

stop: ## Arreter les conteneurs
	$(DOCKER_COMPOSE) stop

restart: ## Redemarrer les conteneurs
	$(DOCKER_COMPOSE) restart

down: ## Arreter et supprimer les conteneurs
	$(DOCKER_COMPOSE) down

destroy: ## Tout supprimer (conteneurs + volumes)
	$(DOCKER_COMPOSE) down -v --remove-orphans

# ===========================================
# LOGS & DEBUG
# ===========================================

logs: ## Voir les logs (tous)
	$(DOCKER_COMPOSE) logs -f

logs-app: ## Logs application PHP
	$(DOCKER_COMPOSE) logs -f app

logs-nginx: ## Logs Nginx
	$(DOCKER_COMPOSE) logs -f nginx

logs-db: ## Logs PostgreSQL
	$(DOCKER_COMPOSE) logs -f postgres

shell: ## Ouvrir un shell dans le conteneur app
	$(EXEC_APP) sh

shell-root: ## Shell root dans app
	$(DOCKER_COMPOSE) exec -u root app sh

# ===========================================
# BASE DE DONNEES
# ===========================================

db-migrate: ## Executer les migrations
	$(EXEC_PHP) bin/console doctrine:migrations:migrate --no-interaction

db-diff: ## Generer une migration
	$(EXEC_PHP) bin/console doctrine:migrations:diff

db-fixtures: ## Charger les fixtures (dev)
	$(EXEC_PHP) bin/console doctrine:fixtures:load --no-interaction

db-reset: ## Reset complet de la BDD
	$(EXEC_PHP) bin/console doctrine:database:drop --force --if-exists
	$(EXEC_PHP) bin/console doctrine:database:create
	$(MAKE) db-migrate
	$(MAKE) db-fixtures

db-backup: ## Backup de la BDD
	@mkdir -p backups
	$(DOCKER_COMPOSE) exec postgres pg_dump -U opstracker opstracker > backups/backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "$(GREEN)Backup cree dans backups/$(NC)"

db-restore: ## Restaurer un backup (usage: make db-restore FILE=backup.sql)
	$(DOCKER_COMPOSE) exec -T postgres psql -U opstracker opstracker < $(FILE)

# ===========================================
# SYMFONY
# ===========================================

cache-clear: ## Vider le cache Symfony
	$(EXEC_PHP) bin/console cache:clear

assets: ## Compiler les assets (importmap)
	$(EXEC_PHP) bin/console importmap:install
	$(EXEC_PHP) bin/console asset-map:compile

tailwind: ## Compiler Tailwind CSS
	$(EXEC_PHP) bin/console tailwind:build

tailwind-watch: ## Watch Tailwind CSS
	$(EXEC_PHP) bin/console tailwind:build --watch

# ===========================================
# QUALITE
# ===========================================

test: ## Lancer les tests
	$(EXEC_PHP) bin/phpunit

cs-fix: ## Corriger le code style
	$(EXEC_APP) composer cs-fix

cs-check: ## Verifier le code style
	$(EXEC_APP) composer cs-check

analyse: ## Analyse statique (PHPStan)
	$(EXEC_APP) composer analyse

qa: cs-fix analyse test ## Qualite complete

# ===========================================
# BUILD
# ===========================================

build: ## Rebuild les images
	$(DOCKER_COMPOSE) build

build-no-cache: ## Rebuild sans cache
	$(DOCKER_COMPOSE) build --no-cache

pull: ## Mettre a jour les images de base
	$(DOCKER_COMPOSE) pull

# ===========================================
# PRODUCTION
# ===========================================

deploy: ## Deploiement (git pull + rebuild + migrate)
	git pull origin main
	$(DOCKER_COMPOSE) build app
	$(DOCKER_COMPOSE) up -d app
	$(MAKE) db-migrate
	$(MAKE) cache-clear
	@echo "$(GREEN)Deploiement termine !$(NC)"

status: ## Etat des conteneurs
	$(DOCKER_COMPOSE) ps

health: ## Verifier la sante des services
	@echo "$(YELLOW)Verification des services...$(NC)"
	@$(DOCKER_COMPOSE) exec postgres pg_isready -U opstracker && echo "$(GREEN)PostgreSQL: OK$(NC)" || echo "$(RED)PostgreSQL: KO$(NC)"
	@$(DOCKER_COMPOSE) exec redis redis-cli ping && echo "$(GREEN)Redis: OK$(NC)" || echo "$(RED)Redis: KO$(NC)"
	@curl -s -o /dev/null -w "%{http_code}" http://localhost/healthz | grep -q 200 && echo "$(GREEN)Nginx: OK$(NC)" || echo "$(RED)Nginx: KO$(NC)"
