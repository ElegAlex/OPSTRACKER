# Stack Technique OpsTracker

Documentation de l'architecture technique de l'application.

---

## Framework & Bundles

| Composant | Version | Usage |
|-----------|---------|-------|
| PHP | 8.3 | Runtime |
| Symfony | 7.4.* | Framework principal |
| EasyAdmin | 4.27+ | Interface administration |
| Doctrine ORM | 3.6+ | ORM / Persistence |
| Twig | 3.x | Moteur de templates |
| Turbo | 2.32+ | Navigation SPA-like |
| Stimulus | 2.32+ | Controleurs JavaScript |
| Tailwind CSS | via bundle | Framework CSS |

---

## Bundles Symfony

### Core

| Bundle | Usage |
|--------|-------|
| `symfony/framework-bundle` | Framework core |
| `symfony/security-bundle` | Authentification & Autorisation |
| `symfony/form` | Formulaires |
| `symfony/validator` | Validation |
| `symfony/workflow` | Machine a etats (statuts operations/campagnes) |

### Persistence

| Bundle | Usage |
|--------|-------|
| `doctrine/doctrine-bundle` | Integration Doctrine |
| `doctrine/doctrine-migrations-bundle` | Migrations BDD |
| `damienharper/auditor-bundle` | Audit trail (RG-070) |

### Frontend

| Bundle | Usage |
|--------|-------|
| `symfony/ux-turbo` | Turbo Drive/Frames/Streams |
| `symfony/stimulus-bundle` | Stimulus controllers |
| `symfony/asset-mapper` | Import maps (ES modules) |
| `symfonycasts/tailwind-bundle` | Compilation Tailwind |

### Utilitaires

| Bundle | Usage |
|--------|-------|
| `league/csv` | Import/Export CSV |
| `dompdf/dompdf` | Export PDF dashboards |
| `symfony/mailer` | Envoi emails |
| `symfony/notifier` | Notifications (SMS) |

---

## Architecture Admin

L'administration utilise **EasyAdmin Bundle**, PAS des templates Twig custom.

### Fichiers cles Admin

```
src/Controller/Admin/
├── DashboardController.php       # Config principale EasyAdmin
├── CampagneCrudController.php    # CRUD Campagnes
├── TypeOperationCrudController.php
├── ChecklistTemplateCrudController.php
├── UtilisateurCrudController.php
├── AgentCrudController.php
├── ChampPersonnaliseController.php
└── ConfigurationController.php   # Export/Import config
```

### Customisation EasyAdmin

- **CSS custom** : charge via `configureAssets()` dans `DashboardController`
  ```php
  public function configureAssets(): Assets
  {
      return Assets::new()
          ->addCssFile('styles/admin.css');
  }
  ```
- **Override templates** : `templates/bundles/EasyAdminBundle/` (si existants)

---

## Architecture Frontend (Campagnes)

Templates Twig custom avec Turbo/Stimulus.

### Structure Templates

```
templates/
├── campagne/
│   ├── _layout.html.twig         # Layout commun campagne
│   ├── _tabs.html.twig           # Onglets navigation
│   ├── index.html.twig           # Portfolio campagnes
│   ├── show.html.twig            # Detail campagne
│   └── ...
├── dashboard/
│   ├── campagne.html.twig        # Dashboard campagne
│   ├── global.html.twig          # Dashboard multi-campagnes
│   └── _*.html.twig              # Partials Turbo Frames
├── operation/
├── segment/
├── terrain/                       # Interface technicien mobile-first
├── booking/                       # Interface reservation agent
├── manager/                       # Interface manager
└── ...
```

### Controllers Stimulus

```
assets/controllers/
├── flash_controller.js           # Auto-dismiss messages flash
└── ...
```

---

## Conventions

### Routes

| Domaine | Pattern | Exemple |
|---------|---------|---------|
| Admin EasyAdmin | `/admin?crudAction=xxx` | `/admin?crudAction=index&crudControllerFqcn=...` |
| Campagnes | `/campagnes/{id}/...` | `/campagnes/5/operations` |
| Dashboard | `/dashboard/campagne/{id}` | `/dashboard/campagne/5` |
| Terrain | `/terrain/{id}` | `/terrain/123` |
| Reservation | `/reservation/{token}` | `/reservation/abc123...` |
| Manager | `/manager/campagne/{id}/...` | `/manager/campagne/5/agents` |

### CSS

| Zone | Fichier | Framework |
|------|---------|-----------|
| Frontend | `assets/styles/app.css` | Tailwind + custom |
| Admin | `assets/styles/admin.css` | EasyAdmin theme + custom |

### Roles

| Role | Description |
|------|-------------|
| `ROLE_USER` | Utilisateur authentifie |
| `ROLE_TECHNICIEN` | Technicien IT (vue terrain) |
| `ROLE_GESTIONNAIRE` | Gestionnaire campagnes |
| `ROLE_COORDINATEUR` | Coordinateur (perimetre delegue) |
| `ROLE_ADMIN` | Administrateur systeme |

---

## Regles Metier Cles

| Code | Description | Implementation |
|------|-------------|----------------|
| RG-010 | 5 statuts campagne avec couleurs | `Campagne::STATUTS` |
| RG-017 | Workflow operations | `config/packages/workflow.yaml` |
| RG-020 | Vue filtree technicien | `OperationRepository::findByTechnicien()` |
| RG-040 | Temps reel Turbo | `DashboardController::refresh()` |
| RG-070 | Audit trail | `damienharper/auditor-bundle` |
| RG-080 | Triple signalisation | CSS + icones + texte |
| RG-082 | Touch targets 44px | CSS terrain |
| RG-121 | 1 agent = 1 creneau | `ReservationService::reserver()` |

---

## Services Cles

```
src/Service/
├── CampagneService.php           # Logique metier campagnes
├── OperationService.php          # Transitions, assignations
├── DashboardService.php          # KPIs, statistiques
├── ChecklistService.php          # Gestion checklists
├── ReservationService.php        # Reservations creneaux
├── NotificationService.php       # Emails, SMS
├── ImportCsvService.php          # Import operations CSV
├── ExportCsvService.php          # Export operations
├── PdfExportService.php          # Export dashboard PDF
├── AuditService.php              # Consultation audit trail
└── ConfigurationService.php      # Export/Import config ZIP
```

---

## Base de Donnees

### Entites Principales

```
src/Entity/
├── Utilisateur.php               # Comptes IT (Sophie, Karim)
├── Campagne.php                  # Campagne de deploiement
├── Operation.php                 # Operation individuelle
├── Segment.php                   # Groupement operations
├── TypeOperation.php             # Config type (Windows, Mac...)
├── ChecklistTemplate.php         # Template checklist
├── ChecklistInstance.php         # Instance checklist/operation
├── Agent.php                     # Agent a migrer (reservation V2)
├── Creneau.php                   # Plage horaire reservation
├── Reservation.php               # Reservation agent/creneau
├── Document.php                  # Documents campagne
├── Prerequis.php                 # Prerequis campagne/segment
└── HabilitationCampagne.php      # Droits granulaires par campagne
```

### Relations Cles

```
Campagne 1--* Operation
Campagne 1--* Segment
Campagne 1--* Creneau
Campagne 1--* Prerequis

Operation *--1 Segment (nullable)
Operation *--1 Utilisateur (technicien, nullable)
Operation 1--1 ChecklistInstance (nullable)

Creneau 1--* Reservation
Reservation *--1 Agent

Agent *--1 Agent (manager, nullable)
```

---

## Commandes Console

```bash
# Cache
php bin/console cache:clear

# Migrations
php bin/console doctrine:migrations:migrate

# Fixtures (dev)
php bin/console doctrine:fixtures:load

# Debug routes
php bin/console debug:router

# Analyse statique
composer analyse

# Tests
composer test

# Code style
composer cs-fix
```

---

## Structure Projet

```
├── assets/                       # Frontend (JS, CSS)
│   ├── controllers/              # Stimulus controllers
│   └── styles/                   # CSS (Tailwind)
├── config/                       # Configuration Symfony
│   └── packages/
│       ├── doctrine.yaml
│       ├── security.yaml
│       └── workflow.yaml         # Machines a etats
├── migrations/                   # Migrations Doctrine
├── public/                       # Document root
├── src/
│   ├── Controller/
│   │   ├── Admin/                # EasyAdmin controllers
│   │   └── *.php                 # Controllers frontend
│   ├── Entity/                   # Entites Doctrine
│   ├── Form/                     # Types de formulaires
│   ├── Repository/               # Repositories Doctrine
│   ├── Security/                 # Voters, Authenticators
│   └── Service/                  # Logique metier
├── templates/                    # Templates Twig
│   └── bundles/                  # Overrides bundles
├── tests/                        # Tests PHPUnit
└── var/                          # Cache, logs
```
