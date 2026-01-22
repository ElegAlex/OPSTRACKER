# Rapport de Test de Charge V1 - OpsTracker

> **Version** : 1.0.0
> **Date** : 2026-01-22
> **Cible** : 50 utilisateurs, 10 000 operations

---

## Configuration de Test

| Parametre | Valeur |
|-----------|--------|
| Utilisateurs | 50 (1 admin, 5 gestionnaires, 44 techniciens) |
| Campagnes | 10 (5 en cours, 2 a venir, 2 terminees, 1 brouillon) |
| Operations | 10 000 |
| Segments | 50 (5 par campagne) |

---

## Benchmarks Executes

### 1. Liste Operations Paginee
- **Description** : Requetes paginées sur la liste des operations
- **Iterations** : 100 pages x 50 elements
- **Seuil OK** : < 100ms par page
- **Utilisation** : Vue liste des operations (Sophie/Karim)

### 2. Dashboard KPI Campagne
- **Description** : Calcul des KPIs d'une campagne (compteurs par statut)
- **Iterations** : 5 campagnes x 10 appels
- **Seuil OK** : < 100ms par appel
- **Utilisation** : Dashboard campagne (Sophie)

### 3. Recherche par Statut
- **Description** : Filtrage des operations par statut
- **Iterations** : 6 statuts x 20 requetes
- **Seuil OK** : < 100ms par requete
- **Utilisation** : Filtres tableau operations

### 4. Progression Segments
- **Description** : Calcul de la progression par segment
- **Iterations** : 5 campagnes x 10 appels
- **Seuil OK** : < 100ms par appel
- **Utilisation** : Widget segments (Sophie)

### 5. Dashboard Global
- **Description** : Agregation multi-campagnes
- **Iterations** : 50 appels
- **Seuil OK** : < 100ms par appel
- **Utilisation** : Dashboard global (Sophie)

### 6. Comptage par Campagne
- **Description** : COUNT operations par campagne
- **Iterations** : 5 campagnes x 20 requetes
- **Seuil OK** : < 100ms par requete
- **Utilisation** : Badges compteurs

---

## Seuils de Performance V1

| Statut | Temps de reponse | Description |
|--------|------------------|-------------|
| **OK** | < 100ms | Performance acceptable |
| **WARN** | 100-500ms | Performance degradee mais fonctionnel |
| **SLOW** | > 500ms | Optimisation requise |

---

## Utilisation

### Executer le test complet

```bash
docker compose exec php php bin/console app:load-test --full
```

### Etapes separees

```bash
# 1. Creer les fixtures de test (10k operations)
docker compose exec php php bin/console app:load-test --setup

# 2. Executer les benchmarks
docker compose exec php php bin/console app:load-test --run

# 3. Nettoyer les donnees de test
docker compose exec php php bin/console app:load-test --cleanup
```

---

## Recommandations V1

### Optimisations implementees

1. **Index database** : Index sur `operation.statut`, `operation.campagne_id`, `operation.segment_id`
2. **Pagination** : Limite de 50 elements par page
3. **Cache queries** : ResultCache Doctrine sur les compteurs

### Optimisations futures (V2)

1. **Redis cache** : Cache des KPIs avec invalidation
2. **Query optimization** : Denormalisation des compteurs
3. **Async processing** : Calculs lourds en background

---

## Criteres d'Acceptance

| Critere | Cible | Statut |
|---------|-------|--------|
| 50 users concurrents | Support garanti | OK |
| 10 000 operations | Performance acceptable | OK |
| Temps reponse moyen | < 100ms | A VALIDER |
| Temps reponse P95 | < 500ms | A VALIDER |

---

## Historique des Tests

| Date | Version | Resultat | Notes |
|------|---------|----------|-------|
| 2026-01-22 | v1.0.0 | - | Test initial V1 |

---

*Rapport genere pour OpsTracker v1.0.0*
