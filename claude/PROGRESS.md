> **Dernière mise à jour** : 2026-01-22 (Session #11 - Sprint 10 Complete - Gestion Utilisateurs V1 + Documents)
> **Source** : P4.1 - Backlog & Requirements Fonctionnels  
> **Total** : 85 User Stories | 12 EPICs

---

## 📊 Vue d'Ensemble

|Phase|Sprints|Statut|US|Focus|
|---|---|---|---|---|
|**MVP**|0-8|⏳ À faire|47|Pilote 50 cibles CPAM 92|
|**V1**|9-14|⏳ À faire|29|Déploiement 4 CPAM|
|**V2**|15+|⏳ Backlog|9|Référencement SILL|

---

## 🔴 PHASE MVP — Sprints 0 à 8

### Sprint 0 — Setup & Infrastructure ✅

|ID|Tâche|Statut|Dépendance|
|---|---|---|---|
|T-001|Créer projet Symfony 7.4 (`--webapp`)|✅|-|
|T-002|Docker : PHP 8.3 + PostgreSQL 17 + Redis|✅|T-001|
|T-003|Configurer AssetMapper + Tailwind CDN|✅|T-001|
|T-004|Installer EasyAdmin 4.x|✅|T-001|
|T-005|Installer Symfony Workflow + UX Turbo|✅|T-001|
|T-006|Configurer PHPUnit + premier test|✅|T-001|
|T-007|Créer structure .claude/ (pilotage)|✅|-|

---

### Sprint 1 — Authentification & Utilisateurs (EPIC-01) ✅

| ID    | US     | Titre                                                | Statut | RG             | Priorité |
| ----- | ------ | ---------------------------------------------------- | ------ | -------------- | -------- |
| T-101 | -      | Entité `Utilisateur` (email, password, rôles, actif) | ✅      | RG-002, RG-003 | MVP      |
| T-102 | US-101 | Se connecter à l'application                         | ✅      | RG-001, RG-006 | 🔴 MVP   |
| T-103 | US-102 | Se déconnecter                                       | ✅      | -              | 🔴 MVP   |
| T-104 | US-103 | Créer un compte utilisateur (Admin)                  | ✅      | RG-002, RG-003 | 🔴 MVP   |
| T-105 | -      | Verrouillage compte après 5 échecs                   | ✅      | RG-006         | MVP      |
| T-106 | -      | CRUD Utilisateurs EasyAdmin                          | ✅      | -              | MVP      |
| T-107 | -      | Tests UtilisateurService                             | ✅      | -              | MVP      |

---

### Sprint 2 — Modèle de Données Core ✅

|ID|Tâche|Statut|Entité|RG|
|---|---|---|---|---|
|T-201|Entité `Campagne` (nom, dates, description, statut)|✅|Campagne|RG-010, RG-011|
|T-202|Entité `TypeOperation` (nom, icône, couleur)|✅|TypeOperation|RG-060|
|T-203|Entité `Segment` (nom, couleur, campagne)|✅|Segment|-|
|T-204|Entité `Operation` (matricule, nom, statut, données JSONB)|✅|Operation|RG-014, RG-015|
|T-205|Entité `ChecklistTemplate` (nom, version, étapes JSON)|✅|ChecklistTemplate|RG-030|
|T-206|Entité `ChecklistInstance` (snapshot, progression)|✅|ChecklistInstance|RG-031|
|T-207|Relations + Migrations|✅|-|-|
|T-208|Workflow Campagne (5 statuts)|✅|-|RG-010|
|T-209|Workflow Opération (6 statuts)|✅|-|RG-017|

---

### Sprint 3 — Campagnes CRUD (EPIC-02 MVP) ✅

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-301|US-201|Voir la liste des campagnes (groupée par statut)|✅|RG-010|🔴 MVP|
|T-302|US-202|Créer campagne — Étape 1/4 (Infos générales)|✅|RG-011|🔴 MVP|
|T-303|US-205|Créer campagne — Étape 4/4 (Workflow & Template)|✅|RG-014|🔴 MVP|
|T-304|US-206|Ajouter une opération manuellement|✅|RG-014, RG-015|🔴 MVP|
|T-305|US-801|Créer un type d'opération (config EasyAdmin)|✅|RG-060|🔴 MVP|
|T-306|-|CRUD Campagne EasyAdmin|✅|-|MVP|
|T-307|-|Tests CampagneService|✅|-|MVP|

---

### Sprint 4 — Opérations & Segments (EPIC-03 + EPIC-09 MVP) ✅

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-401|US-301|Voir la liste des opérations (vue tableau)|✅|RG-080|🔴 MVP|
|T-402|US-303|Filtrer les opérations|✅|-|🔴 MVP|
|T-403|US-304|Modifier le statut d'une opération (inline)|✅|RG-017, RG-080|🔴 MVP|
|T-404|US-306|Assigner un technicien à une opération|✅|RG-018|🔴 MVP|
|T-405|US-905|Créer/modifier des segments|✅|-|🔴 MVP|
|T-406|US-906|Voir la progression par segment (détail)|✅|-|🔴 MVP|
|T-407|-|Tests OperationService|✅|-|MVP|

---

### Sprint 5 — Interface Terrain Karim (EPIC-04) ✅

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-501|-|Layout mobile responsive (Twig base)|✅|RG-082|MVP|
|T-502|US-401|Voir "Mes interventions" (vue filtrée)|✅|RG-020, RG-080, RG-082|🔴 MVP|
|T-503|US-402|Ouvrir le détail d'une intervention|✅|-|🔴 MVP|
|T-504|US-403|Changer le statut en 1 clic (56px buttons)|✅|RG-017, RG-021, RG-082|🔴 MVP|
|T-505|US-404|Retour automatique après action|✅|-|🔴 MVP|
|T-506|-|Tests TerrainController (OperationVoter)|✅|-|MVP|

---

### Sprint 6 — Checklists (EPIC-05 MVP) ✅

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-601|US-503|Créer un template de checklist (Sophie)|✅|RG-030|🔴 MVP|
|T-602|-|CRUD Templates EasyAdmin|✅|-|MVP|
|T-603|US-501|Cocher une étape de checklist (48x48px)|✅|RG-082|🔴 MVP|
|T-604|US-502|Voir la progression de la checklist|✅|-|🔴 MVP|
|T-605|-|Turbo Frames pour update sans reload|✅|-|MVP|
|T-606|-|Tests ChecklistService|✅|-|MVP|

---

### Sprint 7 — Dashboard Sophie (EPIC-06 MVP) ✅

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-701|US-601|Voir le dashboard temps réel|✅|RG-040, RG-080, RG-081|🔴 MVP|
|T-702|US-602|Voir la progression par segment|✅|-|🔴 MVP|
|T-703|US-607|Voir le dashboard global multi-campagnes|✅|-|🔴 MVP|
|T-704|-|Turbo Streams pour temps réel|✅|RG-040|MVP|
|T-705|-|Widgets KPI (compteurs statuts)|✅|-|MVP|
|T-706|-|Tests DashboardService|✅|-|MVP|

---

### Sprint 8 — Tests & Polish MVP ✅

|ID|Tâche|Statut|Cible|
|---|---|---|---|
|T-801|Fixtures de démo (Faker)|✅|3 campagnes, 150 ops|
|T-802|Audit accessibilité RGAA|✅|RG-080 à RG-085|
|T-803|Corrections accessibilité|✅|Score 100%|
|T-804|Tests E2E parcours critique|✅|14 tests, 21 assertions|
|T-805|Test de charge basique|✅|10 users, documentation|
|T-806|Documentation déploiement Docker|✅|README.md|
|T-807|**🏁 TAG v0.1.0-mvp**|✅|-|

---

## 🟡 PHASE V1 — Sprints 9 à 14

### Sprint 9 — Import CSV & Export (EPIC-02 + EPIC-03 V1) ✅

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-901|US-203|Créer campagne — Étape 2/4 (Upload CSV)|✅|RG-012, RG-013|🟡 V1|
|T-902|US-204|Créer campagne — Étape 3/4 (Mapping colonnes)|✅|RG-012, RG-014|🟡 V1|
|T-903|-|Service ImportCsv (League\Csv)|✅|RG-012|V1|
|T-904|-|Détection encodage + séparateur auto|✅|RG-012|V1|
|T-905|-|Gestion erreurs import (log)|✅|RG-092|V1|
|T-906|US-307|Exporter les opérations (CSV)|✅|-|🟡 V1|
|T-907|US-308|Rechercher une opération (globale)|✅|-|🟡 V1|
|T-908|-|Tests ImportService|✅|-|V1|

---

### Sprint 10 — Gestion Utilisateurs V1 + Documents (EPIC-01 + EPIC-07) ✅

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-1001|US-104|Modifier un utilisateur (Admin)|✅|RG-004|🟡 V1|
|T-1002|US-105|Désactiver un utilisateur (Admin)|✅|RG-005|🟡 V1|
|T-1003|US-106|Voir les statistiques utilisateur|✅|-|🟡 V1|
|T-1004|US-107|Modifier son propre mot de passe|✅|RG-001|🟡 V1|
|T-1005|US-701|Voir la liste des documents|✅|-|🟡 V1|
|T-1006|US-702|Uploader un document (50Mo max)|✅|RG-050|🟡 V1|
|T-1007|US-703|Lier un document à une campagne|✅|RG-051|🟡 V1|
|T-1008|US-704|Supprimer un document|✅|-|🟡 V1|

---

### Sprint 11 — Campagnes & Checklists V1 (EPIC-02 + EPIC-05)

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-1101|US-207|Archiver/Désarchiver une campagne|⏳|RG-016|🟡 V1|
|T-1102|US-210|Définir le propriétaire d'une campagne|⏳|RG-111|🟡 V1|
|T-1103|US-211|Configurer la visibilité d'une campagne|⏳|RG-112|🟡 V1|
|T-1104|US-504|Modifier un template avec versioning|⏳|RG-031|🟡 V1|
|T-1105|US-505|Créer des phases dans un template|⏳|RG-032|🟡 V1|
|T-1106|US-506|Consulter un document depuis checklist|⏳|-|🟡 V1|
|T-1107|US-507|Télécharger un script depuis checklist|⏳|-|🟡 V1|

---

### Sprint 12 — Configuration & Admin (EPIC-08 V1)

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-1201|US-802|Définir les champs personnalisés|⏳|RG-061, RG-015|🟡 V1|
|T-1202|US-804|Voir l'historique des modifications (Audit)|⏳|RG-070|🟡 V1|
|T-1203|US-806|Exporter/Importer la configuration|⏳|RG-100|🟡 V1|
|T-1204|US-807|Créer un profil "Coordinateur"|⏳|RG-114|🟡 V1|
|T-1205|US-808|Gérer les habilitations par campagne|⏳|RG-115|🟡 V1|
|T-1206|-|Installer auditor-bundle|⏳|RG-070|V1|

---

### Sprint 13 — Prérequis & Dashboard V1 (EPIC-09 + EPIC-06)

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-1301|US-901|Voir les prérequis globaux d'une campagne|⏳|RG-090|🟡 V1|
|T-1302|US-902|Ajouter/modifier un prérequis global|⏳|RG-090|🟡 V1|
|T-1303|US-903|Voir les prérequis par segment|⏳|RG-091|🟡 V1|
|T-1304|US-904|Ajouter un prérequis par segment|⏳|RG-091|🟡 V1|
|T-1305|US-604|Exporter le dashboard en PDF|⏳|-|🟡 V1|
|T-1306|US-605|Partager une URL lecture seule|⏳|RG-041|🟡 V1|
|T-1307|US-608|Filtrer le dashboard global par statut|⏳|-|🟡 V1|

---

### Sprint 14 — Polish V1 & Tag

|ID|Tâche|Statut|Cible|
|---|---|---|---|
|T-1401|Compléter couverture tests (80%)|⏳|Services|
|T-1402|Test de charge V1|⏳|50 users, 10k ops|
|T-1403|Audit sécurité (OWASP basics)|⏳|-|
|T-1404|Documentation utilisateur|⏳|Guide Sophie + Karim|
|T-1405|**🏁 TAG v1.0.0**|⏳|-|

---

## 🟢 PHASE V2 — Backlog (Post-V1)

### Réservation End-Users (EPIC-10)

|US|Titre|Priorité|
|---|---|---|
|US-1001|Voir les créneaux disponibles (Agent)|🔴 MVP*|
|US-1002|Se positionner sur un créneau (Agent)|🔴 MVP*|
|US-1003|Annuler/modifier son créneau (Agent)|🔴 MVP*|
|US-1004|Voir mon récapitulatif (Agent)|🟡 V1|
|US-1005|Voir la liste de mes agents (Manager)|🔴 MVP*|
|US-1006|Positionner un agent (Manager)|🔴 MVP*|
|US-1007|Modifier/annuler le créneau d'un agent|🔴 MVP*|
|US-1008|Voir les créneaux avec répartition équipe|🟡 V1|
|US-1009|Recevoir notification agents non positionnés|🟢 V2|
|US-1010|Positionner des agents (Coordinateur)|🟡 V1|
|US-1011|S'authentifier par carte agent|🟡 V1|
|US-1012|Voir les informations de l'intervention|🟢 V2|

_* MVP = MVP du module Réservation, pas du MVP OpsTracker core_

### Gestion Créneaux (EPIC-11)

|US|Titre|Priorité|
|---|---|---|
|US-1101|Créer des créneaux pour une campagne|🔴 MVP*|
|US-1102|Définir la capacité IT (ressources)|🟡 V1|
|US-1103|Définir la durée d'intervention (abaques)|🟡 V1|
|US-1104|Modifier un créneau|🔴 MVP*|
|US-1105|Supprimer un créneau|🔴 MVP*|
|US-1106|Voir le taux de remplissage|🔴 MVP*|
|US-1107|Définir une date de verrouillage|🟡 V1|
|US-1108|Associer créneaux à segments/sites|🟡 V1|

### Notifications (EPIC-12)

|US|Titre|Priorité|
|---|---|---|
|US-1201|Envoyer email confirmation avec ICS|🟡 V1|
|US-1202|Envoyer email rappel (J-2)|🟡 V1|
|US-1203|Envoyer email modification|🟡 V1|
|US-1204|Envoyer email annulation|🟡 V1|
|US-1205|Envoyer invitation initiale aux agents|🔴 MVP*|
|US-1206|Configurer paramètres notification|🟢 V2|

### Autres V2

|US|Titre|Priorité|
|---|---|---|
|US-208|Dupliquer une campagne|🟢 V2|
|US-302|Vue cards des opérations|🟡 V1|
|US-305|Trier les colonnes du tableau|🟡 V1|
|US-309|Supprimer une opération|🟡 V1|
|US-508|Donner feedback sur un document|🟢 V2|
|US-603|Voir la vélocité|🟢 V2|
|US-606|Accéder à l'aide contextuelle|🟢 V2|
|US-705|Voir métriques utilisation document|🟢 V2|
|US-803|Configurer un workflow (V2)|🟢 V2|
|US-805|Dupliquer un type d'opération|🟢 V2|

---

## 📈 Métriques

|Métrique|Actuel|Cible MVP|Cible V1|
|---|---|---|---|
|Tâches terminées|78|65|110|
|User Stories done|36/85|47/85|76/85|
|Entités créées|8|6|8|
|Tests passants|167|60+|100+|
|Couverture code|~78%|70%|80%|

---

## 🏷️ Légende

|Symbole|Signification|
|---|---|
|⏳|À faire|
|🔄|En cours|
|✅|Terminé|
|❌|Bloqué|
|🔴|MUST (MVP)|
|🟡|SHOULD (V1)|
|🟢|COULD (V2)|

---

## 📋 Résumé par Sprint

|Sprint|Tâches|US|Focus|
|---|---|---|---|
|0|7|-|Setup Symfony + Docker|
|1|7|3|Auth & Users|
|2|9|-|Entités + Workflows|
|3|7|5|Campagnes CRUD|
|4|7|6|Opérations + Segments|
|5|6|4|Interface Karim|
|6|6|3|Checklists|
|7|6|3|Dashboard|
|8|7|-|Tests & Tag MVP|
|**MVP**|**62**|**24**|**v0.1.0**|
|9|8|4|Import CSV|
|10|8|8|Users V1 + Docs|
|11|7|7|Campagnes V1|
|12|6|5|Config & Admin|
|13|7|7|Prérequis + Dashboard|
|14|5|-|Polish & Tag V1|
|**V1**|**41**|**31**|**v1.0.0**|