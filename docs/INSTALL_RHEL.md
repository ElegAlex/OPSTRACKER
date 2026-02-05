# Guide d'installation OpsTracker sur RHEL 8.10

> **Version du document** : 1.0
> **Dernière mise à jour** : Février 2026
> **Temps d'installation estimé** : 30-60 minutes

---

## Table des matières

1. [Introduction](#1-introduction)
2. [Prérequis](#2-prérequis)
3. [Préparation du système](#3-préparation-du-système)
4. [Configuration réseau et proxy](#4-configuration-réseau-et-proxy)
5. [Installation de Docker](#5-installation-de-docker)
6. [Configuration de SELinux](#6-configuration-de-selinux)
7. [Configuration du pare-feu](#7-configuration-du-pare-feu)
8. [Installation d'OpsTracker](#8-installation-dopstracker)
9. [Configuration HTTPS](#9-configuration-https)
10. [Vérification post-installation](#10-vérification-post-installation)
11. [Dépannage](#11-dépannage)
12. [Maintenance](#12-maintenance)

---

## 1. Introduction

### 1.1 Qu'est-ce qu'OpsTracker ?

OpsTracker est une application web de suivi des opérations IT. Elle permet de gérer des campagnes de migration, de suivre la progression en temps réel, et de coordonner les équipes terrain.

### 1.2 Architecture

L'application s'exécute dans des conteneurs Docker :
- **nginx** : Serveur web (port 80/443)
- **app** : Application PHP/Symfony
- **postgres** : Base de données PostgreSQL
- **redis** : Cache et sessions

### 1.3 Modes d'installation

Ce guide couvre deux modes d'installation :

| Mode | Prérequis réseau | Cas d'usage |
|------|------------------|-------------|
| **En ligne** | Accès Internet | Installation standard |
| **Hors ligne** | Aucun | Environnements isolés, air-gapped |

---

## 2. Prérequis

### 2.1 Configuration matérielle minimale

| Ressource | Minimum | Recommandé |
|-----------|---------|------------|
| CPU | 2 cœurs | 4 cœurs |
| RAM | 4 Go | 8 Go |
| Disque | 20 Go | 50 Go |

### 2.2 Système d'exploitation

- **Red Hat Enterprise Linux 8.10** (ou compatible : CentOS Stream 8, Rocky Linux 8, AlmaLinux 8)
- Installation minimale ou serveur
- Accès root ou utilisateur avec droits sudo

### 2.3 Informations à collecter avant l'installation

Notez ces informations avant de commencer :

```
Nom d'hôte du serveur    : _______________________
Adresse IP du serveur    : _______________________
Masque de sous-réseau    : _______________________
Passerelle par défaut    : _______________________
Serveur DNS              : _______________________
Adresse du proxy (si applicable) : _______________________
Port du proxy (si applicable)    : _______________________
```

---

## 3. Préparation du système

### 3.1 Connexion au serveur

Connectez-vous au serveur via SSH ou directement sur la console :

```bash
ssh utilisateur@adresse-ip-du-serveur
```

> **Note** : Remplacez `utilisateur` par votre nom d'utilisateur et `adresse-ip-du-serveur` par l'adresse IP réelle.

### 3.2 Passage en mode root

Pour exécuter les commandes d'installation, vous avez besoin des droits administrateur :

```bash
sudo -i
```

Le système vous demandera votre mot de passe. Tapez-le (rien ne s'affiche pendant la saisie, c'est normal) puis appuyez sur Entrée.

Votre invite de commande devrait maintenant ressembler à :
```
[root@serveur ~]#
```

### 3.3 Vérification de la version du système

Vérifiez que vous êtes bien sur RHEL 8 :

```bash
cat /etc/redhat-release
```

Résultat attendu (exemple) :
```
Red Hat Enterprise Linux release 8.10 (Ootpa)
```

### 3.4 Mise à jour du système

Mettez à jour les paquets existants :

```bash
dnf update -y
```

> **Attention** : Cette opération peut prendre plusieurs minutes. Ne l'interrompez pas.

### 3.5 Installation des outils de base

Installez les utilitaires nécessaires :

```bash
dnf install -y curl wget git tar unzip nano
```

### 3.6 Configuration du nom d'hôte (optionnel)

Si vous souhaitez définir un nom d'hôte spécifique :

```bash
hostnamectl set-hostname opstracker.votre-domaine.local
```

Vérifiez le changement :
```bash
hostname
```

---

## 4. Configuration réseau et proxy

### 4.1 Vérification de la connectivité Internet

Testez l'accès à Internet :

```bash
ping -c 3 google.com
```

**Si le ping fonctionne** : Vous avez un accès Internet direct. Passez à la section [4.3](#43-sans-proxy-vérification).

**Si le ping échoue** : Vous êtes peut-être derrière un proxy. Continuez avec la section [4.2](#42-configuration-du-proxy).

### 4.2 Configuration du proxy

> **Note** : Cette section est uniquement nécessaire si votre réseau utilise un proxy pour accéder à Internet.

#### 4.2.1 Variables d'environnement système

Créez un fichier de configuration pour le proxy :

```bash
nano /etc/profile.d/proxy.sh
```

Ajoutez le contenu suivant (adaptez l'adresse et le port) :

```bash
# Configuration du proxy
export http_proxy="http://adresse-proxy:port"
export https_proxy="http://adresse-proxy:port"
export HTTP_PROXY="http://adresse-proxy:port"
export HTTPS_PROXY="http://adresse-proxy:port"
export no_proxy="localhost,127.0.0.1,::1"
export NO_PROXY="localhost,127.0.0.1,::1"
```

> **Exemple concret** : Si votre proxy est à l'adresse 192.168.1.10 sur le port 3128 :
> ```bash
> export http_proxy="http://192.168.1.10:3128"
> ```

**Si le proxy nécessite une authentification** :
```bash
export http_proxy="http://utilisateur:motdepasse@adresse-proxy:port"
```

Sauvegardez le fichier :
- Appuyez sur `Ctrl + O` puis `Entrée` pour sauvegarder
- Appuyez sur `Ctrl + X` pour quitter

Appliquez la configuration :

```bash
source /etc/profile.d/proxy.sh
```

#### 4.2.2 Configuration du proxy pour DNF

Éditez la configuration de DNF :

```bash
nano /etc/dnf/dnf.conf
```

Ajoutez à la fin du fichier :

```ini
proxy=http://adresse-proxy:port
# Si authentification requise :
# proxy_username=utilisateur
# proxy_password=motdepasse
```

Sauvegardez et quittez (`Ctrl + O`, `Entrée`, `Ctrl + X`).

#### 4.2.3 Configuration du proxy pour Docker (à faire après l'installation de Docker)

Cette configuration sera appliquée lors de l'installation de Docker (section 5).

### 4.3 Sans proxy : vérification

Si vous n'utilisez pas de proxy, vérifiez simplement que vous pouvez télécharger des fichiers :

```bash
curl -I https://download.docker.com
```

Vous devriez voir une réponse commençant par `HTTP/2 200` ou `HTTP/1.1 200`.

---

## 5. Installation de Docker

Docker est le moteur de conteneurisation qui permet d'exécuter OpsTracker.

### 5.1 Installation en ligne (avec accès Internet)

#### 5.1.1 Ajout du dépôt Docker officiel

```bash
dnf config-manager --add-repo https://download.docker.com/linux/rhel/docker-ce.repo
```

#### 5.1.2 Installation des paquets Docker

```bash
dnf install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

> **Note** : Si vous obtenez une erreur concernant `container-tools`, exécutez d'abord :
> ```bash
> dnf module disable -y container-tools
> ```
> Puis relancez l'installation.

#### 5.1.3 Démarrage et activation de Docker

Démarrez le service Docker :

```bash
systemctl start docker
```

Activez le démarrage automatique au boot :

```bash
systemctl enable docker
```

Vérifiez que Docker fonctionne :

```bash
docker --version
docker compose version
```

Résultat attendu (les versions peuvent varier) :
```
Docker version 24.0.x, build xxxxx
Docker Compose version v2.x.x
```

#### 5.1.4 Configuration du proxy pour Docker (si applicable)

Si vous utilisez un proxy, configurez Docker pour l'utiliser :

```bash
mkdir -p /etc/systemd/system/docker.service.d
nano /etc/systemd/system/docker.service.d/http-proxy.conf
```

Ajoutez :

```ini
[Service]
Environment="HTTP_PROXY=http://adresse-proxy:port"
Environment="HTTPS_PROXY=http://adresse-proxy:port"
Environment="NO_PROXY=localhost,127.0.0.1"
```

Rechargez la configuration et redémarrez Docker :

```bash
systemctl daemon-reload
systemctl restart docker
```

#### 5.1.5 Test de Docker

Vérifiez que Docker peut télécharger et exécuter une image :

```bash
docker run --rm hello-world
```

Vous devriez voir un message "Hello from Docker!" confirmant que l'installation fonctionne.

---

### 5.2 Installation hors ligne (sans accès Internet)

> **Prérequis** : Vous aurez besoin d'une machine avec accès Internet pour préparer les fichiers, puis d'un moyen de les transférer (clé USB, partage réseau, etc.).

#### 5.2.1 Sur une machine avec accès Internet : préparation des fichiers

**a) Téléchargement des RPM Docker**

Sur une machine RHEL 8 avec accès Internet :

```bash
# Créer un dossier pour les fichiers
mkdir -p /tmp/docker-offline
cd /tmp/docker-offline

# Ajouter le dépôt Docker
dnf config-manager --add-repo https://download.docker.com/linux/rhel/docker-ce.repo

# Télécharger les RPM sans les installer
dnf download --resolve --destdir=/tmp/docker-offline \
    docker-ce docker-ce-cli containerd.io \
    docker-buildx-plugin docker-compose-plugin
```

**b) Téléchargement des images Docker OpsTracker**

```bash
# Créer le dossier pour les images
mkdir -p /tmp/opstracker-offline

# Télécharger les images nécessaires
docker pull nginx:1.25-alpine
docker pull postgres:17-alpine
docker pull redis:7-alpine
docker pull php:8.3-fpm-alpine
docker pull composer:2.7

# Sauvegarder les images dans des fichiers tar
docker save nginx:1.25-alpine -o /tmp/opstracker-offline/nginx.tar
docker save postgres:17-alpine -o /tmp/opstracker-offline/postgres.tar
docker save redis:7-alpine -o /tmp/opstracker-offline/redis.tar
docker save php:8.3-fpm-alpine -o /tmp/opstracker-offline/php.tar
docker save composer:2.7 -o /tmp/opstracker-offline/composer.tar
```

**c) Téléchargement du code OpsTracker**

```bash
cd /tmp
git clone --depth 1 https://github.com/ElegAlex/OPSTRACKER.git opstracker-source
tar czf opstracker-source.tar.gz opstracker-source
```

**d) Création de l'archive finale**

```bash
cd /tmp
tar czf opstracker-offline-bundle.tar.gz \
    docker-offline \
    opstracker-offline \
    opstracker-source.tar.gz
```

**e) Transfert des fichiers**

Copiez le fichier `opstracker-offline-bundle.tar.gz` sur une clé USB ou un partage réseau accessible depuis le serveur cible.

#### 5.2.2 Sur le serveur cible (sans Internet)

**a) Copie des fichiers**

Montez votre clé USB ou accédez au partage réseau, puis copiez l'archive :

```bash
# Exemple avec clé USB (adaptez le chemin)
mkdir -p /mnt/usb
mount /dev/sdb1 /mnt/usb
cp /mnt/usb/opstracker-offline-bundle.tar.gz /tmp/
umount /mnt/usb
```

**b) Extraction de l'archive**

```bash
cd /tmp
tar xzf opstracker-offline-bundle.tar.gz
```

**c) Installation des RPM Docker**

```bash
cd /tmp/docker-offline
dnf localinstall -y *.rpm
```

**d) Démarrage de Docker**

```bash
systemctl start docker
systemctl enable docker
```

**e) Chargement des images Docker**

```bash
cd /tmp/opstracker-offline
docker load -i nginx.tar
docker load -i postgres.tar
docker load -i redis.tar
docker load -i php.tar
docker load -i composer.tar
```

**f) Vérification**

```bash
docker images
```

Vous devriez voir les 5 images listées.

---

## 6. Configuration de SELinux

SELinux (Security-Enhanced Linux) est un mécanisme de sécurité activé par défaut sur RHEL. Vous avez deux options pour le configurer.

### 6.1 Vérification du statut actuel

```bash
getenforce
```

Résultats possibles :
- `Enforcing` : SELinux est actif et bloque les actions non autorisées
- `Permissive` : SELinux journalise mais ne bloque pas
- `Disabled` : SELinux est désactivé

### 6.2 Option A : Configuration correcte de SELinux (recommandé)

Cette option maintient la sécurité de votre système tout en permettant à Docker de fonctionner.

#### 6.2.1 Installation des outils SELinux

```bash
dnf install -y policycoreutils-python-utils setools-console
```

#### 6.2.2 Configuration des contextes pour Docker

```bash
# Autoriser les conteneurs à se connecter au réseau
setsebool -P container_connect_any 1

# Autoriser les conteneurs à utiliser tous les ports
semanage port -a -t http_port_t -p tcp 80 2>/dev/null || true
semanage port -a -t http_port_t -p tcp 443 2>/dev/null || true
```

#### 6.2.3 Création du dossier d'installation avec le bon contexte

```bash
mkdir -p /opt/opstracker
chcon -Rt svirt_sandbox_file_t /opt/opstracker
```

#### 6.2.4 En cas de problèmes

Si vous rencontrez des erreurs liées à SELinux après l'installation, consultez les logs :

```bash
ausearch -m avc -ts recent
```

Pour générer une politique autorisant les actions bloquées :

```bash
ausearch -m avc -ts recent | audit2allow -M opstracker-policy
semodule -i opstracker-policy.pp
```

### 6.3 Option B : Désactivation de SELinux (déconseillé en production)

> **Avertissement** : Cette option réduit la sécurité de votre système. Ne l'utilisez que si l'option A pose des problèmes insurmontables.

#### 6.3.1 Passage en mode permissif (temporaire)

Pour tester sans redémarrer :

```bash
setenforce 0
```

#### 6.3.2 Désactivation permanente

Éditez le fichier de configuration :

```bash
nano /etc/selinux/config
```

Modifiez la ligne :
```
SELINUX=enforcing
```

En :
```
SELINUX=permissive
```

> **Note** : Utilisez `permissive` plutôt que `disabled` pour garder la possibilité de réactiver SELinux plus tard.

Redémarrez le serveur pour appliquer :

```bash
reboot
```

Après le redémarrage, reconnectez-vous et repassez en mode root :

```bash
ssh utilisateur@adresse-ip-du-serveur
sudo -i
```

---

## 7. Configuration du pare-feu

RHEL 8 utilise `firewalld` comme pare-feu par défaut.

### 7.1 Vérification du statut du pare-feu

```bash
systemctl status firewalld
```

Si le pare-feu n'est pas actif, vous pouvez passer à la section suivante. S'il est actif (vous voyez "active (running)"), continuez ci-dessous.

### 7.2 Ouverture des ports nécessaires

#### 7.2.1 Port HTTP (80)

```bash
firewall-cmd --permanent --add-service=http
```

#### 7.2.2 Port HTTPS (443)

```bash
firewall-cmd --permanent --add-service=https
```

#### 7.2.3 Application des changements

```bash
firewall-cmd --reload
```

### 7.3 Vérification

```bash
firewall-cmd --list-all
```

Vous devriez voir `http` et `https` dans la liste des services autorisés :
```
services: cockpit dhcpv6-client http https ssh
```

---

## 8. Installation d'OpsTracker

### 8.1 Installation en ligne (recommandée)

#### 8.1.1 Installation automatique

Exécutez la commande suivante :

```bash
curl -sL https://raw.githubusercontent.com/ElegAlex/OPSTRACKER/main/scripts/quick-install.sh | bash
```

L'installation prend environ 5 à 15 minutes selon votre connexion Internet.

#### 8.1.2 Suivi de l'installation

L'installateur affiche sa progression :

```
======================================================
       OpsTracker - Installation automatique
======================================================

[1/7] Verification du systeme...
[OK] Systeme : Red Hat Enterprise Linux 8.10

[2/7] Verification de Docker...
[OK] Docker deja installe (v24.x.x)

[3/7] Verification de Docker Compose...
[OK] Docker Compose disponible

[4/7] Telechargement d'OpsTracker...
[OK] OpsTracker telecharge dans /opt/opstracker

[5/7] Configuration...
[OK] Configuration generee (secrets aleatoires)

[6/7] Installation (peut prendre plusieurs minutes)...
  Construction de l'image Docker...
  Demarrage des services...
  [OK] Services prets

[7/7] Configuration de la base de donnees...
  [OK] Migrations executees
  [OK] Compte administrateur cree

======================================================
       OpsTracker installe avec succes !
======================================================

  URL locale :    http://localhost
  URL reseau :    http://192.168.1.100

  Identifiants par defaut :
    Email :       admin@opstracker.local
    Mot de passe: Admin123!

  /!\ IMPORTANT: Changez le mot de passe admin immediatement !
```

### 8.2 Installation hors ligne

Si vous avez préparé les fichiers selon la section 5.2 :

#### 8.2.1 Extraction du code source

```bash
cd /tmp
tar xzf opstracker-source.tar.gz
mv opstracker-source /opt/opstracker
cd /opt/opstracker
```

#### 8.2.2 Génération de la configuration

Créez le fichier de configuration :

```bash
# Génération de secrets aléatoires
APP_SECRET=$(openssl rand -hex 16)
DB_PASSWORD=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)

# Création du fichier .env
cat > .env << EOF
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=$APP_SECRET

DB_NAME=opstracker
DB_USER=opstracker
DB_PASSWORD=$DB_PASSWORD

DATABASE_URL="postgresql://opstracker:$DB_PASSWORD@postgres:5432/opstracker?serverVersion=17"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
MAILER_DSN=null://null

DEFAULT_URI=http://localhost

NGINX_PORT=80
SMS_ENABLED=false
SMS_PROVIDER=log

COOKIE_SECURE=false
EOF
```

#### 8.2.3 Construction et démarrage

```bash
docker compose build
docker compose up -d
```

#### 8.2.4 Attente et configuration de la base de données

Attendez que les services démarrent (environ 30 secondes) :

```bash
sleep 30
```

Exécutez les migrations :

```bash
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction
```

Créez le compte administrateur :

```bash
docker compose exec -T app php bin/console app:create-admin \
    admin@opstracker.local \
    Admin \
    Admin \
    --password='Admin123!'
```

### 8.3 Vérification de l'installation

#### 8.3.1 Vérification des conteneurs

```bash
cd /opt/opstracker
docker compose ps
```

Tous les conteneurs doivent être en état "Up" et "healthy" :

```
NAME                  STATUS                   PORTS
opstracker_app        Up (healthy)             9000/tcp
opstracker_nginx      Up (healthy)             0.0.0.0:80->80/tcp
opstracker_postgres   Up (healthy)             5432/tcp
opstracker_redis      Up (healthy)             6379/tcp
```

#### 8.3.2 Test d'accès

Depuis le serveur :

```bash
curl -I http://localhost
```

Vous devriez voir :
```
HTTP/1.1 302 Found
Location: http://localhost/login
```

#### 8.3.3 Accès depuis un navigateur

Ouvrez un navigateur web et accédez à :

```
http://adresse-ip-du-serveur
```

Vous devriez voir la page de connexion OpsTracker.

---

## 9. Configuration HTTPS

### 9.1 Pourquoi HTTPS ?

HTTPS chiffre les communications entre le navigateur et le serveur, protégeant :
- Les identifiants de connexion
- Les données sensibles
- L'intégrité des données

### 9.2 Option A : Let's Encrypt (certificat gratuit, automatique)

> **Prérequis** : Le serveur doit être accessible depuis Internet sur le port 80, et vous devez avoir un nom de domaine pointant vers le serveur.

#### 9.2.1 Installation de Certbot

```bash
dnf install -y epel-release
dnf install -y certbot
```

#### 9.2.2 Arrêt temporaire de nginx

```bash
cd /opt/opstracker
docker compose stop nginx
```

#### 9.2.3 Obtention du certificat

```bash
certbot certonly --standalone -d votre-domaine.com
```

Suivez les instructions à l'écran. Certbot vous demandera :
- Une adresse email (pour les notifications d'expiration)
- D'accepter les conditions d'utilisation

#### 9.2.4 Configuration de nginx pour HTTPS

Créez un nouveau fichier de configuration :

```bash
nano /opt/opstracker/docker/nginx/default-ssl.conf
```

Contenu :

```nginx
upstream php-upstream {
    server app:9000;
}

# Redirection HTTP vers HTTPS
server {
    listen 80;
    server_name votre-domaine.com;
    return 301 https://$server_name$request_uri;
}

# Serveur HTTPS
server {
    listen 443 ssl http2;
    server_name votre-domaine.com;
    root /var/www/html/public;

    # Certificats Let's Encrypt
    ssl_certificate /etc/letsencrypt/live/votre-domaine.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/votre-domaine.com/privkey.pem;

    # Configuration SSL sécurisée
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;

    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Logs
    access_log /var/log/nginx/opstracker_access.log;
    error_log /var/log/nginx/opstracker_error.log;

    # Assets statiques
    location ~* \.(jpg|jpeg|gif|png|css|js|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass php-upstream;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_read_timeout 300;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

#### 9.2.5 Modification du docker-compose.yml

Éditez le fichier :

```bash
nano /opt/opstracker/docker-compose.yml
```

Modifiez la section `nginx` :

```yaml
  nginx:
    image: nginx:1.25-alpine
    container_name: opstracker_nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./docker/nginx/default-ssl.conf:/etc/nginx/conf.d/default.conf:ro
      - /etc/letsencrypt:/etc/letsencrypt:ro
      - public_assets:/var/www/html/public:ro
      - nginx_logs:/var/log/nginx
```

#### 9.2.6 Modification de la configuration OpsTracker

Mettez à jour le fichier .env pour activer les cookies sécurisés :

```bash
nano /opt/opstracker/.env
```

Modifiez :
```
COOKIE_SECURE=true
DEFAULT_URI=https://votre-domaine.com
```

#### 9.2.7 Redémarrage

```bash
cd /opt/opstracker
docker compose up -d
```

#### 9.2.8 Renouvellement automatique

Let's Encrypt délivre des certificats valides 90 jours. Configurez le renouvellement automatique :

```bash
echo "0 3 * * * root certbot renew --quiet --post-hook 'docker restart opstracker_nginx'" >> /etc/crontab
```

### 9.3 Option B : Certificat interne (entreprise)

Si vous utilisez une autorité de certification interne :

#### 9.3.1 Préparation des certificats

Vous devez obtenir auprès de votre équipe PKI :
- Le certificat serveur (ex: `opstracker.crt`)
- La clé privée (ex: `opstracker.key`)
- Le certificat de l'autorité de certification (ex: `ca.crt`)

#### 9.3.2 Installation des certificats

```bash
mkdir -p /opt/opstracker/certs
cp opstracker.crt /opt/opstracker/certs/
cp opstracker.key /opt/opstracker/certs/
cp ca.crt /opt/opstracker/certs/

# Création du fichier fullchain
cat /opt/opstracker/certs/opstracker.crt /opt/opstracker/certs/ca.crt > /opt/opstracker/certs/fullchain.crt

# Sécurisation des fichiers
chmod 600 /opt/opstracker/certs/opstracker.key
chmod 644 /opt/opstracker/certs/*.crt
```

#### 9.3.3 Configuration nginx

Créez le fichier de configuration SSL :

```bash
nano /opt/opstracker/docker/nginx/default-ssl.conf
```

Contenu (adaptez le nom du serveur) :

```nginx
upstream php-upstream {
    server app:9000;
}

server {
    listen 80;
    server_name opstracker.votre-entreprise.local;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name opstracker.votre-entreprise.local;
    root /var/www/html/public;

    ssl_certificate /etc/nginx/certs/fullchain.crt;
    ssl_certificate_key /etc/nginx/certs/opstracker.key;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    access_log /var/log/nginx/opstracker_access.log;
    error_log /var/log/nginx/opstracker_error.log;

    location ~* \.(jpg|jpeg|gif|png|css|js|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass php-upstream;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_read_timeout 300;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

#### 9.3.4 Modification du docker-compose.yml

```bash
nano /opt/opstracker/docker-compose.yml
```

Section nginx :

```yaml
  nginx:
    image: nginx:1.25-alpine
    container_name: opstracker_nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./docker/nginx/default-ssl.conf:/etc/nginx/conf.d/default.conf:ro
      - ./certs:/etc/nginx/certs:ro
      - public_assets:/var/www/html/public:ro
      - nginx_logs:/var/log/nginx
```

#### 9.3.5 Mise à jour de la configuration et redémarrage

```bash
nano /opt/opstracker/.env
```

Modifiez :
```
COOKIE_SECURE=true
DEFAULT_URI=https://opstracker.votre-entreprise.local
```

Redémarrez :

```bash
cd /opt/opstracker
docker compose up -d
```

---

## 10. Vérification post-installation

### 10.1 Liste de contrôle

Vérifiez chaque point après l'installation :

| Vérification | Commande | Résultat attendu |
|--------------|----------|------------------|
| Conteneurs actifs | `docker compose ps` | 4 conteneurs "Up (healthy)" |
| Page de login accessible | Navigateur → http(s)://serveur | Page de connexion affichée |
| Connexion admin | Login avec admin@opstracker.local | Accès au tableau de bord |
| CSS/JS chargés | F12 → Console | Aucune erreur 404 |

### 10.2 Test de connexion

1. Ouvrez votre navigateur
2. Accédez à `http://adresse-ip-du-serveur` (ou `https://votre-domaine.com`)
3. Connectez-vous avec :
   - **Email** : `admin@opstracker.local`
   - **Mot de passe** : `Admin123!`

### 10.3 Changement du mot de passe administrateur

**Important** : Changez immédiatement le mot de passe par défaut !

1. Connectez-vous à l'application
2. Cliquez sur votre nom en haut à droite
3. Sélectionnez "Mon profil"
4. Cliquez sur "Changer le mot de passe"
5. Entrez un nouveau mot de passe sécurisé (minimum 8 caractères, avec majuscule, chiffre et caractère spécial)

---

## 11. Dépannage

### 11.1 Les conteneurs ne démarrent pas

**Vérification** :
```bash
cd /opt/opstracker
docker compose ps
docker compose logs
```

**Solutions courantes** :

| Problème | Solution |
|----------|----------|
| Port 80 déjà utilisé | `dnf remove httpd` ou changez NGINX_PORT dans .env |
| Erreur de permission | Vérifiez SELinux : `ausearch -m avc -ts recent` |
| Mémoire insuffisante | Vérifiez avec `free -h`, ajoutez du swap si nécessaire |

### 11.2 Erreur 500 au login

**Vérification** :
```bash
docker compose exec -T app php bin/console doctrine:migrations:status
```

**Si les migrations ne sont pas exécutées** :
```bash
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction
```

### 11.3 Assets non chargés (erreurs 404 sur CSS/JS)

**Vérification** :
```bash
docker compose exec -T nginx ls /var/www/html/public/bundles/
```

**Si le dossier est vide ou n'existe pas** :
```bash
docker compose exec -T app php bin/console assets:install public
docker compose exec -T app php bin/console asset-map:compile
```

### 11.4 Problèmes de connexion à la base de données

**Vérification** :
```bash
docker compose exec -T app php bin/console dbal:run-sql "SELECT 1"
```

**Si erreur "password authentication failed"** :
```bash
# Les volumes PostgreSQL contiennent d'anciennes données
cd /opt/opstracker
docker compose down -v
docker compose up -d
# Puis relancez les migrations et créez l'admin
```

### 11.5 SELinux bloque Docker

**Vérification** :
```bash
ausearch -m avc -ts recent | grep docker
```

**Solution temporaire** :
```bash
setenforce 0
```

**Solution permanente** :
```bash
ausearch -m avc -ts recent | audit2allow -M opstracker
semodule -i opstracker.pp
setenforce 1
```

### 11.6 Consulter les logs

**Logs de tous les conteneurs** :
```bash
cd /opt/opstracker
docker compose logs -f
```

**Logs d'un conteneur spécifique** :
```bash
docker compose logs -f app      # Application PHP
docker compose logs -f nginx    # Serveur web
docker compose logs -f postgres # Base de données
```

**Logs Symfony** :
```bash
docker compose exec -T app cat var/log/prod.log
```

---

## 12. Maintenance

### 12.1 Sauvegarde

#### 12.1.1 Sauvegarde de la base de données

```bash
cd /opt/opstracker
docker compose exec -T postgres pg_dump -U opstracker opstracker > backup_$(date +%Y%m%d_%H%M%S).sql
```

#### 12.1.2 Sauvegarde complète (base + fichiers)

```bash
cd /opt/opstracker
# Arrêt des conteneurs
docker compose stop

# Sauvegarde
tar czf /backup/opstracker_backup_$(date +%Y%m%d).tar.gz \
    .env \
    docker-compose.yml \
    uploads/

# Sauvegarde base de données
docker compose start postgres
sleep 10
docker compose exec -T postgres pg_dump -U opstracker opstracker > /backup/opstracker_db_$(date +%Y%m%d).sql

# Redémarrage
docker compose start
```

### 12.2 Restauration

#### 12.2.1 Restauration de la base de données

```bash
cd /opt/opstracker
cat backup.sql | docker compose exec -T postgres psql -U opstracker opstracker
```

### 12.3 Mise à jour d'OpsTracker

```bash
cd /opt/opstracker

# Sauvegarde préalable (voir 12.1)

# Récupération des mises à jour
git pull

# Reconstruction et redémarrage
docker compose build
docker compose up -d

# Migrations
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction

# Nettoyage du cache
docker compose exec -T app php bin/console cache:clear
```

### 12.4 Redémarrage des services

```bash
cd /opt/opstracker
docker compose restart
```

### 12.5 Arrêt complet

```bash
cd /opt/opstracker
docker compose down
```

### 12.6 Démarrage après arrêt

```bash
cd /opt/opstracker
docker compose up -d
```

---

## Annexes

### A. Commandes utiles

| Action | Commande |
|--------|----------|
| Voir les conteneurs | `docker compose ps` |
| Voir les logs | `docker compose logs -f` |
| Redémarrer | `docker compose restart` |
| Arrêter | `docker compose down` |
| Démarrer | `docker compose up -d` |
| Entrer dans un conteneur | `docker compose exec -it app sh` |
| Vider le cache | `docker compose exec app php bin/console cache:clear` |

### B. Ports utilisés

| Port | Service | Usage |
|------|---------|-------|
| 80 | nginx | HTTP |
| 443 | nginx | HTTPS |
| 5432 | postgres | Base de données (interne) |
| 6379 | redis | Cache (interne) |
| 9000 | php-fpm | Application (interne) |

### C. Fichiers importants

| Fichier | Description |
|---------|-------------|
| `/opt/opstracker/.env` | Configuration principale |
| `/opt/opstracker/docker-compose.yml` | Configuration Docker |
| `/opt/opstracker/docker/nginx/default.conf` | Configuration nginx |

### D. Support

En cas de problème non résolu par ce guide :

1. Consultez les logs : `docker compose logs`
2. Vérifiez les issues GitHub : https://github.com/ElegAlex/OPSTRACKER/issues
3. Ouvrez une nouvelle issue avec :
   - La version de RHEL (`cat /etc/redhat-release`)
   - Les logs d'erreur
   - Les étapes pour reproduire le problème

---

*Document généré pour OpsTracker - Février 2026*
