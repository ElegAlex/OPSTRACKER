# CURRENT_TASK.md — Tâche en Cours

> **Assignée le** : 2026-01-22
> **Session** : #2 (prochaine)

---

## 🎯 Prochain Sprint : Sprint 1 — Authentification & Utilisateurs

**Première tâche** : T-101 — Entité `Utilisateur`

---

## ✅ Sprint 0 Terminé

Toutes les tâches T-001 à T-007 ont été complétées dans la Session #1 :

- [x] T-001 : Projet Symfony 7.4 LTS
- [x] T-002 : Docker (PHP 8.3, PostgreSQL 17, Redis 7)
- [x] T-003 : AssetMapper + Tailwind CDN
- [x] T-004 : EasyAdmin 4.27
- [x] T-005 : UX Turbo + Stimulus
- [x] T-006 : PHPUnit (2 tests passants)
- [x] T-007 : Structure .claude/

---

## 🔜 Prochaines Étapes

Pour la prochaine session, commencer par T-101 :

1. Créer l'entité `Utilisateur` avec les champs :
   - email (unique)
   - password (hash)
   - roles (array)
   - actif (boolean)
   - prenom, nom
   - created_at, updated_at

2. Référence : RG-002, RG-003

---

## 🧪 Vérification Pré-Session

Avant de commencer Sprint 1 :

```bash
# Démarrer les services
docker compose up -d

# Vérifier que tout fonctionne
docker compose run --rm -e APP_ENV=test php php bin/phpunit

# Vérifier la connexion DB
docker compose run --rm php php bin/console doctrine:database:create --if-not-exists
```
