# CURRENT_TASK.md — Tâche en Cours

> **Assignée le** : 2026-01-22  
> **Session** : #1

---

## 🎯 Tâche : T-001 — Créer le projet Symfony 7.4

**Sprint** : 0 - Setup & Infrastructure  
**Priorité** : 🔴 MVP — Bloquant pour tout le reste  
**Estimation** : 30 min

---

## 📋 Description

Initialiser le projet Symfony avec la configuration de base pour OpsTracker.

---

## ✅ Critères de Done

- [ ] Projet créé avec `symfony new opstracker --version=7.4`
- [ ] Dépendances de base installées :
  - [ ] `doctrine/orm`
  - [ ] `doctrine/doctrine-bundle`
  - [ ] `symfony/security-bundle`
  - [ ] `symfony/twig-bundle`
  - [ ] `symfony/asset-mapper`
  - [ ] `symfony/stimulus-bundle`
  - [ ] `symfony/ux-turbo`
  - [ ] `symfony/workflow`
  - [ ] `symfony/messenger`
- [ ] `.env` configuré pour PostgreSQL
- [ ] `config/packages/doctrine.yaml` avec driver pdo_pgsql
- [ ] Vérification : `php bin/console about` fonctionne
- [ ] Premier commit effectué

---

## 🔧 Commandes à exécuter

```bash
# ⚠️ NE PAS faire symfony new directement ici — ça écraserait .claude/ et design-reference/

# Créer dans un dossier temporaire
symfony new temp-sf --version=7.4 --webapp

# Déplacer le contenu (sauf .git du temp)
mv temp-sf/* .
mv temp-sf/.env .
rm -rf temp-sf

# Vérifier que .claude/ et design-reference/ sont toujours là
ls -la .claude/
ls -la design-reference/

# Vérifier l'installation
php bin/console about
```

---

## 📁 Configuration .env

```env
DATABASE_URL="postgresql://opstracker:opstracker@127.0.0.1:5432/opstracker?serverVersion=17&charset=utf8"
```

---

## ⚠️ Points d'attention

- Utiliser `--webapp` pour avoir Twig, Security, etc. pré-installés
- Ne PAS utiliser SQLite même pour les tests (PostgreSQL partout)
- Vérifier que AssetMapper est bien installé (pas webpack)

---

## 🔗 Tâche suivante

Après T-001 → **T-002** : Configurer Docker (PHP + PostgreSQL + Redis)

---

## 📝 Notes de progression

_À remplir pendant la session :_

```
[Heure] - Note
```
