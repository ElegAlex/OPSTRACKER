# CURRENT_TASK.md — Tache en Cours

> **Assigne le** : 2026-01-22
> **Session** : #14 (terminee)

---

## Tache : Sprint 13 — Prerequis & Dashboard V1 (EPIC-09 + EPIC-06) ✅ COMPLETE

**Sprint** : 13 - Prerequis & Dashboard V1
**Priorite** : V1
**Statut** : ✅ TERMINE

---

## Taches realisees

| ID     | US     | Titre                                       | Statut | RG     |
| ------ | ------ | ------------------------------------------- | ------ | ------ |
| T-1301 | US-901 | Voir les prerequis globaux d'une campagne   | ✅      | RG-090 |
| T-1302 | US-902 | Ajouter/modifier un prerequis global        | ✅      | RG-090 |
| T-1303 | US-903 | Voir les prerequis par segment              | ✅      | RG-091 |
| T-1304 | US-904 | Ajouter un prerequis par segment            | ✅      | RG-091 |
| T-1305 | US-604 | Exporter le dashboard en PDF                | ✅      | -      |
| T-1306 | US-605 | Partager une URL lecture seule              | ✅      | RG-041 |
| T-1307 | US-608 | Filtrer le dashboard global par statut      | ✅      | -      |

---

## Fichiers crees/modifies

### Entites
- `src/Entity/Prerequis.php` — Entite Prerequis (RG-090, RG-091)
- `src/Entity/Campagne.php` — Ajout shareToken, shareTokenCreatedAt (RG-041)

### Repositories
- `src/Repository/PrerequisRepository.php` — Requetes prerequis globaux/segment
- `src/Repository/CampagneRepository.php` — Ajout findOneByShareToken, findByStatuts

### Services
- `src/Service/PrerequisService.php` — CRUD prerequis, progression
- `src/Service/PdfExportService.php` — Export PDF dashboard (dompdf)
- `src/Service/ShareService.php` — Gestion liens de partage
- `src/Service/DashboardService.php` — Ajout filtrage par statut

### Controllers
- `src/Controller/PrerequisController.php` — CRUD prerequis, changement statut inline
- `src/Controller/DashboardController.php` — Export PDF, filtrage global
- `src/Controller/ShareController.php` — Liens de partage lecture seule

### Formulaires
- `src/Form/PrerequisType.php` — Formulaire prerequis

### Templates
- `templates/prerequis/index.html.twig` — Liste prerequis globaux + par segment
- `templates/prerequis/new_global.html.twig` — Ajout prerequis global
- `templates/prerequis/new_segment.html.twig` — Ajout prerequis segment
- `templates/prerequis/edit.html.twig` — Edition prerequis
- `templates/prerequis/_row.html.twig` — Ligne prerequis (Turbo)
- `templates/prerequis/_turbo_statut.html.twig` — Update statut inline
- `templates/pdf/dashboard.html.twig` — Template PDF A4 paysage
- `templates/share/dashboard.html.twig` — Dashboard lecture seule
- `templates/share/_modal.html.twig` — Modal partage
- `templates/dashboard/campagne.html.twig` — Ajout boutons PDF, partage, onglet prerequis
- `templates/dashboard/global.html.twig` — Ajout filtres par statut

### Tests
- `tests/Unit/Service/PrerequisServiceTest.php` — 11 tests, 48 assertions

### Migrations
- `migrations/Version20260122213209.php` — Table prerequis
- `migrations/Version20260122213720.php` — Colonnes share_token sur campagne

---

## Regles metier implementees

- **RG-090** : Prerequis globaux de campagne (A faire / En cours / Fait) - indicateur declaratif NON bloquant
- **RG-091** : Prerequis specifiques a un segment - indicateur declaratif NON bloquant
- **RG-041** : URLs partagees (/share/xxx) = consultation uniquement, aucune action

---

## Prochaine tache : Sprint 14 — Polish V1 & Tag

| ID     | Tache                              | Statut | Cible             |
| ------ | ---------------------------------- | ------ | ----------------- |
| T-1401 | Completer couverture tests (80%)   | ⏳      | Services          |
| T-1402 | Test de charge V1                  | ⏳      | 50 users, 10k ops |
| T-1403 | Audit securite (OWASP basics)      | ⏳      | -                 |
| T-1404 | Documentation utilisateur          | ⏳      | Guide Sophie      |
| T-1405 | **TAG v1.0.0**                     | ⏳      | -                 |

---

## Tests

```bash
# Tous les tests passent
php bin/phpunit
# OK, Tests: 202, Assertions: 642
```
