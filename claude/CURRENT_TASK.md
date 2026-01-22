# CURRENT_TASK.md — Tache en Cours

> **Assigne le** : 2026-01-22
> **Session** : #7 (terminee)

---

## Tache : Sprint 6 — Checklists (EPIC-05 MVP) ✅ COMPLETE

**Sprint** : 6 - Checklists
**Priorite** : MVP
**Statut** : ✅ TERMINE

---

## Taches realisees

| ID    | US     | Titre                                      | Statut | RG     |
| ----- | ------ | ------------------------------------------ | ------ | ------ |
| T-601 | US-503 | Creer un template de checklist (Sophie)    | ✅      | RG-030 |
| T-602 | -      | CRUD Templates EasyAdmin                   | ✅      | -      |
| T-603 | US-501 | Cocher une etape de checklist (48x48px)    | ✅      | RG-082 |
| T-604 | US-502 | Voir la progression de la checklist        | ✅      | -      |
| T-605 | -      | Turbo Frames pour update sans reload       | ✅      | -      |
| T-606 | -      | Tests ChecklistService                     | ✅      | -      |

---

## Fichiers crees/modifies

### Service
- `src/Service/ChecklistService.php` — Gestion templates et instances

### Controller
- `src/Controller/Admin/ChecklistTemplateCrudController.php` — CRUD EasyAdmin
- `src/Controller/TerrainController.php` — Route toggleEtape ajoutee

### Templates
- `templates/admin/field/checklist_etapes.html.twig` — Affichage structure template
- `templates/terrain/_checklist.html.twig` — Composant checklist avec Turbo Frame
- `templates/terrain/show.html.twig` — Integration checklist

### Tests
- `tests/Unit/Service/ChecklistServiceTest.php` — 19 tests, 69 assertions

---

## Prochaine tache : Sprint 7 — Dashboard Sophie (EPIC-06 MVP)

| ID    | US     | Titre                                  | Statut | RG                       |
| ----- | ------ | -------------------------------------- | ------ | ------------------------ |
| T-701 | US-601 | Voir le dashboard temps reel           | ⏳      | RG-040, RG-080, RG-081   |
| T-702 | US-602 | Voir la progression par segment        | ⏳      | -                        |
| T-703 | US-607 | Voir le dashboard global multi-campagnes| ⏳     | -                        |
| T-704 | -      | Turbo Streams pour temps reel          | ⏳      | RG-040                   |
| T-705 | -      | Widgets KPI (compteurs statuts)        | ⏳      | -                        |
| T-706 | -      | Tests DashboardController              | ⏳      | -                        |

---

## Tests

```bash
# Tous les tests passent
php bin/phpunit
# OK, Tests: 90, Assertions: 284
```
