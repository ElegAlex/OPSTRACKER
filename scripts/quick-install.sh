#!/bin/bash

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Fonction pour afficher les erreurs et quitter
error_exit() {
    echo -e "${RED}❌ $1${NC}"
    exit 1
}

echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║           OpsTracker - Installation automatique           ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

# =============================================================================
# 1. VERIFICATIONS SYSTEME
# =============================================================================

echo -e "${YELLOW}[1/7] Verification du systeme...${NC}"

# Verifier qu'on est sur Linux
if [[ "$(uname)" != "Linux" ]]; then
    error_exit "Ce script est uniquement pour Linux.\n   Pour Mac/Windows, voir : https://github.com/ElegAlex/OPSTRACKER#installation"
fi

# Verifier qu'on est root ou sudo disponible
if [[ $EUID -ne 0 ]] && ! sudo -v &>/dev/null; then
    error_exit "Ce script necessite les droits sudo."
fi

# Detecter le gestionnaire de paquets
if command -v apt-get &>/dev/null; then
    PKG_MANAGER="apt"
elif command -v dnf &>/dev/null; then
    PKG_MANAGER="dnf"
elif command -v yum &>/dev/null; then
    PKG_MANAGER="yum"
else
    error_exit "Gestionnaire de paquets non supporte (apt/dnf/yum requis)."
fi

# Detecter la distribution
if command -v lsb_release &>/dev/null; then
    DISTRO=$(lsb_release -ds 2>/dev/null)
elif [[ -f /etc/os-release ]]; then
    DISTRO=$(grep PRETTY_NAME /etc/os-release | cut -d'"' -f2)
else
    DISTRO="Linux"
fi

echo -e "${GREEN}✓ Systeme : $DISTRO${NC}"

# =============================================================================
# 2. INSTALLATION DOCKER
# =============================================================================

echo -e "${YELLOW}[2/7] Verification de Docker...${NC}"

USE_SUDO=""

if command -v docker &>/dev/null; then
    DOCKER_VERSION=$(docker --version | cut -d' ' -f3 | tr -d ',')
    echo -e "${GREEN}✓ Docker deja installe (v$DOCKER_VERSION)${NC}"

    # Verifier si on peut utiliser docker sans sudo
    if ! docker info &>/dev/null 2>&1; then
        USE_SUDO="sudo"
        echo -e "${YELLOW}  (utilisation avec sudo)${NC}"
    fi
else
    echo "  Installation de Docker..."

    if [[ "$PKG_MANAGER" == "apt" ]]; then
        sudo apt-get update -qq
        sudo apt-get install -y -qq ca-certificates curl gnupg
        sudo install -m 0755 -d /etc/apt/keyrings

        if [[ -f /etc/os-release ]]; then
            . /etc/os-release
            if [[ "$ID" == "ubuntu" ]]; then
                DOCKER_REPO="https://download.docker.com/linux/ubuntu"
            else
                DOCKER_REPO="https://download.docker.com/linux/debian"
            fi
            CODENAME="${VERSION_CODENAME:-$(lsb_release -cs)}"
        else
            DOCKER_REPO="https://download.docker.com/linux/ubuntu"
            CODENAME=$(lsb_release -cs)
        fi

        curl -fsSL "$DOCKER_REPO/gpg" | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg 2>/dev/null || true
        sudo chmod a+r /etc/apt/keyrings/docker.gpg
        echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] $DOCKER_REPO $CODENAME stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
        sudo apt-get update -qq
        sudo apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    else
        sudo $PKG_MANAGER install -y -q dnf-plugins-core 2>/dev/null || true
        sudo $PKG_MANAGER config-manager --add-repo https://download.docker.com/linux/fedora/docker-ce.repo 2>/dev/null || true
        sudo $PKG_MANAGER install -y -q docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    fi

    sudo systemctl start docker
    sudo systemctl enable docker
    sudo usermod -aG docker $USER
    USE_SUDO="sudo"

    echo -e "${GREEN}✓ Docker installe${NC}"
    echo -e "${YELLOW}  Note: Utilisez 'newgrp docker' ou reconnectez-vous pour docker sans sudo${NC}"
fi

# =============================================================================
# 3. VERIFICATION DOCKER COMPOSE
# =============================================================================

echo -e "${YELLOW}[3/7] Verification de Docker Compose...${NC}"

# Definir la commande docker compose
DOCKER_COMPOSE="$USE_SUDO docker compose"

if $DOCKER_COMPOSE version &>/dev/null; then
    COMPOSE_VERSION=$($DOCKER_COMPOSE version --short 2>/dev/null)
    echo -e "${GREEN}✓ Docker Compose disponible (v$COMPOSE_VERSION)${NC}"
else
    error_exit "Docker Compose non disponible. Reessayez apres redemarrage."
fi

# Verifier git
if ! command -v git &>/dev/null; then
    echo -e "${YELLOW}  Installation de git...${NC}"
    if [[ "$PKG_MANAGER" == "apt" ]]; then
        sudo apt-get install -y -qq git
    else
        sudo $PKG_MANAGER install -y -q git
    fi
fi

echo -e "${GREEN}✓ Dependances verifiees${NC}"

# =============================================================================
# 4. CLONAGE DU REPO
# =============================================================================

echo -e "${YELLOW}[4/7] Telechargement d'OpsTracker...${NC}"

INSTALL_DIR="${INSTALL_DIR:-/opt/opstracker}"

if [[ -d "$INSTALL_DIR" ]]; then
    echo -e "${YELLOW}  ⚠ Le dossier $INSTALL_DIR existe deja.${NC}"
    read -p "  Supprimer et reinstaller ? (o/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Oo]$ ]]; then
        cd /
        if [[ -f "$INSTALL_DIR/docker-compose.yml" ]]; then
            cd "$INSTALL_DIR"
            $DOCKER_COMPOSE down -v --remove-orphans 2>/dev/null || true
            cd /
        fi
        sudo rm -rf "$INSTALL_DIR"
    else
        echo "Installation annulee."
        exit 0
    fi
fi

sudo git clone --depth 1 https://github.com/ElegAlex/OPSTRACKER.git "$INSTALL_DIR"
sudo chown -R $USER:$USER "$INSTALL_DIR"
cd "$INSTALL_DIR"

echo -e "${GREEN}✓ OpsTracker telecharge dans $INSTALL_DIR${NC}"

# =============================================================================
# 5. CONFIGURATION
# =============================================================================

echo -e "${YELLOW}[5/7] Configuration...${NC}"

APP_SECRET=$(openssl rand -hex 16)
DB_PASSWORD=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)

cat > .env << ENVEOF
# .env - Configuration OpsTracker (generee par quick-install.sh)
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=$APP_SECRET

# Base de donnees
DB_NAME=opstracker
DB_USER=opstracker
DB_PASSWORD=$DB_PASSWORD

# Symfony
DATABASE_URL="postgresql://opstracker:$DB_PASSWORD@postgres:5432/opstracker?serverVersion=17"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
MAILER_DSN=null://null

# Router
DEFAULT_URI=http://localhost

# Options
NGINX_PORT=80
SMS_ENABLED=false
SMS_PROVIDER=log
ENVEOF

echo -e "${GREEN}✓ Configuration generee (secrets aleatoires)${NC}"

# =============================================================================
# 6. INSTALLATION
# =============================================================================

echo -e "${YELLOW}[6/7] Installation (peut prendre plusieurs minutes)...${NC}"

echo "  Construction de l'image Docker..."
$DOCKER_COMPOSE build --no-cache || error_exit "Echec du build Docker"

echo "  Demarrage des services..."
$DOCKER_COMPOSE up -d || error_exit "Echec du demarrage des services"

# Attente PostgreSQL
echo -n "  Attente de PostgreSQL"
PG_READY=false
for i in $(seq 1 30); do
    if $DOCKER_COMPOSE exec -T postgres pg_isready -U opstracker -d opstracker >/dev/null 2>&1; then
        PG_READY=true
        break
    fi
    echo -n "."
    sleep 2
done
echo ""

if [ "$PG_READY" = false ]; then
    echo -e "${RED}❌ PostgreSQL n'a pas demarre a temps.${NC}"
    $DOCKER_COMPOSE logs postgres
    exit 1
fi
echo -e "${GREEN}  ✓ PostgreSQL pret${NC}"

# Attente Application
echo -n "  Attente de l'application"
APP_READY=false
for i in $(seq 1 30); do
    if $DOCKER_COMPOSE exec -T app php-fpm-healthcheck >/dev/null 2>&1; then
        APP_READY=true
        break
    fi
    echo -n "."
    sleep 2
done
echo ""

if [ "$APP_READY" = false ]; then
    echo -e "${RED}❌ L'application n'a pas demarre a temps.${NC}"
    $DOCKER_COMPOSE logs app
    exit 1
fi
echo -e "${GREEN}  ✓ Application prete${NC}"

# Migrations
echo "  Execution des migrations..."
if ! $DOCKER_COMPOSE exec -T app php bin/console doctrine:migrations:migrate --no-interaction; then
    error_exit "Echec des migrations"
fi
echo -e "${GREEN}  ✓ Migrations executees${NC}"

echo -e "${GREEN}✓ OpsTracker installe et demarre${NC}"

# =============================================================================
# 7. CREATION COMPTE ADMIN
# =============================================================================

echo -e "${YELLOW}[7/7] Creation du compte administrateur...${NC}"
echo ""

read -p "Email administrateur : " ADMIN_EMAIL
read -p "Nom de famille : " ADMIN_NOM
read -p "Prenom : " ADMIN_PRENOM
read -s -p "Mot de passe (8 car min, 1 maj, 1 chiffre, 1 special) : " ADMIN_PASSWORD
echo ""
read -s -p "Confirmer le mot de passe : " ADMIN_PASSWORD_CONFIRM
echo ""

if [[ "$ADMIN_PASSWORD" != "$ADMIN_PASSWORD_CONFIRM" ]]; then
    echo -e "${RED}❌ Les mots de passe ne correspondent pas.${NC}"
    echo "   Creez le compte plus tard avec :"
    echo "   cd $INSTALL_DIR && $DOCKER_COMPOSE exec app php bin/console app:create-admin"
else
    if $DOCKER_COMPOSE exec -T app php bin/console app:create-admin "$ADMIN_EMAIL" "$ADMIN_NOM" "$ADMIN_PRENOM" <<ADMINEOF
$ADMIN_PASSWORD
$ADMIN_PASSWORD
ADMINEOF
    then
        echo -e "${GREEN}✓ Compte administrateur cree${NC}"
    else
        echo -e "${YELLOW}⚠ Erreur lors de la creation du compte admin.${NC}"
        echo "   Creez-le manuellement avec :"
        echo "   cd $INSTALL_DIR && $DOCKER_COMPOSE exec app php bin/console app:create-admin"
    fi
fi

# =============================================================================
# TERMINE
# =============================================================================

IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "votre-ip")

echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║              ✅ Installation terminee !                   ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""
echo -e "  ${GREEN}Acces local :${NC}     http://localhost"
echo -e "  ${GREEN}Acces reseau :${NC}    http://$IP"
echo ""
echo -e "  ${GREEN}Dossier :${NC}         $INSTALL_DIR"
echo -e "  ${GREEN}Logs :${NC}            cd $INSTALL_DIR && docker compose logs -f"
echo -e "  ${GREEN}Arreter :${NC}         cd $INSTALL_DIR && docker compose down"
echo -e "  ${GREEN}Redemarrer :${NC}      cd $INSTALL_DIR && docker compose up -d"
echo ""
if [[ -n "$ADMIN_EMAIL" ]]; then
    echo -e "  ${YELLOW}Connexion :${NC}       $ADMIN_EMAIL"
    echo ""
fi

if [[ -n "$USE_SUDO" ]]; then
    echo -e "${YELLOW}Note: Docker necessite sudo. Reconnectez-vous ou utilisez 'newgrp docker'${NC}"
    echo -e "${YELLOW}      pour l'utiliser sans sudo.${NC}"
    echo ""
fi
