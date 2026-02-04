#!/bin/bash
set -e

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

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
    echo -e "${RED}❌ Ce script est uniquement pour Linux.${NC}"
    echo "   Pour Mac/Windows, voir : https://github.com/ElegAlex/OPSTRACKER#installation"
    exit 1
fi

# Verifier qu'on est root ou sudo disponible
if [[ $EUID -ne 0 ]] && ! sudo -v &>/dev/null; then
    echo -e "${RED}❌ Ce script necessite les droits sudo.${NC}"
    exit 1
fi

# Detecter le gestionnaire de paquets
if command -v apt-get &>/dev/null; then
    PKG_MANAGER="apt"
elif command -v dnf &>/dev/null; then
    PKG_MANAGER="dnf"
elif command -v yum &>/dev/null; then
    PKG_MANAGER="yum"
else
    echo -e "${RED}❌ Gestionnaire de paquets non supporte (apt/dnf/yum requis).${NC}"
    exit 1
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

NEED_NEWGRP=false

if command -v docker &>/dev/null; then
    DOCKER_VERSION=$(docker --version | cut -d' ' -f3 | tr -d ',')
    echo -e "${GREEN}✓ Docker deja installe (v$DOCKER_VERSION)${NC}"
else
    echo "  Installation de Docker..."

    if [[ "$PKG_MANAGER" == "apt" ]]; then
        # Installation Docker pour Ubuntu/Debian
        sudo apt-get update -qq
        sudo apt-get install -y -qq ca-certificates curl gnupg
        sudo install -m 0755 -d /etc/apt/keyrings

        # Detecter si Ubuntu ou Debian
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
        # Installation Docker pour RHEL/CentOS/Fedora
        sudo $PKG_MANAGER install -y -q dnf-plugins-core 2>/dev/null || true
        sudo $PKG_MANAGER config-manager --add-repo https://download.docker.com/linux/fedora/docker-ce.repo 2>/dev/null || true
        sudo $PKG_MANAGER install -y -q docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    fi

    # Demarrer Docker
    sudo systemctl start docker
    sudo systemctl enable docker

    # Ajouter l'utilisateur au groupe docker
    sudo usermod -aG docker $USER
    NEED_NEWGRP=true

    echo -e "${GREEN}✓ Docker installe${NC}"
fi

# =============================================================================
# 3. VERIFICATION DOCKER COMPOSE
# =============================================================================

echo -e "${YELLOW}[3/7] Verification de Docker Compose...${NC}"

# Fonction pour executer docker (avec ou sans sudo)
docker_cmd() {
    if $NEED_NEWGRP || ! docker info &>/dev/null 2>&1; then
        sudo docker "$@"
    else
        docker "$@"
    fi
}

docker_compose_cmd() {
    if $NEED_NEWGRP || ! docker info &>/dev/null 2>&1; then
        sudo docker compose "$@"
    else
        docker compose "$@"
    fi
}

if docker_cmd compose version &>/dev/null; then
    COMPOSE_VERSION=$(docker_cmd compose version --short)
    echo -e "${GREEN}✓ Docker Compose disponible (v$COMPOSE_VERSION)${NC}"
else
    echo -e "${RED}❌ Docker Compose non disponible.${NC}"
    echo "   Reessayez apres redemarrage ou relancez le script."
    exit 1
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

# Verifier make
if ! command -v make &>/dev/null; then
    echo -e "${YELLOW}  Installation de make...${NC}"
    if [[ "$PKG_MANAGER" == "apt" ]]; then
        sudo apt-get install -y -qq make
    else
        sudo $PKG_MANAGER install -y -q make
    fi
fi

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
            docker_compose_cmd down -v --remove-orphans 2>/dev/null || true
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

# Generer des secrets aleatoires
APP_SECRET=$(openssl rand -hex 16)
DB_PASSWORD=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)

# Creer .env a partir de .env.docker avec les valeurs personnalisees
cat > .env << EOF
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
EOF

echo -e "${GREEN}✓ Configuration generee (secrets aleatoires)${NC}"

# =============================================================================
# 6. INSTALLATION
# =============================================================================

echo -e "${YELLOW}[6/7] Installation (peut prendre plusieurs minutes)...${NC}"

# Build de l'image Docker (inclut composer install, tailwind, assets)
echo "  Construction de l'image Docker..."
docker_compose_cmd build --no-cache

# Demarrage des conteneurs
echo "  Demarrage des services..."
docker_compose_cmd up -d

# Attente que PostgreSQL soit pret (via healthcheck)
echo "  Attente de PostgreSQL..."
RETRIES=30
until docker_compose_cmd exec -T postgres pg_isready -U opstracker -d opstracker &>/dev/null || [ $RETRIES -eq 0 ]; do
    echo -n "."
    sleep 2
    RETRIES=$((RETRIES-1))
done
echo ""

if [ $RETRIES -eq 0 ]; then
    echo -e "${RED}❌ PostgreSQL n'a pas demarre a temps.${NC}"
    docker_compose_cmd logs postgres
    exit 1
fi

# Attente que l'application soit prete
echo "  Attente de l'application..."
RETRIES=30
until docker_compose_cmd exec -T app php-fpm-healthcheck &>/dev/null || [ $RETRIES -eq 0 ]; do
    echo -n "."
    sleep 2
    RETRIES=$((RETRIES-1))
done
echo ""

if [ $RETRIES -eq 0 ]; then
    echo -e "${RED}❌ L'application n'a pas demarre a temps.${NC}"
    docker_compose_cmd logs app
    exit 1
fi

# Executer les migrations
echo "  Execution des migrations..."
docker_compose_cmd exec -T app php bin/console doctrine:migrations:migrate --no-interaction

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
    echo "   Vous pourrez creer le compte plus tard avec :"
    echo "   cd $INSTALL_DIR && docker compose exec app php bin/console app:create-admin"
else
    # Creer le compte admin
    docker_compose_cmd exec -T app php bin/console app:create-admin "$ADMIN_EMAIL" "$ADMIN_NOM" "$ADMIN_PRENOM" <<EOF
$ADMIN_PASSWORD
$ADMIN_PASSWORD
EOF

    if [[ $? -eq 0 ]]; then
        echo -e "${GREEN}✓ Compte administrateur cree${NC}"
    else
        echo -e "${YELLOW}⚠ Erreur lors de la creation du compte admin.${NC}"
        echo "   Vous pourrez le creer manuellement avec :"
        echo "   cd $INSTALL_DIR && docker compose exec app php bin/console app:create-admin"
    fi
fi

# =============================================================================
# TERMINE
# =============================================================================

# Recuperer l'IP de la machine
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

if $NEED_NEWGRP; then
    echo -e "${YELLOW}Note: Docker a ete installe. Deconnectez-vous et reconnectez-vous${NC}"
    echo -e "${YELLOW}      pour utiliser docker sans sudo.${NC}"
    echo ""
fi
