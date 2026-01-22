# CURRENT_TASK.md — Tache en Cours

> **Assigne le** : 2026-01-22
> **Session** : #10 (terminee)

---

## Tache : Sprint 9 — Import CSV & Export (EPIC-02 + EPIC-03 V1) ✅ COMPLETE

**Sprint** : 9 - Import CSV & Export
**Priorite** : V1
**Statut** : ✅ TERMINE

---

## Taches realisees

| ID    | US     | Titre                                           | Statut | RG                  |
| ----- | ------ | ----------------------------------------------- | ------ | ------------------- |
| T-901 | US-203 | Creer campagne — Etape 2/4 (Upload CSV)         | ✅      | RG-012, RG-013      |
| T-902 | US-204 | Creer campagne — Etape 3/4 (Mapping colonnes)   | ✅      | RG-012, RG-014      |
| T-903 | -      | Service ImportCsv (League\Csv)                  | ✅      | RG-012              |
| T-904 | -      | Detection encodage + separateur auto            | ✅      | RG-012              |
| T-905 | -      | Gestion erreurs import (log)                    | ✅      | RG-092              |
| T-906 | US-307 | Exporter les operations (CSV)                   | ✅      | -                   |
| T-907 | US-308 | Rechercher une operation (globale)              | ✅      | -                   |
| T-908 | -      | Tests ImportService                             | ✅      | -                   |

---

## Fichiers crees/modifies

### Services
- `src/Service/ImportCsvService.php` — Import CSV avec League\Csv
- `src/Service/ImportResult.php` — Resultat d'import avec erreurs
- `src/Service/ExportCsvService.php` — Export CSV des operations

### Formulaires
- `src/Form/CampagneStep2Type.php` — Upload fichier CSV
- `src/Form/CampagneStep3Type.php` — Mapping colonnes dynamique

### Controllers
- `src/Controller/CampagneController.php` — Routes step2, step3, export
- `src/Controller/SearchController.php` — Recherche globale

### Repository
- `src/Repository/OperationRepository.php` — Methode searchGlobal()

### Templates
- `templates/campagne/step2.html.twig` — Upload CSV (Bauhaus)
- `templates/campagne/step3.html.twig` — Mapping avec apercu
- `templates/search/index.html.twig` — Resultats recherche

### Tests
- `tests/Unit/Service/ImportCsvServiceTest.php` — 24 tests, 56 assertions

---

## Prochaine tache : Sprint 10 — Gestion Utilisateurs V1 + Documents

| ID     | US     | Titre                              | Statut | RG     | Priorite |
| ------ | ------ | ---------------------------------- | ------ | ------ | -------- |
| T-1001 | US-104 | Modifier un utilisateur (Admin)    | ⏳      | RG-004 | 🟡 V1    |
| T-1002 | US-105 | Desactiver un utilisateur (Admin)  | ⏳      | RG-005 | 🟡 V1    |
| T-1003 | US-106 | Voir les statistiques utilisateur  | ⏳      | -      | 🟡 V1    |
| T-1004 | US-107 | Modifier son propre mot de passe   | ⏳      | RG-001 | 🟡 V1    |
| T-1005 | US-701 | Voir la liste des documents        | ⏳      | -      | 🟡 V1    |
| T-1006 | US-702 | Uploader un document (50Mo max)    | ⏳      | RG-050 | 🟡 V1    |
| T-1007 | US-703 | Lier un document a une campagne    | ⏳      | RG-051 | 🟡 V1    |
| T-1008 | US-704 | Supprimer un document              | ⏳      | -      | 🟡 V1    |

---

## Tests

```bash
# Tous les tests passent
php bin/phpunit
# OK, Tests: 148, Assertions: 476
```
