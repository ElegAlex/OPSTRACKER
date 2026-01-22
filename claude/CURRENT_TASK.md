# CURRENT_TASK.md — Tache en Cours

> **Assigne le** : 2026-01-22
> **Session** : #4 (terminee)

---

## Tache : Sprint 3 — Campagnes CRUD (EPIC-02 MVP) ✅ COMPLETE

**Sprint** : 3 - Campagnes CRUD
**Priorite** : MVP
**Statut** : ✅ TERMINE

---

## Taches realisees

| ID    | US     | Titre                                              | Statut | RG             |
| ----- | ------ | -------------------------------------------------- | ------ | -------------- |
| T-301 | US-201 | Voir la liste des campagnes (groupee par statut)   | ✅      | RG-010         |
| T-302 | US-202 | Creer campagne — Etape 1/4 (Infos generales)       | ✅      | RG-011         |
| T-303 | US-205 | Creer campagne — Etape 4/4 (Workflow & Template)   | ✅      | RG-014         |
| T-304 | US-206 | Ajouter une operation manuellement                 | ✅      | RG-014, RG-015 |
| T-305 | US-801 | Creer un type d'operation (config EasyAdmin)       | ✅      | RG-060         |
| T-306 | -      | CRUD Campagne EasyAdmin                            | ✅      | -              |
| T-307 | -      | Tests CampagneService                              | ✅      | -              |

---

## Fichiers crees/modifies

### Services
- `src/Service/CampagneService.php` — Logique metier campagnes

### Controllers
- `src/Controller/CampagneController.php` — Routes portfolio, creation, operations
- `src/Controller/Admin/CampagneCrudController.php` — CRUD EasyAdmin
- `src/Controller/Admin/TypeOperationCrudController.php` — CRUD EasyAdmin
- `src/Controller/Admin/DashboardController.php` — MAJ menu

### Formulaires
- `src/Form/CampagneStep1Type.php` — Etape 1 creation
- `src/Form/CampagneStep4Type.php` — Etape 4 configuration
- `src/Form/OperationType.php` — Ajout operation manuelle

### Templates
- `templates/campagne/_layout.html.twig` — Layout sidebar
- `templates/campagne/index.html.twig` — Portfolio campagnes
- `templates/campagne/new.html.twig` — Formulaire etape 1
- `templates/campagne/step4.html.twig` — Formulaire etape 4
- `templates/campagne/show.html.twig` — Detail campagne
- `templates/campagne/operation_new.html.twig` — Ajout operation
- `templates/campagne/_card.html.twig` — Card campagne active
- `templates/campagne/_card_compact.html.twig` — Card terminee
- `templates/campagne/_card_archived.html.twig` — Card archivee

### Tests
- `tests/Unit/Service/CampagneServiceTest.php` — 9 tests, 47 assertions

---

## Prochaine tache : Sprint 4 — Operations & Segments

| ID    | US     | Titre                                          | Statut | RG             |
| ----- | ------ | ---------------------------------------------- | ------ | -------------- |
| T-401 | US-301 | Voir la liste des operations (vue tableau)     | ⏳      | RG-080         |
| T-402 | US-303 | Filtrer les operations                         | ⏳      | -              |
| T-403 | US-304 | Modifier le statut d'une operation (inline)    | ⏳      | RG-017, RG-080 |
| T-404 | US-306 | Assigner un technicien a une operation         | ⏳      | RG-018         |
| T-405 | US-905 | Creer/modifier des segments                    | ⏳      | -              |
| T-406 | US-906 | Voir la progression par segment (detail)       | ⏳      | -              |
| T-407 | -      | Tests OperationService                         | ⏳      | -              |

---

## Tests

```bash
# Tous les tests passent
php bin/phpunit
# OK, Tests: 43, Assertions: 126
```
