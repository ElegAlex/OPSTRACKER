# CURRENT_TASK.md — Tache en Cours

> **Assigne le** : 2026-01-22
> **Session** : #11 (terminee)

---

## Tache : Sprint 10 — Gestion Utilisateurs V1 + Documents (EPIC-01 + EPIC-07) ✅ COMPLETE

**Sprint** : 10 - Gestion Utilisateurs V1 + Documents
**Priorite** : V1
**Statut** : ✅ TERMINE

---

## Taches realisees

| ID     | US     | Titre                              | Statut | RG     |
| ------ | ------ | ---------------------------------- | ------ | ------ |
| T-1001 | US-104 | Modifier un utilisateur (Admin)    | ✅      | RG-004 |
| T-1002 | US-105 | Desactiver un utilisateur (Admin)  | ✅      | RG-005 |
| T-1003 | US-106 | Voir les statistiques utilisateur  | ✅      | -      |
| T-1004 | US-107 | Modifier son propre mot de passe   | ✅      | RG-001 |
| T-1005 | US-701 | Voir la liste des documents        | ✅      | -      |
| T-1006 | US-702 | Uploader un document (50Mo max)    | ✅      | RG-050 |
| T-1007 | US-703 | Lier un document a une campagne    | ✅      | RG-051 |
| T-1008 | US-704 | Supprimer un document              | ✅      | -      |

---

## Fichiers crees/modifies

### Entites
- `src/Entity/Document.php` — Entite Document (RG-050, RG-051)

### Repositories
- `src/Repository/DocumentRepository.php` — Requetes documents
- `src/Repository/OperationRepository.php` — Ajout methodes statistiques technicien

### Services
- `src/Service/DocumentService.php` — Upload, suppression, statistiques
- `src/Service/UtilisateurService.php` — Ajout getStatistiques, updateProfile, updateRoles

### Controllers
- `src/Controller/DocumentController.php` — CRUD documents
- `src/Controller/ProfileController.php` — Page profil et changement mot de passe
- `src/Controller/Admin/UtilisateurCrudController.php` — Actions toggle, unlock, stats

### Formulaires
- `src/Form/DocumentUploadType.php` — Upload fichier (RG-050)
- `src/Form/ChangePasswordType.php` — Changement mot de passe (RG-001)

### Templates
- `templates/document/index.html.twig` — Liste documents
- `templates/document/upload.html.twig` — Upload document
- `templates/profile/index.html.twig` — Page profil
- `templates/profile/password.html.twig` — Changement mot de passe
- `templates/admin/utilisateur/stats.html.twig` — Statistiques utilisateur

### Tests
- `tests/Unit/Service/DocumentServiceTest.php` — 19 tests, 48 assertions

---

## Regles metier implementees

- **RG-001** : Mot de passe securise (8 car, 1 maj, 1 chiffre, 1 special)
- **RG-004** : Un admin ne peut pas retrograder son propre role
- **RG-005** : Desactivation conserve l'historique
- **RG-050** : Formats documents (PDF, DOCX, PS1, BAT, ZIP, EXE), taille max 50 Mo
- **RG-051** : Tout document doit etre associe a une campagne

---

## Prochaine tache : Sprint 11 — Campagnes & Checklists V1

| ID     | US     | Titre                              | Statut | RG     | Priorite |
| ------ | ------ | ---------------------------------- | ------ | ------ | -------- |
| T-1101 | US-207 | Archiver/Desarchiver une campagne  | ⏳      | RG-016 | 🟡 V1    |
| T-1102 | US-210 | Definir le proprietaire            | ⏳      | RG-111 | 🟡 V1    |
| T-1103 | US-211 | Configurer la visibilite           | ⏳      | RG-112 | 🟡 V1    |
| T-1104 | US-504 | Modifier un template (versioning)  | ⏳      | RG-031 | 🟡 V1    |
| T-1105 | US-505 | Creer des phases dans un template  | ⏳      | RG-032 | 🟡 V1    |
| T-1106 | US-506 | Consulter document depuis checklist| ⏳      | -      | 🟡 V1    |
| T-1107 | US-507 | Telecharger script depuis checklist| ⏳      | -      | 🟡 V1    |

---

## Tests

```bash
# Tous les tests passent
php bin/phpunit
# OK, Tests: 167, Assertions: 524
```
