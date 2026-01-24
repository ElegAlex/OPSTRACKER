# PROGRESS-V2 — Module Reservation

> **Derniere mise a jour** : 2026-01-24 (Session #17 - Sprint 16 Complete)
> **Source** : P4.1 - EPIC-10, EPIC-11, EPIC-12
> **Total V2** : 26 User Stories | 3 EPICs

---

## Vue d'Ensemble V2

| Phase | Sprints | Statut | US | Focus |
|-------|---------|--------|-----|-------|
| **Setup** | 16 | ✅ Termine | 0 | Entites + Services |
| **Core** | 17-19 | ⏳ A faire | 15 | Creneaux + Reservation + Notifs |
| **Complements** | 20 | ⏳ A faire | 8 | Fonctionnalites V1 |
| **Finalisation** | 21 | ⏳ A faire | 3 | Tests + Audit P6 |

---

## PHASE V2 — Sprints 16 a 21

### Sprint 16 — Setup & Entites ✅

| ID | Tache | Statut | Detail |
|----|-------|--------|--------|
| T-1601 | Creer entite `Agent` + migration | ✅ | matricule, email, nom, prenom, service, site, manager, actif |
| T-1602 | Creer entite `Creneau` + migration | ✅ | campagne, segment, date, heureDebut, heureFin, capacite, lieu, verrouille |
| T-1603 | Creer entite `Reservation` + migration | ✅ | Contrainte unique (agent_id, campagne_id) - RG-121 |
| T-1604 | Creer entite `Notification` + migration | ✅ | agent, reservation, type, sujet, contenu, statut, sentAt |
| T-1605 | Creer repositories | ✅ | AgentRepository, CreneauRepository, ReservationRepository, NotificationRepository |
| T-1606 | Creer `CreneauService` (squelette) | ✅ | creer(), genererPlage(), modifier(), supprimer(), getDisponibles() |
| T-1607 | Creer `ReservationService` (squelette) | ✅ | reserver(), modifier(), annuler(), getByAgent(), getByManager() |
| T-1608 | Creer `NotificationService` (squelette) | ✅ | envoyerConfirmation/Rappel/Modification/Annulation/Invitation() |
| T-1609 | Creer `IcsGenerator` (squelette) | ✅ | generate(Reservation): string |
| T-1610 | Fixtures V2 | ✅ | 55 agents (5 managers + 50 agents), 60 creneaux, 30 reservations |
| T-1611 | CRUD Agent dans EasyAdmin | ✅ | Liste, creation, edition, filtres par service/site |

**Regles Metier Implementees Sprint 16** :
- RG-121 : Un agent = un seul creneau par campagne (contrainte unique DB)
- RG-124 : Manager ne voit que les agents de son service (filtre repository)
- RG-125 : Tracabilite : enregistrer qui a positionne (champ positionnePar)

---

### Sprint 17 — Gestion Creneaux (EPIC-11 Core) ⏳

| ID | US | Titre | Statut | RG | Priorite |
|----|-----|-------|--------|-----|----------|
| T-1701 | US-1101 | Creer des creneaux (manuel + auto) | ⏳ | RG-130 | MVP |
| T-1702 | US-1104 | Modifier un creneau | ⏳ | RG-133 | MVP |
| T-1703 | US-1105 | Supprimer un creneau | ⏳ | RG-134 | MVP |
| T-1704 | US-1106 | Voir le taux de remplissage | ⏳ | - | MVP |
| T-1705 | - | Templates creneaux (index, new, edit, generate) | ⏳ | - | - |
| T-1706 | - | Tests CreneauService | ⏳ | - | - |

---

### Sprint 18 — Interface Reservation (EPIC-10 Core) ⏳

| ID | US | Titre | Statut | RG | Priorite |
|----|-----|-------|--------|-----|----------|
| T-1801 | US-1001 | Voir creneaux disponibles (Agent) | ⏳ | RG-120 | MVP |
| T-1802 | US-1002 | Se positionner sur un creneau | ⏳ | RG-121, RG-122 | MVP |
| T-1803 | US-1003 | Annuler/modifier son creneau | ⏳ | RG-123 | MVP |
| T-1804 | US-1005 | Voir liste de mes agents (Manager) | ⏳ | RG-124 | MVP |
| T-1805 | US-1006 | Positionner un agent | ⏳ | RG-121, RG-125 | MVP |
| T-1806 | US-1007 | Modifier/annuler creneau d'un agent | ⏳ | RG-123, RG-126 | MVP |
| T-1807 | - | Templates reservation (agent, manager) | ⏳ | - | - |
| T-1808 | - | Tests ReservationService | ⏳ | - | - |

---

### Sprint 19 — Notifications (EPIC-12) ⏳

| ID | US | Titre | Statut | RG | Priorite |
|----|-----|-------|--------|-----|----------|
| T-1901 | US-1201 | Email confirmation + ICS | ⏳ | RG-140 | V1 |
| T-1902 | US-1202 | Email rappel J-2 | ⏳ | RG-141 | V1 |
| T-1903 | US-1203 | Email modification | ⏳ | RG-142 | V1 |
| T-1904 | US-1204 | Email annulation | ⏳ | RG-143 | V1 |
| T-1905 | US-1205 | Email invitation initiale | ⏳ | RG-144 | MVP |
| T-1906 | - | IcsGenerator service | ⏳ | - | - |
| T-1907 | - | Commande cron rappels | ⏳ | - | - |
| T-1908 | - | Configuration SMTP | ⏳ | - | - |
| T-1909 | - | Tests NotificationService | ⏳ | - | - |

---

### Sprint 20 — Complements V1 ⏳

| ID | US | Titre | Statut | RG | Priorite |
|----|-----|-------|--------|-----|----------|
| T-2001 | US-1004 | Recapitulatif agent | ⏳ | - | V1 |
| T-2002 | US-1008 | Vue planning manager (repartition) | ⏳ | RG-127 | V1 |
| T-2003 | US-1010 | Interface coordinateur | ⏳ | RG-114, RG-125 | V1 |
| T-2004 | US-1011 | Auth AD (fallback carte agent) | ⏳ | RG-128 | V1 |
| T-2005 | US-1102 | Definir capacite IT | ⏳ | RG-131 | V1 |
| T-2006 | US-1103 | Abaques duree intervention | ⏳ | RG-132 | V1 |
| T-2007 | US-1107 | Config verrouillage par campagne | ⏳ | RG-123 | V1 |
| T-2008 | US-1108 | Creneaux par segment/site | ⏳ | RG-135 | V1 |

---

### Sprint 21 — Tests & Audit P6 ⏳

| ID | Tache | Statut | Cible |
|----|-------|--------|-------|
| T-2101 | Tests E2E parcours agent | ⏳ | 5 scenarios |
| T-2102 | Tests E2E parcours manager | ⏳ | 5 scenarios |
| T-2103 | Tests E2E notifications | ⏳ | 5 emails |
| T-2104 | Audit P6.1-P6.6 (Qualify) | ⏳ | Score >=95% |
| T-2105 | Corrections findings P6 | ⏳ | 0 bloquant |
| T-2106 | Documentation utilisateur V2 | ⏳ | Guide Agent + Manager |
| T-2107 | **TAG v2.0.0** | ⏳ | - |

---

## Metriques V2

| Metrique | Actuel | Cible |
|----------|--------|-------|
| Taches terminees | 11/48 | 48 |
| User Stories done | 0/26 | 26 |
| Entites creees | 4/4 | 4 |
| Services crees | 4/4 | 4 |
| Fixtures | 55 agents, 60 creneaux, 30 reservations | OK |
| Tests passants | 240 (V1) | 290+ |
| Score Audit P6 | - | >=95% |

---

## Fichiers Crees Sprint 16

**Entites** :
- `src/Entity/Agent.php`
- `src/Entity/Creneau.php`
- `src/Entity/Reservation.php`
- `src/Entity/Notification.php`

**Repositories** :
- `src/Repository/AgentRepository.php`
- `src/Repository/CreneauRepository.php`
- `src/Repository/ReservationRepository.php`
- `src/Repository/NotificationRepository.php`

**Services** :
- `src/Service/CreneauService.php`
- `src/Service/ReservationService.php`
- `src/Service/NotificationService.php`
- `src/Service/IcsGenerator.php`

**Fixtures** :
- `src/DataFixtures/ReservationFixtures.php`

**Admin** :
- `src/Controller/Admin/AgentCrudController.php`

**Migration** :
- `migrations/Version20260124130004.php`

---

## Legende

| Symbole | Signification |
|---------|---------------|
| ⏳ | A faire |
| 🔄 | En cours |
| ✅ | Termine |
| ❌ | Bloque |
| MVP | MUST (MVP module) |
| V1 | SHOULD (V1 module) |
| V2 | COULD (V2 module) |

---

## Prochaines Etapes

1. ✅ ~~Sprint 16 : Setup entites + services~~
2. ⏳ Sprint 17 : CRUD Creneaux
3. ⏳ Sprint 18 : Interface reservation
4. ⏳ Sprint 19 : Notifications email
5. ⏳ Sprint 20 : Complements
6. ⏳ Sprint 21 : Audit P6 + TAG v2.0.0

---

_Derniere mise a jour : 2026-01-24 — OpsTracker V2 Sprint 16 Complete_
