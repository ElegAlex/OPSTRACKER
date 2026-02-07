# Contribuer a OpsTracker

Merci de votre interet pour OpsTracker ! Ce guide vous aidera a contribuer au projet.

## Prerequis

- PHP 8.3+
- Composer 2
- Docker 24.0+ et Docker Compose 2.20+
- Git

## Installation locale

```bash
# Cloner le depot
git clone https://github.com/ElegAlex/OPSTRACKER.git
cd OPSTRACKER

# Configurer l'environnement
cp .env .env.local
# Editer .env.local selon vos besoins

# Demarrer avec Docker
docker compose -f docker-compose.yml -f docker-compose.override.yml up -d

# Installer les dependances
docker compose exec app composer install

# Executer les migrations
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Charger les fixtures de dev
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

## Conventions de code

- **PHP** : PSR-12, verifie par PHP-CS-Fixer (`composer cs-check`)
- **Analyse statique** : PHPStan niveau 8 (`composer analyse`)
- **Tests** : PHPUnit (`composer test`)
- **Commits** : messages clairs en anglais, format conventionnel (`fix:`, `feat:`, `chore:`, `docs:`)

Avant de soumettre, verifiez que tout passe :

```bash
docker compose exec app composer cs-fix
docker compose exec app composer analyse
docker compose exec app composer test
```

## Process de contribution

1. **Ouvrir une issue** pour discuter du changement envisage
2. **Forker** le depot et creer une branche depuis `main` :
   ```bash
   git checkout -b feat/ma-fonctionnalite
   ```
3. **Developper** en respectant les conventions ci-dessus
4. **Tester** : ajouter des tests pour les nouvelles fonctionnalites
5. **Pousser** votre branche et ouvrir une **Pull Request** vers `main`

## Pull Requests

- Titre clair et descriptif
- Description du changement et de sa motivation
- Reference a l'issue concernee (`Fixes #123`)
- Tests verts (CI)
- Code style conforme (PHP-CS-Fixer)
- Analyse statique sans erreur (PHPStan)

## Signaler un bug

Ouvrez une [issue](https://github.com/ElegAlex/OPSTRACKER/issues) avec :
- Description du comportement observe vs attendu
- Etapes de reproduction
- Version d'OpsTracker, PHP, navigateur
- Logs pertinents (anonymises)

## Securite

Pour les vulnerabilites de securite, **ne pas ouvrir d'issue publique**.
Voir [SECURITY.md](SECURITY.md) pour la procedure de signalement.

## Licence

En contribuant, vous acceptez que vos contributions soient distribuees sous
licence [EUPL 1.2](LICENSE).
