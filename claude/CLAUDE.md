# CLAUDE.md — Instructions pour Claude Code

> **Projet** : OpsTracker
> **Version** : 2.0 (Module Réservation Doodle)
> **Dernière mise à jour** : 2026-02-01

---

## 🎯 Contexte Projet

**OpsTracker** est une application Symfony de gestion d'opérations IT terrain pour les CPAM (Assurance Maladie). Elle permet de piloter des campagnes de migration/déploiement avec suivi en temps réel.

**Utilisateurs cibles** :
- **Sophie** (Gestionnaire IT) : Configure les campagnes, suit les dashboards
- **Karim** (Technicien IT) : Exécute les interventions terrain avec checklists
- **Agent Impacté** (End-user) : Réserve son créneau d'intervention
- **Manager Métier** (Hors IT) : Supervise et positionne son équipe

**North Star** : "Sophie voit son dashboard se mettre à jour en temps réel pendant que Karim coche ses étapes sur le terrain."

---

## 📋 Protocole de Session

### Au DÉBUT de chaque session
```
1. Lire ce fichier (CLAUDE.md)
2. Lire claude/PROGRESS.md → état d'avancement
3. Lire claude/CURRENT_TASK.md → ta mission
4. Vérifier claude/BLOCKERS.md → points en attente
5. Vérifier que les tests passent : php bin/phpunit
```

### PENDANT la session
- Travailler sur **UNE seule tâche** (celle de CURRENT_TASK.md)
- Commiter fréquemment : `git commit -m "[T-XX] description"`
- Si bloqué > 15 min → documenter dans BLOCKERS.md et passer à la suite
- Si décision architecturale → documenter dans DECISIONS.md

### À la FIN de chaque session
```
1. Mettre à jour claude/PROGRESS.md (cocher les tâches terminées)
2. Ajouter une entrée dans claude/SESSION_LOG.md
3. Si tâche incomplète → mettre à jour CURRENT_TASK.md avec l'avancement
4. Commit final : git commit -m "[SESSION] End session - T-XX progress"
5. Push : git push
```

---

## 🛠️ Stack Technique

| Composant | Technologie | Version |
|-----------|-------------|---------|
| Framework | Symfony | 7.4 LTS |
| PHP | PHP | 8.3+ |
| Base de données | PostgreSQL | 17 |
| Cache/Sessions | Redis | 7+ |
| Frontend | Twig + Turbo + Stimulus | - |
| Assets | AssetMapper | (pas de npm) |
| Admin | EasyAdmin | 4.x |
| Workflow | Symfony Workflow | - |
| Tests | PHPUnit | 10+ |
| Fixtures | Alice + Faker | - |

---

## 🎨 DESIGN & FRONTEND — CRITIQUE

### Sources de vérité

Tous les éléments visuels DOIVENT respecter les fichiers dans `/design-reference/` :

| Fichier | Contenu | Usage |
|---------|---------|-------|
| `DESIGN_SYSTEM.md` | Couleurs, typo, espacements, composants | **OBLIGATOIRE** pour tout CSS/HTML |
| `mockups/*.html` | Maquettes HTML de référence | Copier/adapter le code |
| `twig-components/` | Composants Twig prêts à l'emploi | Inclure directement |

### Règles Design Bauhaus

```
┌─────────────────────────────────────────────────────────────────┐
│  RÈGLES IMPÉRATIVES                                             │
├─────────────────────────────────────────────────────────────────┤
│  1. PAS de border-radius (sauf cercles purs : avatars)          │
│  2. Bordures 2px border-ink (#0a0a0a) sur les cards             │
│  3. Fond paper (#f5f5f0), cards en white (#ffffff)              │
│  4. Police Space Grotesk uniquement                             │
│  5. Boutons terrain : min-height 56px (RGAA tactile)            │
│  6. Triple signalisation : icône + couleur + texte              │
└─────────────────────────────────────────────────────────────────┘
```

### Palette Couleurs

```
ink:      #0a0a0a   (texte, bordures)
paper:    #f5f5f0   (fond principal)
cream:    #fafaf5   (fond cards)
white:    #ffffff   (cards, modals)
muted:    #6b6b6b   (texte secondaire)

primary:  #2563eb   (bleu — actions, planifié)
success:  #059669   (vert — réalisé, en cours)
warning:  #d97706   (orange — reporté, alerte)
danger:   #dc2626   (rouge — erreur, à remédier)
complete: #0d9488   (teal — terminé)
```

### Statuts → Couleurs

| Statut Opération | Couleur | Variable |
|------------------|---------|----------|
| À planifier | gris | `muted` |
| Planifié | bleu | `primary` |
| En cours | bleu | `primary` |
| Réalisé | vert | `success` |
| Reporté | orange | `warning` |
| À remédier | rouge | `danger` |

### Mockups disponibles

| Fichier | Persona | Écran |
|---------|---------|-------|
| `portfolio.html` | Sophie | Liste campagnes |
| `campaign-dashboard.html` | Sophie | Dashboard campagne |
| `technician-list.html` | Karim | Mes interventions |
| `technician-detail.html` | Karim | Détail + Checklist |
| `reservation-agent.html` | Agent | Choix créneau |
| `reservation-manager.html` | Manager | Mon équipe |
| `reservation-confirmation.html` | Agent | Confirmation |

### Quand créer du HTML/CSS

1. **D'ABORD** : Ouvrir le mockup correspondant dans `/design-reference/mockups/`
2. **COPIER** : Reprendre la structure HTML et les classes Tailwind
3. **ADAPTER** : Remplacer les données statiques par des variables Twig
4. **VÉRIFIER** : Contraste ≥ 4.5:1, touch targets 44x44px min

### Composants Twig réutilisables

```twig
{# Inclure un badge statut #}
{% include 'components/_status-badge.html.twig' with {status: 'realise'} %}

{# Inclure une card campagne #}
{% include 'components/_campaign-card.html.twig' with {campaign: campaign} %}

{# Inclure un KPI #}
{% include 'components/_card-kpi.html.twig' with {value: 42, label: 'Réalisées', color: 'success'} %}
```

---

## 📐 Conventions de Code

### PHP / Symfony
- **Standard** : PSR-12
- **Entités** : Noms en français (Campagne, Operation, Checklist, Utilisateur)
- **Services** : Suffixe `Service` (CampagneService, ImportService)
- **Controllers** : Singulier (CampagneController, OperationController)
- **Repositories** : Via Doctrine, pas de query dans les controllers
- **Logique métier** : Dans les Services, JAMAIS dans les Controllers

### Nommage des fichiers
```
src/
├── Entity/
│   ├── Campagne.php
│   ├── Operation.php
│   └── Utilisateur.php
├── Service/
│   ├── CampagneService.php
│   └── ImportService.php
├── Controller/
│   ├── CampagneController.php
│   └── TerrainController.php
└── Repository/
    └── CampagneRepository.php

templates/
├── components/           # Composants réutilisables
│   ├── _status-badge.html.twig
│   ├── _campaign-card.html.twig
│   └── _card-kpi.html.twig
├── terrain/              # Vues Karim (mobile)
├── dashboard/            # Vues Sophie
└── reservation/          # Vues Agent/Manager (public)
```

### Base de données
- **Colonnes** : snake_case (date_debut, statut_workflow)
- **JSONB** : Pour les champs dynamiques (custom_fields)
- **Timestamps** : created_at, updated_at sur toutes les entités
- **Soft delete** : Non (archivage explicite via statut)

### Frontend
- **Pas de npm/webpack** — AssetMapper uniquement
- **Stimulus** : Pour interactivité JS
- **Turbo** : Pour navigation SPA-like et streams temps réel
- **CSS** : Tailwind via CDN (dev) puis build (prod)
- **Design System** : OBLIGATOIRE — voir `/design-reference/DESIGN_SYSTEM.md`

### Tests
- **Unitaires** : `tests/Unit/` — Services, Entities
- **Fonctionnels** : `tests/Functional/` — Controllers
- **Fixtures** : `tests/Fixtures/` avec Alice
- **Couverture cible** : 80% sur les Services

---

## 🚫 Interdictions

1. **Ne JAMAIS modifier** les fichiers dans `/docs/` (specs read-only)
2. **Ne JAMAIS ignorer** un test qui casse — le fixer ou documenter dans BLOCKERS
3. **Ne JAMAIS changer** l'architecture sans documenter dans DECISIONS.md
4. **Ne JAMAIS travailler** sur plusieurs tâches en parallèle
5. **Ne JAMAIS commiter** du code qui ne compile pas
6. **Ne JAMAIS utiliser** de dépendances JS complexes (React, Vue, etc.)
7. **Ne JAMAIS inventer** un design — toujours se référer aux mockups
8. **Ne JAMAIS utiliser** border-radius (sauf avatars ronds)

---

## 📚 Documentation de Référence

| Document | Chemin | Usage |
|----------|--------|-------|
| Requirements fonctionnels | `/docs/specs/P4.1-Requirements.md` | User Stories, critères d'acceptance |
| Architecture technique | `/docs/specs/P4.2-Architecture.md` | Choix techniques, NFR |
| Règles métier | `/docs/specs/REFERENTIEL_REGLES_METIER.md` | Toutes les RG-XXX |
| **Design System** | `/design-reference/DESIGN_SYSTEM.md` | **Couleurs, typo, composants** |
| **Mockups** | `/design-reference/mockups/` | **HTML de référence** |
| **Composants Twig** | `/design-reference/twig-components/` | **À copier dans templates/** |

---

## 🔢 Règles Métier Critiques

### Core (MVP/V1)

| Code | Règle | Impact |
|------|-------|--------|
| RG-006 | Verrouillage compte après 5 échecs | Sécurité auth |
| RG-010 | 5 statuts campagne avec transitions | Workflow campagne |
| RG-017 | Transitions statut opération | Workflow opération |
| RG-031 | Snapshot Pattern checklists | Versioning |
| RG-080 | Triple signalisation RGAA | Accessibilité |
| RG-082 | Touch targets 44×44px minimum | Mobile Karim |

### Module Réservation (V2)

| Code | Règle | Impact |
|------|-------|--------|
| RG-120 | Agent ne voit que créneaux de son segment | Filtrage créneaux |
| RG-121 | Un agent = max 1 réservation par campagne | UNIQUE constraint |
| RG-122 | Confirmation automatique email + ICS | Notifications |
| RG-123 | Verrouillage créneaux J-X (défaut J-2) | Modification interdite |
| RG-124 | Manager ne voit que ses agents | Filtrage équipe |
| RG-125 | Traçabilité positionnement (agent/manager/coord) | Audit trail |
| RG-126 | Notification agent si positionné par tiers | Email automatique |
| RG-127 | Alerte si >50% équipe même jour | Dashboard planning |
| RG-130 | Création créneaux manuelle ou auto | Génération plage |
| RG-131 | Capacité IT configurable par créneau | Limite réservations |
| RG-133 | Modification créneau = notification agents | Email si changement |
| RG-134 | Suppression créneau = annulation + notif | Cascade agents |
| RG-135 | Créneaux par segment optionnel | Filtrage optionnel |

---

## 📦 Entités (17 au total)

### Core (11)

| Entité | Description |
|--------|-------------|
| `Utilisateur` | Utilisateur IT (auth, rôles) |
| `Campagne` | Campagne d'opérations |
| `Operation` | Unité de travail terrain |
| `Segment` | Groupement logique d'opérations |
| `TypeOperation` | Catégorie d'opération |
| `ChecklistTemplate` | Modèle de checklist |
| `ChecklistInstance` | Instance d'exécution checklist |
| `Document` | Fichier attaché |
| `HabilitationCampagne` | Droits granulaires |
| `Prerequis` | Tâches préalables |
| `CoordinateurPerimetre` | Périmètre délégation |

### Module Réservation V2 (6)

| Entité | Description | RG |
|--------|-------------|-----|
| `Agent` | Personne métier (matricule, email, service) | RG-121 |
| `Creneau` | Plage horaire réservable | RG-130, RG-131 |
| `Reservation` | Association Agent ↔ Creneau | RG-121, RG-125 |
| `Notification` | Historique emails/SMS | RG-122 |
| `CampagneChamp` | Colonnes dynamiques CSV | RG-015 |
| `CampagneAgentAutorise` | Liste agents mode import | — |

---

## 🎯 Controllers Module Réservation

| Controller | Routes | Persona |
|------------|--------|---------|
| `BookingController` | `/reservation/{token}/*` | Agent (token privé) |
| `PublicBookingController` | `/reservation/c/{token}/*` | Public (Doodle) |
| `ManagerBookingController` | `/manager/campagne/{id}/*` | Manager |
| `CreneauController` | `/campagnes/{id}/creneaux/*` | Sophie (admin) |

---

## 🧪 Commandes Utiles

```bash
# Lancer les tests
php bin/phpunit

# Lancer un test spécifique
php bin/phpunit tests/Unit/Service/CampagneServiceTest.php

# Créer une migration
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Vider le cache
php bin/console cache:clear

# Fixtures
php bin/console doctrine:fixtures:load

# Import agents depuis CSV (V2)
php bin/console app:import-agents fichier.csv [--separator=;] [--update]

# Synchroniser segments depuis colonne CSV (V2)
php bin/console app:sync-segments <campagne_id>
```

---

## 🆘 En cas de Blocage

1. **Erreur de compilation** → Vérifier les imports, namespace
2. **Test qui casse** → Lire le message, vérifier les fixtures
3. **Migration échoue** → Vérifier la cohérence schema/entity
4. **Problème de compréhension spec** → Relire le doc référencé
5. **Question design** → Ouvrir le mockup HTML correspondant
6. **Blocage > 15 min** → Documenter dans `claude/BLOCKERS.md`

---

## ✅ Checklist Commit

Avant chaque commit, vérifier :
- [ ] Les tests passent (`php bin/phpunit`)
- [ ] Le code compile (`php bin/console cache:clear`)
- [ ] Le message suit le format `[T-XX] description`
- [ ] Pas de `dd()`, `dump()`, `var_dump()` oubliés
- [ ] Pas de credentials/secrets hardcodés
- [ ] Le HTML respecte le Design System (si applicable)
