# CLAUDE.md — Instructions pour Claude Code

> **Projet** : OpsTracker  
> **Version** : 1.0 MVP  
> **Dernière mise à jour** : 2026-01-22

---

## 🎯 Contexte Projet

**OpsTracker** est une application Symfony de gestion d'opérations IT terrain pour les CPAM (Assurance Maladie). Elle permet de piloter des campagnes de migration/déploiement avec suivi en temps réel.

**Utilisateurs cibles** :
- **Sophie** (Gestionnaire) : Configure les campagnes, suit les dashboards
- **Karim** (Technicien) : Exécute les interventions terrain avec checklists

**North Star** : "Sophie voit son dashboard se mettre à jour en temps réel pendant que Karim coche ses étapes sur le terrain."

---

## 📋 Protocole de Session

### Au DÉBUT de chaque session
```
1. Lire ce fichier (CLAUDE.md)
2. Lire .claude/PROGRESS.md → état d'avancement
3. Lire .claude/CURRENT_TASK.md → ta mission
4. Vérifier .claude/BLOCKERS.md → points en attente
5. Vérifier que les tests passent : php bin/phpunit
```

### PENDANT la session
- Travailler sur **UNE seule tâche** (celle de CURRENT_TASK.md)
- Commiter fréquemment : `git commit -m "[T-XX] description"`
- Si bloqué > 15 min → documenter dans BLOCKERS.md et passer à la suite
- Si décision architecturale → documenter dans DECISIONS.md

### À la FIN de chaque session
```
1. Mettre à jour .claude/PROGRESS.md (cocher les tâches terminées)
2. Ajouter une entrée dans .claude/SESSION_LOG.md
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
│   └── TerrainController.php      # Pour Karim
└── Repository/
    └── CampagneRepository.php
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
- **Design System** : Voir `/docs/DESIGN_SYSTEM.md`

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

---

## 📚 Documentation de Référence

| Document | Chemin | Usage |
|----------|--------|-------|
| Requirements fonctionnels | `/docs/P4.1-Requirements.md` | User Stories, critères d'acceptance |
| Architecture technique | `/docs/P4.2-Architecture.md` | Choix techniques, NFR |
| Règles métier | `/docs/REFERENTIEL_REGLES_METIER.md` | Toutes les RG-XXX |
| Design System | `/docs/DESIGN_SYSTEM.md` | Couleurs, typo, composants |
| Mockups | `/docs/mockups/` | HTML de référence |

---

## 🔢 Règles Métier Critiques (à connaître)

| Code | Règle | Impact |
|------|-------|--------|
| RG-006 | Verrouillage compte après 5 échecs | Sécurité auth |
| RG-010 | 5 statuts campagne avec transitions auto | Workflow campagne |
| RG-012 | Import CSV max 100 000 lignes | Performance |
| RG-017 | Transitions statut opération | Workflow opération |
| RG-031 | Snapshot Pattern checklists | Versioning |
| RG-080 | Triple signalisation RGAA | Accessibilité |

---

## 🧪 Commandes Utiles

```bash
# Lancer les tests
php bin/phpunit

# Lancer un test spécifique
php bin/phpunit tests/Unit/Service/CampagneServiceTest.php

# Vérifier le code style
php vendor/bin/php-cs-fixer fix --dry-run

# Créer une migration
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Vider le cache
php bin/console cache:clear

# Fixtures
php bin/console doctrine:fixtures:load
```

---

## 🆘 En cas de Blocage

1. **Erreur de compilation** → Vérifier les imports, namespace
2. **Test qui casse** → Lire le message, vérifier les fixtures
3. **Migration échoue** → Vérifier la cohérence schema/entity
4. **Problème de compréhension spec** → Relire le doc référencé dans la tâche
5. **Blocage > 15 min** → Documenter dans `.claude/BLOCKERS.md` et passer à autre chose

---

## ✅ Checklist Commit

Avant chaque commit, vérifier :
- [ ] Les tests passent (`php bin/phpunit`)
- [ ] Le code compile (`php bin/console cache:clear`)
- [ ] Le message suit le format `[T-XX] description`
- [ ] Pas de `dd()`, `dump()`, `var_dump()` oubliés
- [ ] Pas de credentials/secrets hardcodés
