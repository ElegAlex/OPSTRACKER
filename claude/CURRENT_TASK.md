# CURRENT_TASK.md — Tache en Cours

> **Assigne le** : 2026-01-22
> **Session** : #8 (terminee)

---

## Tache : Sprint 7 — Dashboard Sophie (EPIC-06 MVP) ✅ COMPLETE

**Sprint** : 7 - Dashboard Sophie
**Priorite** : MVP
**Statut** : ✅ TERMINE

---

## Taches realisees

| ID    | US     | Titre                                      | Statut | RG                       |
| ----- | ------ | ------------------------------------------ | ------ | ------------------------ |
| T-701 | US-601 | Voir le dashboard temps reel               | ✅      | RG-040, RG-080, RG-081   |
| T-702 | US-602 | Voir la progression par segment            | ✅      | -                        |
| T-703 | US-607 | Voir le dashboard global multi-campagnes   | ✅      | -                        |
| T-704 | -      | Turbo Streams pour temps reel              | ✅      | RG-040                   |
| T-705 | -      | Widgets KPI (compteurs statuts)            | ✅      | -                        |
| T-706 | -      | Tests DashboardService                     | ✅      | -                        |

---

## Fichiers crees/modifies

### Service
- `src/Service/DashboardService.php` — KPIs, progression segments, equipe, activite

### Controller
- `src/Controller/DashboardController.php` — Routes dashboard campagne/global/refresh

### Templates
- `templates/dashboard/campagne.html.twig` — Dashboard principal d'une campagne
- `templates/dashboard/global.html.twig` — Vue multi-campagnes
- `templates/dashboard/segment.html.twig` — Detail d'un segment
- `templates/dashboard/_segments.html.twig` — Composant liste segments
- `templates/dashboard/_activite.html.twig` — Composant activite recente
- `templates/dashboard/_equipe.html.twig` — Composant equipe assignee
- `templates/dashboard/_widget_kpi.html.twig` — Widget KPI individuel
- `templates/dashboard/_turbo_refresh.html.twig` — Turbo Stream refresh

### Tests
- `tests/Unit/Service/DashboardServiceTest.php` — 12 tests, 95 assertions

---

## Prochaine tache : Sprint 8 — Tests & Polish MVP

| ID    | Tache                                    | Statut | Cible                          |
| ----- | ---------------------------------------- | ------ | ------------------------------ |
| T-801 | Fixtures de demo (Alice/Faker)           | ⏳      | 3 campagnes, 150 ops           |
| T-802 | Audit accessibilite RGAA (axe-core)      | ⏳      | RG-080 a RG-085                |
| T-803 | Corrections accessibilite                | ⏳      | Score > 90%                    |
| T-804 | Tests E2E parcours critique              | ⏳      | Login -> Checklist -> Dashboard|
| T-805 | Test de charge basique                   | ⏳      | 10 users simultanes            |
| T-806 | Documentation deploiement Docker         | ⏳      | README.md                      |
| T-807 | **TAG v0.1.0-mvp**                        | ⏳      | -                              |

---

## Tests

```bash
# Tous les tests passent
php bin/phpunit
# OK, Tests: 102, Assertions: 379
```
