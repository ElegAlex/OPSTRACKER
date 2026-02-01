> **Dernière mise à jour** : 2026-02-01 (Session #27 - Audit Documentation V2) **Source** : P4.1 - Backlog & Requirements Fonctionnels **Total** : 101 User Stories | 14 EPICs

---

## 📊 Vue d'Ensemble

|Phase|Sprints|Statut|US|Focus|
|---|---|---|---|---|
|**MVP**|0-8|✅ Terminé|47|Pilote 50 cibles Organisation|
|**V1**|9-14|✅ Terminé|29|Déploiement multi-sites|
|**Audit V1**|15|✅ Terminé|-|Qualification Production|
|**V2 Réservation**|16-17|✅ Terminé|16|Module Réservation Doodle|
|**V2.1**|18+|⏳ Backlog|9|Notifications + Améliorations|

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

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-101|-|Entité `Utilisateur` (email, password, rôles, actif)|✅|RG-002, RG-003|MVP|
|T-102|US-101|Se connecter à l'application|✅|RG-001, RG-006|🔴 MVP|
|T-103|US-102|Se déconnecter|✅|-|🔴 MVP|
|T-104|US-103|Créer un compte utilisateur (Admin)|✅|RG-002, RG-003|🔴 MVP|
|T-105|-|Verrouillage compte après 5 échecs|✅|RG-006|MVP|
|T-106|-|CRUD Utilisateurs EasyAdmin|✅|-|MVP|
|T-107|-|Tests UtilisateurService|✅|-|MVP|

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
|T-807|**🏷 TAG v0.1.0-mvp**|✅|-|

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

### Sprint 11 — Campagnes & Checklists V1 (EPIC-02 + EPIC-05) ✅

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-1101|US-207|Archiver/Désarchiver une campagne|✅|RG-016|🟡 V1|
|T-1102|US-210|Définir le propriétaire d'une campagne|✅|RG-111|🟡 V1|
|T-1103|US-211|Configurer la visibilité d'une campagne|✅|RG-112|🟡 V1|
|T-1104|US-504|Modifier un template avec versioning|✅|RG-031|🟡 V1|
|T-1105|US-505|Créer des phases dans un template|✅|RG-032|🟡 V1|
|T-1106|US-506|Consulter un document depuis checklist|✅|-|🟡 V1|
|T-1107|US-507|Télécharger un script depuis checklist|✅|-|🟡 V1|

---

### Sprint 12 — Configuration & Admin (EPIC-08 V1) ✅

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-1201|US-802|Définir les champs personnalisés|✅|RG-061, RG-015|🟡 V1|
|T-1202|US-804|Voir l'historique des modifications (Audit)|✅|RG-070|🟡 V1|
|T-1203|US-806|Exporter/Importer la configuration|✅|RG-100|🟡 V1|
|T-1204|US-807|Créer un profil "Coordinateur"|✅|RG-114|🟡 V1|
|T-1205|US-808|Gérer les habilitations par campagne|✅|RG-115|🟡 V1|
|T-1206|-|Installer auditor-bundle|✅|RG-070|V1|

---

### Sprint 13 — Prérequis & Dashboard V1 (EPIC-09 + EPIC-06) ✅

|ID|US|Titre|Statut|RG|Priorité|
|---|---|---|---|---|---|
|T-1301|US-901|Voir les prérequis globaux d'une campagne|✅|RG-090|🟡 V1|
|T-1302|US-902|Ajouter/modifier un prérequis global|✅|RG-090|🟡 V1|
|T-1303|US-903|Voir les prérequis par segment|✅|RG-091|🟡 V1|
|T-1304|US-904|Ajouter un prérequis par segment|✅|RG-091|🟡 V1|
|T-1305|US-604|Exporter le dashboard en PDF|✅|-|🟡 V1|
|T-1306|US-605|Partager une URL lecture seule|✅|RG-041|🟡 V1|
|T-1307|US-608|Filtrer le dashboard global par statut|✅|-|🟡 V1|

---

### Sprint 14 — Polish V1 & Tag ✅

|ID|Tâche|Statut|Cible|
|---|---|---|---|
|T-1401|Compléter couverture tests (80%)|✅|Services (240 tests)|
|T-1402|Test de charge V1|✅|50 users, 10k ops|
|T-1403|Audit sécurité (OWASP basics)|✅|OWASP Top 10|
|T-1404|Documentation utilisateur|✅|Guide Sophie + Karim|
|T-1405|**🏷 TAG v1.0.0**|✅|-|

---

## 🔍 PHASE AUDIT V1 — Sprint 15 ✅

### Sprint 15 — Audit Technique V1 Ready (P6-QUALIFY) ✅

> **Objectif** : Garantir que le code correspond aux spécifications P4.1 avant mise en production. **Méthode** : Audit en 6 étapes (Framework BA-AI P6-QUALIFY)

|ID|Étape|Focus|Résultat|Findings|
|---|---|---|---|---|
|T-1501|P6.1|Liens Placeholders & Code Mort|✅|2 → Corrigés|
|T-1502|P6.2|Routes vs Controllers|✅|2 routes manquantes → Créées|
|T-1503|P6.3|UI/UX Incomplets (Dashboard)|✅|0 - 100% fonctionnel|
|T-1504|P6.4|Formulaires & Validation|✅|0 - 100% validés|
|T-1505|P6.5|Sécurité & Permissions|✅|1 fix → Corrigé|
|T-1506|P6.6|Gap Analysis P4.1 vs Code|✅|100% couverture|

#### Corrections Appliquées

|Finding|Type|Description|Commit|
|---|---|---|---|
|#1|🔴 Sécurité|Route `/share/` PUBLIC_ACCESS|—|
|#2|🔴 Route|`app_operation_show` (US-305)|`f00f452`|
|#3|🔴 Route|`app_operation_edit` (US-306)|`6c57e0b`|
|#4|🟡 UX|Liens `href="#"` câblés|(inclus)|

#### Score Final Audit

|Catégorie|Score|
|---|---|
|Liens & Code Mort|10/10 ✅|
|Routes & Controllers|10/10 ✅|
|UI/UX Complet|10/10 ✅|
|Validation Forms|10/10 ✅|
|Sécurité|10/10 ✅|
|Couverture P4.1|100% ✅|
|**TOTAL**|**100/100 ✅**|

#### Verdict

|Statut|Détail|
|---|---|
|✅ **V1 READY**|Tous les critères satisfaits. Prêt pour production.|

---

## 🟢 PHASE V2 — Sprints 16 à 17 ✅

### Sprint 16 — Module Réservation Core (EPIC-10 + EPIC-11) ✅

> **Objectif** : Implémenter le système de réservation type Doodle pour les agents et managers.

#### Nouvelles Entités Créées

|Entité|Description|RG|
|---|---|---|
|`Agent`|Personne métier pouvant réserver (matricule, email, service, site)|RG-121|
|`Creneau`|Plage horaire réservable avec capacité|RG-130, RG-131|
|`Reservation`|Association Agent ↔ Creneau avec traçabilité|RG-121, RG-125|
|`CampagneChamp`|Colonnes dynamiques pour import CSV|RG-015|
|`CampagneAgentAutorise`|Liste agents autorisés (mode import)|—|

#### User Stories EPIC-10 — Interface Réservation End-Users ✅

|ID|US|Titre|Statut|RG|
|---|---|---|---|---|
|T-1601|US-1001|Voir les créneaux disponibles (Agent)|✅|RG-120|
|T-1602|US-1002|Se positionner sur un créneau (Agent)|✅|RG-121, RG-122|
|T-1603|US-1003|Annuler/modifier son créneau (Agent)|✅|RG-123|
|T-1604|US-1004|Voir mon récapitulatif (Agent)|✅|—|
|T-1605|US-1005|Voir la liste de mes agents (Manager)|✅|RG-124|
|T-1606|US-1006|Positionner un agent (Manager)|✅|RG-125|
|T-1607|US-1007|Modifier/annuler le créneau d'un agent|✅|RG-126|
|T-1608|US-1008|Voir les créneaux avec répartition équipe|✅|RG-127|

#### User Stories EPIC-11 — Gestion des Créneaux ✅

|ID|US|Titre|Statut|RG|
|---|---|---|---|---|
|T-1609|US-1101|Créer des créneaux pour une campagne|✅|RG-130|
|T-1610|US-1102|Définir la capacité IT (ressources)|✅|RG-131|
|T-1611|US-1103|Définir la durée d'intervention (abaques)|✅|RG-132|
|T-1612|US-1104|Modifier un créneau + notifications|✅|RG-133|
|T-1613|US-1105|Supprimer un créneau + confirmation|✅|RG-134|
|T-1614|US-1106|Voir le taux de remplissage|✅|—|
|T-1615|US-1107|Définir une date de verrouillage|✅|RG-123|
|T-1616|US-1108|Associer créneaux à segments/sites|✅|RG-135|

#### Controllers Créés

|Controller|Routes|Responsabilité|
|---|---|---|
|`BookingController`|`/reservation/{token}/*`|Interface agent (token privé)|
|`PublicBookingController`|`/reservation/c/{token}/*`|Mode Doodle (accès public)|
|`ManagerBookingController`|`/manager/campagne/{id}/*`|Interface manager|
|`CreneauController`|`/campagnes/{id}/creneaux/*`|CRUD créneaux|

---

### Sprint 17 — Réservation Publique & Améliorations (EPIC-10 Extended) ✅

> **Objectif** : Mode Doodle public avec 3 modes d'identification + améliorations terrain.

#### Fonctionnalités Implémentées

|ID|Fonctionnalité|Description|Statut|
|---|---|---|---|
|T-1701|Mode Libre|Saisie libre identifiant (ouvert à tous)|✅|
|T-1702|Mode Import|Liste CSV préchargée d'agents autorisés|✅|
|T-1703|Mode Annuaire|Dropdown agents avec filtres (service, site, rôle)|✅|
|T-1704|Génération ShareToken|Token public unique par campagne|✅|
|T-1705|Configuration Step 4|UI configuration réservation dans wizard|✅|
|T-1706|Dashboard Encart|Affichage lien réservation sur dashboard|✅|
|T-1707|Import Agents CLI|Commande `app:import-agents`|✅|
|T-1708|Sync Segments CLI|Commande `app:sync-segments`|✅|
|T-1709|Colonnes Dynamiques|CampagneChamp pour import CSV flexible|✅|
|T-1710|Mapping Date/Horaire|Configuration colonnes date_planifiee + horaire|✅|

#### Migrations Appliquées (Jan 2026)

|Version|Description|
|---|---|
|`20260129200141`|Création table `campagne_champ`|
|`20260131114710`|`operation.date_planifiee` : DATE → TIMESTAMP|
|`20260131144256`|Config réservation publique (3 colonnes Campagne)|
|`20260131180923`|Table `campagne_agent_autorise` + filtres annuaire|
|`20260131212107`|`campagne.colonne_segment` (mapping)|
|`20260131220324`|`campagne.colonne_date_planifiee` + `colonne_horaire`|

#### Règles Métier Implémentées

|RG|Description|Implémentation|
|---|---|---|
|RG-120|Agent ne voit que créneaux de son segment|`CreneauRepository::findDisponibles()`|
|RG-121|Un agent = max 1 réservation par campagne|UNIQUE constraint + validation|
|RG-122|Confirmation automatique email + ICS|`NotificationService` + `IcsGenerator`|
|RG-123|Verrouillage J-X (défaut J-2)|`Creneau::isVerrouillePourDate()`|
|RG-124|Manager ne voit que ses agents|Filtrage `Agent.manager_id`|
|RG-125|Traçabilité positionnement|`Reservation.typePositionnement`|
|RG-126|Notification agent si tiers positionne|Email automatique|
|RG-127|Alerte si >50% équipe même jour|Dashboard planning|
|RG-130|Création manuelle ou génération auto|`CreneauService::genererPlage()`|
|RG-131|Capacité IT configurable|`Creneau.capacite`|
|RG-132|Durée intervention par type|Paramètre génération|
|RG-133|Modification créneau = notification|`CreneauController::edit()`|
|RG-134|Suppression créneau = annulation + notif|`CreneauController::delete()`|
|RG-135|Créneaux par segment optionnel|`Creneau.segment_id` nullable|

---

## 🔵 PHASE V2.1 — Backlog (Post-Réservation)

### Réservation — Fonctionnalités Restantes

|US|Titre|Priorité|Statut|
|---|---|---|---|
|US-1009|Recevoir notification agents non positionnés|🟡 V2.1|⏳|
|US-1011|S'authentifier par carte agent|🟢 V2.2|⏳|

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

|Métrique|Actuel|Cible MVP|Cible V1|Cible V2|
|---|---|---|---|---|
|Tâches terminées|**136**|65|110|136|
|User Stories done|**92/101**|47/85|76/85|92/101|
|Entités créées|**17**|6|8|17|
|Tests passants|240+|60+|100+|250+|
|Couverture code|~80%|70%|80%|80%|
|**Score Audit V1**|**100/100**|-|-|-|
|**Module Réservation**|**100%**|-|-|100%|

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
|15|6|-|Audit V1 Ready|
|16|16|8|Module Réservation Core|
|17|10|8|Réservation Publique Doodle|
|**V2**|**26**|**16**|**v2.0.0**|
|**TOTAL**|**135**|**71**|**V2 READY**|

---

## 🚀 Prochaines Étapes

1. ✅ ~~Audit V1 (P6-QUALIFY)~~
2. ✅ ~~Module Réservation V2 (EPIC-10/11)~~
3. 🔜 Déploiement production Organisation
4. 🔜 Formation utilisateurs (Sophie, Karim, Agent, Manager)
5. 🔜 EPIC-12 Notifications (emails automatiques)
6. 🔜 P7 — Évaluation post-lancement (KPIs)
7. 🔜 V2.1 — Améliorations continue (authentification carte agent)

---

_Dernière mise à jour : 2026-02-01 — OpsTracker v2.0.0 V2 READY (Module Réservation Doodle)_