# DECISIONS.md — Décisions Techniques (ADR Léger)

> Architecture Decision Records simplifiés pour OpsTracker

---

## Format

```markdown
## [DATE] DECISION-XXX : Titre

**Contexte** : Pourquoi cette décision était nécessaire
**Options considérées** : Liste des alternatives
**Décision** : Ce qui a été choisi
**Conséquences** : Impact sur le projet
```

---

## Décisions Prises

### [2026-01-22] DECISION-001 : Stack technique

**Contexte** : Choix initial de la stack pour OpsTracker (secteur public, self-hosted)

**Options considérées** :
1. Laravel + MySQL
2. Symfony + PostgreSQL
3. Django + PostgreSQL

**Décision** : Symfony 7.4 LTS + PostgreSQL 17

**Conséquences** :
- Compétences Symfony disponibles dans l'équipe
- LTS = support long terme (important secteur public)
- PostgreSQL pour JSONB (champs dynamiques)
- Pas de coût de licence

---

### [2026-01-22] DECISION-002 : Pas de SPA frontend

**Contexte** : Choix de l'architecture frontend

**Options considérées** :
1. React/Vue SPA + API
2. Twig SSR + Turbo/Stimulus
3. HTMX + Twig

**Décision** : Twig SSR + Turbo + Stimulus

**Conséquences** :
- Pas de build JS complexe
- SEO natif (SSR)
- Accessibilité plus simple
- Temps réel via Turbo Streams
- Équipe n'a pas besoin de compétences React

---

### [2026-01-22] DECISION-003 : AssetMapper vs Webpack

**Contexte** : Gestion des assets frontend

**Options considérées** :
1. Webpack Encore
2. AssetMapper (natif Symfony)
3. Vite

**Décision** : AssetMapper

**Conséquences** :
- Pas de npm/node requis
- Plus simple à maintenir
- Suffisant pour Tailwind CDN + Stimulus
- Moins de dépendances

---

_Ajouter les nouvelles décisions ci-dessous au fur et à mesure du développement._
