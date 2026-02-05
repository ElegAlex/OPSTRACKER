# OpsTracker

[![CI](https://github.com/ElegAlex/OPSTRACKER/actions/workflows/ci.yml/badge.svg)](https://github.com/ElegAlex/OPSTRACKER/actions/workflows/ci.yml)
[![Docker](https://github.com/ElegAlex/OPSTRACKER/actions/workflows/docker-build.yml/badge.svg)](https://github.com/ElegAlex/OPSTRACKER/actions/workflows/docker-build.yml)

> Gestion d'operations IT pour les organisations - Pilotage des campagnes de migration et deploiement en temps reel.

## Installation rapide (Linux - 1 commande)

```bash
curl -sL https://raw.githubusercontent.com/ElegAlex/OPSTRACKER/main/scripts/quick-install.sh | bash
```

Installe automatiquement Docker, configure tout, et demarre l'application.

**Autres OS / Installation manuelle** : Voir ci-dessous ou [documentation complete](docs/DOCKER.md)

## Documentation

| Document | Description |
|----------|-------------|
| [Installation RHEL 8.10](docs/INSTALL_RHEL.md) | Guide complet pour Red Hat Enterprise Linux (online/offline, proxy, SELinux, HTTPS) |
| [Guide d'exploitation](docs/EXPLOITATION.md) | Administration, sauvegardes, monitoring, depannage, securite |
| [Installation Docker](docs/DOCKER.md) | Installation standard avec Docker Compose |

## Quick Start (Docker)

```bash
# 1. Cloner
git clone https://github.com/ElegAlex/OPSTRACKER.git
cd OPSTRACKER

# 2. Configurer
cp .env.docker .env.local
# Editer .env.local (APP_SECRET, DB_PASSWORD)

# 3. Lancer
make install

# 4. Acceder : http://localhost
```

Documentation complete: [docs/DOCKER.md](docs/DOCKER.md)

## Images Docker

```bash
# Derniere version stable
docker pull ghcr.io/elegalex/opstracker:latest

# Version specifique
docker pull ghcr.io/elegalex/opstracker:v2.3.0
```

## Fonctionnalites

- **Dashboard temps reel** : KPI, progression par segment, activite recente
- **Gestion des campagnes** : Creation, configuration, suivi des operations
- **Interface terrain** : Vue mobile pour les techniciens avec checklists interactives
- **Administration** : Gestion des utilisateurs, types d'operation, templates de checklist

## Prerequis

- Docker 24.0+ et Docker Compose 2.20+
- Git
- Make (optionnel mais recommande)

## Installation rapide

```bash
# Avec Makefile (recommande)
make install

# Ou manuellement
docker compose up -d
docker compose exec app composer install
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# (Optionnel) Charger les donnees de demo
make db-fixtures
# ou: docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

## Acces a l'application

| Service | URL | Description |
|---------|-----|-------------|
| Application | http://localhost:8081 | Interface principale |
| PostgreSQL | localhost:5434 | Base de donnees |
| Redis | localhost:6381 | Cache/Sessions |

## Comptes de demo

Apres chargement des fixtures :

| Email | Mot de passe | Role |
|-------|--------------|------|
| admin@cpam92.fr | Admin123! | Administrateur |
| sophie.martin@cpam92.fr | Sophie123! | Gestionnaire |
| karim.benali@cpam92.fr | Karim123! | Technicien |

## Architecture

```
opstracker/
├── config/              # Configuration Symfony
├── docker/              # Configuration Docker (Nginx, PHP)
├── migrations/          # Migrations Doctrine
├── public/              # Point d'entree web
├── src/
│   ├── Controller/      # Controleurs (Dashboard, Terrain, Admin)
│   ├── Entity/          # Entites Doctrine
│   ├── Repository/      # Repositories
│   ├── Service/         # Services metier
│   └── DataFixtures/    # Fixtures de demo
├── templates/           # Templates Twig
├── tests/               # Tests PHPUnit
└── docker-compose.yml   # Configuration Docker Compose
```

## Stack technique

- **Backend:** PHP 8.3, Symfony 7.4
- **Base de donnees:** PostgreSQL 17
- **Frontend:** Twig, Tailwind CSS, Turbo (Hotwire)
- **Admin:** EasyAdmin 4
- **Tests:** PHPUnit 12

## Commandes utiles

```bash
# Avec Makefile
make start          # Demarrer
make stop           # Arreter
make logs           # Voir les logs
make test           # Executer les tests
make cache-clear    # Vider le cache
make shell          # Acceder au shell PHP
make db-migrate     # Migrations
make db-backup      # Sauvegarder la BDD
make deploy         # Mise a jour production
make help           # Voir toutes les commandes

# Sans Makefile
docker compose up -d
docker compose down
docker compose logs -f
docker compose exec app php bin/phpunit
```

## Tests

```bash
# Tous les tests
docker compose exec php php bin/phpunit

# Tests avec couverture
docker compose exec php php bin/phpunit --coverage-text

# Tests specifiques
docker compose exec php php bin/phpunit tests/Functional/
docker compose exec php php bin/phpunit tests/Unit/
```

## Deploiement production

### Variables d'environnement

Creer un fichier `.env.local` avec :

```env
APP_ENV=prod
APP_SECRET=<secret-aleatoire-32-caracteres>
DATABASE_URL="postgresql://user:password@host:5432/opstracker?serverVersion=17"
```

### Build production

```bash
# Installer les dependances production
docker compose exec php composer install --no-dev --optimize-autoloader

# Vider et warmer le cache
docker compose exec php php bin/console cache:clear --env=prod
docker compose exec php php bin/console cache:warmup --env=prod

# Executer les migrations
docker compose exec php php bin/console doctrine:migrations:migrate --env=prod --no-interaction
```

## Regles metier implementees

| Code | Description |
|------|-------------|
| RG-010 | Campagne avec dates, type operation, proprietaire |
| RG-017 | Transitions de statut des operations |
| RG-020 | Vue terrain filtree par technicien |
| RG-030 | Templates de checklist avec phases |
| RG-040 | Dashboard temps reel avec Turbo |
| RG-080 | Triple signalisation (icone + couleur + texte) |
| RG-082 | Touch targets 44x44px minimum |

## Licence

MIT License

---

Developpe avec Symfony 7.4 et Claude Code
