# Politique de securite

## Versions supportees

| Version | Support          |
|---------|------------------|
| 2.3.x  | :white_check_mark: Corrections de securite |
| 2.2.x  | :white_check_mark: Corrections critiques uniquement |
| < 2.2  | :x: Plus supportee |

## Signaler une vulnerabilite

**Ne pas ouvrir d'issue publique pour les vulnerabilites de securite.**

Pour signaler une vulnerabilite, veuillez utiliser la fonctionnalite
[GitHub Security Advisories](https://github.com/ElegAlex/OPSTRACKER/security/advisories/new)
du depot.

Vous pouvez egalement contacter les mainteneurs directement via les
coordonnees disponibles sur leur profil GitHub.

### Informations a fournir

- Description de la vulnerabilite
- Etapes de reproduction
- Impact potentiel
- Suggestion de correctif (si possible)

### Delai de reponse

- **Accusé de reception** : sous 48 heures
- **Evaluation initiale** : sous 7 jours
- **Correctif** : selon la severite, entre 7 et 30 jours

### Processus

1. Vous signalez la vulnerabilite via GitHub Security Advisories
2. Nous accusons reception et evaluons la severite
3. Nous developpons un correctif en prive
4. Nous publions le correctif et un avis de securite
5. Nous vous creditons dans l'avis (sauf si vous preferez l'anonymat)

## ⚠️ Post-migration open-source

Les versions anterieures a v2.3.0 contenaient un `APP_SECRET` de
developpement dans le `.env` tracke par git. Si vous avez deploye une version
anterieure, vous devez regenerer votre secret :

```bash
php -r "echo bin2hex(random_bytes(16));"
```

Puis mettre la nouvelle valeur dans votre `.env.local` et relancer
l'application.

Meme chose pour `DB_PASSWORD` si vous utilisiez la valeur par defaut.

## Bonnes pratiques de deploiement

- Toujours modifier `APP_SECRET` et `DB_PASSWORD` avant la mise en production
- Activer HTTPS (`COOKIE_SECURE=true`)
- Maintenir PHP, Symfony et les dependances a jour
- Consulter le [guide d'exploitation](docs/EXPLOITATION.md) pour le durcissement
