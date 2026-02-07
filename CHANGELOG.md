# Changelog

Toutes les modifications notables de ce projet sont documentees dans ce fichier.

Le format est base sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et ce projet adhere au [Semantic Versioning](https://semver.org/lang/fr/).

## [2.3.0] - 2025-06-01

### Added
- Images Docker publiees sur GitHub Container Registry (ghcr.io)
- Script d'installation rapide (`scripts/quick-install.sh`)
- Guide d'installation RHEL 8.10 (online/offline, proxy, SELinux, HTTPS)
- Guide d'exploitation (administration, sauvegardes, monitoring)
- Workflow CI GitHub Actions (tests, PHPStan, PHP-CS-Fixer)
- Workflow Docker build et push automatique

### Changed
- Montee en version PHP 8.3
- PHPStan monte au niveau 8
- Nettoyage des references specifiques pour publication open-source

## [2.2.0] - 2025-03-01

### Added
- Systeme de reservations type Doodle pour les agents
- Templates de checklist avec phases configurables
- Triple signalisation accessibilite (icone + couleur + texte)
- Touch targets 44x44px minimum pour la vue terrain

### Changed
- Amelioration du dashboard temps reel avec Turbo Streams
- Optimisation des requetes du tableau de bord

## [2.1.0] - 2025-01-15

### Added
- Audit trail complet sur les entites principales (auditor-bundle)
- Export CSV des operations
- Generation PDF des rapports de campagne
- Vue terrain mobile pour les techniciens

### Fixed
- Correction du verrouillage apres 5 tentatives echouees
- Correction des transitions de statut des operations

## [2.0.0] - 2024-10-01

### Added
- Migration vers Symfony 7.4
- Migration vers EasyAdmin 4
- Migration vers PostgreSQL 17
- Gestion des campagnes avec dates, types et proprietaires
- Dashboard temps reel avec KPI et progression par segment

### Changed
- Refonte complete de l'interface d'administration
- Passage de Webpack Encore a Asset Mapper
- Passage de Bootstrap a Tailwind CSS

### Removed
- Support de PHP 8.1

## [1.0.0] - 2024-06-01

### Added
- Version initiale d'OpsTracker
- Gestion des operations IT
- Authentification et gestion des utilisateurs
- Interface d'administration EasyAdmin
- Deploiement Docker avec Nginx, PHP-FPM, PostgreSQL, Redis

[2.3.0]: https://github.com/ElegAlex/OPSTRACKER/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/ElegAlex/OPSTRACKER/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/ElegAlex/OPSTRACKER/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/ElegAlex/OPSTRACKER/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/ElegAlex/OPSTRACKER/releases/tag/v1.0.0
