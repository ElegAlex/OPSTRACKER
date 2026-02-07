# Deploiement Docker OpsTracker

## Prerequis

- Docker 24.0+
- Docker Compose 2.20+
- 4 Go RAM minimum
- 20 Go d'espace disque

## Installation rapide

### 1. Cloner le depot

```bash
git clone https://github.com/ElegAlex/OPSTRACKER.git
cd opstracker
```

### 2. Configurer l'environnement

```bash
# Copier le fichier de configuration
cp .env .env.local

# Editer et changer les valeurs sensibles
nano .env.local
```

**IMPORTANT** : Changer obligatoirement :
- `APP_SECRET` : Generer avec `php -r "echo bin2hex(random_bytes(16));"`
- `DB_PASSWORD` : Mot de passe fort pour PostgreSQL

### 3. Lancer l'installation

```bash
make install
```

### 4. Acceder a l'application

- **Application** : http://localhost (ou https://opstracker.local)
- **Compte admin par defaut** : Creer via les fixtures ou manuellement

## Commandes utiles

| Commande | Description |
|----------|-------------|
| `make start` | Demarrer les conteneurs |
| `make stop` | Arreter les conteneurs |
| `make restart` | Redemarrer |
| `make logs` | Voir les logs |
| `make shell` | Acceder au conteneur PHP |
| `make db-migrate` | Appliquer les migrations |
| `make db-backup` | Sauvegarder la BDD |
| `make deploy` | Mise a jour (git pull + migrate) |
| `make status` | Etat des conteneurs |
| `make health` | Verifier la sante des services |

## Architecture

```
+-----------------------------------------------------------+
|                    Serveur OpsTracker                     |
+-----------------------------------------------------------+
|  +----------+  +----------+  +----------+  +-----------+  |
|  |  Nginx   |--|  PHP-FPM |--|PostgreSQL|--|   Redis   |  |
|  |  :443    |  |   8.3    |  |    17    |  |     7     |  |
|  +----------+  +----------+  +----------+  +-----------+  |
+-----------------------------------------------------------+
```

## Developpement local

### Configuration

```bash
# Copier la configuration dev
cp docker-compose.override.yml.dist docker-compose.override.yml

# Demarrer en mode dev
make start
```

### Ports en developpement

| Service | Port |
|---------|------|
| Application | http://localhost:8081 |
| Adminer (BDD) | http://localhost:8082 |
| Mailpit (emails) | http://localhost:8025 |
| PostgreSQL | localhost:5434 |
| Redis | localhost:6381 |

### Hot reload

En mode dev, le code source est monte dans le conteneur. Les modifications sont immediatement visibles.

## Production avec HTTPS

### 1. Placer les certificats SSL

```bash
mkdir -p docker/nginx/certs
cp /chemin/vers/certificat.crt docker/nginx/certs/certificate.crt
cp /chemin/vers/certificat.key docker/nginx/certs/certificate.key
```

### 2. Demarrer en mode production

```bash
make install-prod
```

Ou manuellement :

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

## Sauvegarde et restauration

### Sauvegarde manuelle

```bash
make db-backup
```

Les backups sont crees dans le dossier `backups/`.

### Sauvegarde automatique (crontab)

```bash
# Ajouter au crontab (quotidien a 2h)
0 2 * * * cd /opt/opstracker && make db-backup
```

### Restauration

```bash
make db-restore FILE=backups/backup_20240115_020000.sql
```

## Mise a jour

### Methode simple

```bash
make deploy
```

### Methode manuelle

```bash
git pull origin main
docker compose build app
docker compose up -d
make db-migrate
make cache-clear
```

## Depannage

### Les conteneurs ne demarrent pas

```bash
# Verifier les logs
make logs

# Verifier l'etat
make status

# Rebuild complet
make destroy
make install
```

### Probleme de permissions

```bash
# Dans le conteneur
make shell-root
chown -R www-data:www-data var/ uploads/
chmod -R 775 var/ uploads/
```

### Probleme de connexion BDD

```bash
# Verifier que PostgreSQL est pret
docker compose exec postgres pg_isready

# Tester la connexion
docker compose exec app php bin/console doctrine:query:sql "SELECT 1"
```

### Verifier la sante des services

```bash
make health
```

### Logs detailles

```bash
# Tous les logs
make logs

# Logs d'un service specifique
make logs-app    # PHP-FPM
make logs-nginx  # Nginx
make logs-db     # PostgreSQL
```

## Variables d'environnement

| Variable | Description | Defaut |
|----------|-------------|--------|
| `APP_ENV` | Environnement (dev/prod) | prod |
| `APP_SECRET` | Cle secrete Symfony | - |
| `DB_NAME` | Nom de la base | opstracker |
| `DB_USER` | Utilisateur PostgreSQL | opstracker |
| `DB_PASSWORD` | Mot de passe PostgreSQL | - |
| `NGINX_PORT` | Port HTTP | 80 |
| `MAILER_DSN` | Configuration email | null://null |
| `SMS_ENABLED` | Activer les SMS | false |

## Support

- **Documentation** : https://github.com/ElegAlex/OPSTRACKER/wiki
- **Issues** : https://github.com/ElegAlex/OPSTRACKER/issues
