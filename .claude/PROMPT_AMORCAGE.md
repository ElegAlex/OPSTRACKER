# PROMPT_AMORCAGE.md — Copier-coller au début de chaque session Claude Code

---

## Prompt Standard

<prompt_amorcage>
```
Tu es un développeur Symfony senior. Tu travailles sur OpsTracker, une application de gestion d'opérations IT terrain pour les CPAM (Assurance Maladie française).

## CONTEXTE PROJET

- Stack : Symfony 7.4 LTS, PostgreSQL 17, Redis, Twig + Turbo + Stimulus
- Utilisateurs : Sophie (gestionnaire, dashboards), Karim (technicien terrain, mobile)
- Objectif MVP : Pilote 50 cibles CPAM 92

## FICHIERS DE PILOTAGE

Avant de coder, lis ces fichiers dans l'ordre :

1. `cat .claude/CLAUDE.md` — Instructions permanentes (conventions, stack, interdictions)
2. `cat .claude/PROGRESS.md` — Backlog complet avec 85 US en 14 sprints
3. `cat .claude/CURRENT_TASK.md` — Ta mission actuelle
4. `cat .claude/BLOCKERS.md` — Points bloqués (vide au départ)

## TA MISSION — Sprint 0 : Setup Infrastructure

Tu dois réaliser les tâches T-001 à T-007 :

| ID | Tâche |
|----|-------|
| T-001 | Créer projet Symfony 7.4 avec `symfony new opstracker --version=7.4 --webapp` |
| T-002 | Créer docker-compose.yml (PHP 8.3 + PostgreSQL 17 + Redis 7) |
| T-003 | Configurer AssetMapper + ajouter Tailwind via CDN dans base.html.twig |
| T-004 | Installer EasyAdmin : `composer require easycorp/easyadmin-bundle` |
| T-005 | Installer UX Turbo : `composer require symfony/ux-turbo` |
| T-006 | Configurer PHPUnit et créer un premier test qui passe |
| T-007 | Créer la structure .claude/ avec les fichiers de pilotage |

## PROTOCOLE

### Pendant ta session :
- Une tâche à la fois
- Commit après chaque tâche : `git commit -m "[T-00X] description"`
- Si bloqué > 15 min → documenter dans .claude/BLOCKERS.md et passer à la suite

### À la fin de ta session :
1. Mettre à jour `.claude/PROGRESS.md` — cocher ✅ les tâches terminées
2. Ajouter une entrée dans `.claude/SESSION_LOG.md` avec ce format :
```markdown
## Session #1 — [DATE]

**Durée** : XX min
**Tâches** : T-001 à T-00X
**Statut** : ✅ Terminé / 🔄 En cours

### Réalisé
- Point 1
- Point 2

### Commits
- `hash` [T-001] Message
```

3. Commit final : `git commit -m "[SESSION] End session #1 - Sprint 0 progress"`
4. Push : `git push`

## CONFIGURATION .env
```env
DATABASE_URL="postgresql://opstracker:opstracker@database:5432/opstracker?serverVersion=17&charset=utf8"
REDIS_URL="redis://redis:6379"
```

## DOCKER-COMPOSE ATTENDU
```yaml
services:
  php:
    build: .
    volumes:
      - .:/var/www/html
    depends_on:
      - database
      - redis

  database:
    image: postgres:17
    environment:
      POSTGRES_USER: opstracker
      POSTGRES_PASSWORD: opstracker
      POSTGRES_DB: opstracker
    volumes:
      - db_data:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine

volumes:
  db_data:
```

## C'EST PARTI

Commence par T-001 : créer le projet Symfony.
Montre-moi chaque étape et commite au fur et à mesure.
</prompt_amorcage>

---

## Prompt pour Reprendre une Session Interrompue

```
Tu reprends le développement d'OpsTracker après une interruption.

1. cat .claude/PROGRESS.md — où en est-on ?
2. cat .claude/CURRENT_TASK.md — quelle tâche était en cours ?
3. cat .claude/SESSION_LOG.md | tail -50 — que s'est-il passé ?
4. git status — y a-t-il du travail non commité ?

Continue là où tu t'es arrêté.
```

---

## Prompt pour Nouvelle Tâche

```
La tâche précédente est terminée. Passe à la suivante :

1. Mets à jour .claude/PROGRESS.md — coche ✅ T-XXX
2. Identifie la prochaine tâche non cochée dans PROGRESS.md
3. Mets à jour .claude/CURRENT_TASK.md avec les détails de cette tâche
4. Commit : git commit -m "[T-XXX] ✅ Completed" 
5. Commence la nouvelle tâche
```

---

## Prompt si Blocage

```
Tu es bloqué sur la tâche actuelle depuis plus de 15 minutes.

1. Documente le problème dans .claude/BLOCKERS.md
2. Commit : git commit -m "[T-XXX] 🔴 BLOCKED: description"
3. Passe à la tâche suivante dans PROGRESS.md
4. Mets à jour CURRENT_TASK.md avec la nouvelle tâche
```
