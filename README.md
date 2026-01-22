# OpsTracker

Application de suivi des operations IT pour les CPAM - Pilotage des campagnes de migration et deploiement en temps reel.

**Version:** 0.1.0-mvp

## Fonctionnalites

- **Dashboard temps reel** : KPI, progression par segment, activite recente
- **Gestion des campagnes** : Creation, configuration, suivi des operations
- **Interface terrain** : Vue mobile pour les techniciens avec checklists interactives
- **Administration** : Gestion des utilisateurs, types d'operation, templates de checklist

## Prerequis

- Docker et Docker Compose
- Git

## Installation rapide

```bash
# Cloner le projet
git clone <repository-url>
cd opstracker

# Lancer les conteneurs
docker compose up -d

# Installer les dependances PHP
docker compose exec php composer install

# Creer la base de donnees et executer les migrations
docker compose exec php php bin/console doctrine:database:create --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# (Optionnel) Charger les donnees de demo
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
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
# Demarrer l'environnement
docker compose up -d

# Arreter l'environnement
docker compose down

# Voir les logs
docker compose logs -f

# Executer les tests
docker compose exec php php bin/phpunit

# Vider le cache
docker compose exec php php bin/console cache:clear

# Creer une migration
docker compose exec php php bin/console make:migration

# Acceder au shell PHP
docker compose exec php bash
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

Proprietary - CPAM 92

---

Developpe avec Symfony 7.4 et Claude Code
