# OpsTracker

[![CI](https://github.com/ElegAlex/OPSTRACKER/actions/workflows/ci.yml/badge.svg)](https://github.com/ElegAlex/OPSTRACKER/actions/workflows/ci.yml)
[![Docker](https://github.com/ElegAlex/OPSTRACKER/actions/workflows/docker-build.yml/badge.svg)](https://github.com/ElegAlex/OPSTRACKER/actions/workflows/docker-build.yml)

> Gestion d'operations IT pour les organisations - Pilotage des campagnes de migration et deploiement en temps reel.

## Installation rapide (Linux)

```bash
curl -sL https://raw.githubusercontent.com/ElegAlex/OPSTRACKER/main/scripts/quick-install.sh | sudo bash
```

Installe Docker si necessaire, configure tout, et demarre l'application sur **http://localhost**.

**Identifiants par defaut :**
- Email : `admin@opstracker.local`
- Mot de passe : `Admin123!`

## Documentation

| Document | Description |
|----------|-------------|
| [Installation RHEL 8.10](docs/INSTALL_RHEL.md) | Guide complet pour Red Hat Enterprise Linux (online/offline, proxy, SELinux, HTTPS) |
| [Guide d'exploitation](docs/EXPLOITATION.md) | Administration, sauvegardes, monitoring, depannage, securite |
| [Installation Docker](docs/DOCKER.md) | Installation manuelle avec Docker Compose |

## Fonctionnalites

- **Dashboard temps reel** : KPI, progression par segment, activite recente
- **Gestion des campagnes** : Creation, configuration, suivi des operations
- **Interface terrain** : Vue mobile pour les techniciens avec checklists interactives
- **Reservations** : Systeme de creneaux type Doodle pour les agents
- **Administration** : Gestion des utilisateurs, types d'operation, templates de checklist
- **Audit** : Tracabilite complete des modifications

## Installation manuelle (Docker)

### Prerequis

- Docker 24.0+ et Docker Compose 2.20+
- Git

### Etapes

```bash
# 1. Cloner le repository
git clone https://github.com/ElegAlex/OPSTRACKER.git
cd OPSTRACKER

# 2. Configurer l'environnement
cp .env.docker .env
# Editer .env : modifier APP_SECRET et DB_PASSWORD

# 3. Construire et demarrer
docker compose build
docker compose up -d

# 4. Executer les migrations
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# 5. Creer un administrateur
docker compose exec app php bin/console app:create-admin \
    admin@example.com Admin Admin --password='VotreMotDePasse123!'
```

Acceder a l'application : **http://localhost**

### Charger les donnees de demo (optionnel)

```bash
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

Comptes de demo disponibles :

| Email | Mot de passe | Role |
|-------|--------------|------|
| admin@demo.opstracker.local | Admin123! | Administrateur |
| sophie.martin@demo.opstracker.local | Sophie123! | Gestionnaire |
| karim.benali@demo.opstracker.local | Karim123! | Technicien |

## Images Docker

```bash
# Derniere version stable
docker pull ghcr.io/elegalex/opstracker:latest

# Version specifique
docker pull ghcr.io/elegalex/opstracker:v2.3.0
```

## Commandes utiles

```bash
# Gestion des conteneurs
docker compose up -d          # Demarrer
docker compose down           # Arreter
docker compose restart        # Redemarrer
docker compose logs -f        # Voir les logs

# Acces au conteneur PHP
docker compose exec app sh

# Base de donnees
docker compose exec app php bin/console doctrine:migrations:migrate
docker compose exec app php bin/console doctrine:fixtures:load

# Cache
docker compose exec app php bin/console cache:clear

# Tests
docker compose exec app php bin/phpunit
```

### Avec Makefile

```bash
make start          # Demarrer
make stop           # Arreter
make logs           # Voir les logs
make shell          # Acceder au shell PHP
make db-migrate     # Migrations
make db-backup      # Sauvegarder la BDD
make test           # Executer les tests
make deploy         # Mise a jour production
make help           # Toutes les commandes
```

## Architecture

```
opstracker/
├── config/              # Configuration Symfony
├── docker/              # Configuration Docker (Nginx, PHP, PostgreSQL)
├── docs/                # Documentation
├── migrations/          # Migrations Doctrine
├── public/              # Point d'entree web
├── scripts/             # Scripts d'installation
├── src/
│   ├── Command/         # Commandes console
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

| Composant | Version |
|-----------|---------|
| PHP | 8.3 |
| Symfony | 7.4 |
| PostgreSQL | 17 |
| Redis | 7 |
| Nginx | 1.25 |
| Tailwind CSS | 3.x |
| EasyAdmin | 4 |

## Regles metier

| Code | Description |
|------|-------------|
| RG-001 | Politique de mot de passe (8 car., majuscule, chiffre, special) |
| RG-006 | Verrouillage apres 5 tentatives echouees |
| RG-010 | Campagne avec dates, type operation, proprietaire |
| RG-017 | Transitions de statut des operations |
| RG-020 | Vue terrain filtree par technicien |
| RG-030 | Templates de checklist avec phases |
| RG-040 | Dashboard temps reel avec Turbo |
| RG-070 | Audit trail sur les entites principales |
| RG-080 | Triple signalisation (icone + couleur + texte) |
| RG-082 | Touch targets 44x44px minimum |

## Licence

MIT License

---

Developpe avec Symfony 7.4
