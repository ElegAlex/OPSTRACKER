#!/bin/bash

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Fonction pour afficher les erreurs et quitter
error_exit() {
    echo -e "${RED}[ERREUR] $1${NC}"
    exit 1
}

echo ""
echo "======================================================"
echo "       OpsTracker - Installation automatique          "
echo "======================================================"
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

echo -e "${GREEN}[OK] Systeme : $DISTRO${NC}"

# =============================================================================
# 2. INSTALLATION DOCKER
# =============================================================================

echo -e "${YELLOW}[2/7] Verification de Docker...${NC}"

USE_SUDO=""

if command -v docker &>/dev/null; then
    DOCKER_VERSION=$(docker --version | cut -d' ' -f3 | tr -d ',')
    echo -e "${GREEN}[OK] Docker deja installe (v$DOCKER_VERSION)${NC}"

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

    echo -e "${GREEN}[OK] Docker installe${NC}"
    echo -e "${YELLOW}  Note: Utilisez 'newgrp docker' ou reconnectez-vous pour docker sans sudo${NC}"
fi

# =============================================================================
# 3. VERIFICATION DOCKER COMPOSE
# =============================================================================

echo -e "${YELLOW}[3/7] Verification de Docker Compose...${NC}"

# Definir la commande docker compose
DOCKER_COMPOSE="$USE_SUDO docker compose"

if docker compose version &>/dev/null; then
    COMPOSE_VERSION=$(docker compose version --short 2>/dev/null)
    echo -e "${GREEN}[OK] Docker Compose disponible (v$COMPOSE_VERSION)${NC}"
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

echo -e "${GREEN}[OK] Dependances verifiees${NC}"

# =============================================================================
# 4. NETTOYAGE ET CLONAGE DU REPO
# =============================================================================

echo -e "${YELLOW}[4/7] Telechargement d'OpsTracker...${NC}"

INSTALL_DIR="${INSTALL_DIR:-/opt/opstracker}"

# Nettoyage d'une installation precedente
if [[ -d "$INSTALL_DIR" ]]; then
    echo -e "${YELLOW}  Nettoyage de l'installation precedente...${NC}"

    # Arreter et supprimer les conteneurs/volumes
    cd /
    if [[ -f "$INSTALL_DIR/docker-compose.yml" ]]; then
        cd "$INSTALL_DIR"
        $USE_SUDO docker compose down -v --remove-orphans 2>/dev/null || true
        cd /
    fi

    # Arreter TOUS les conteneurs opstracker (au cas ou ils viennent d'ailleurs)
    for container in $($USE_SUDO docker ps -aq --filter "name=opstracker" 2>/dev/null); do
        $USE_SUDO docker rm -f "$container" 2>/dev/null || true
    done

    # Supprimer TOUS les volumes Docker lies a opstracker (avec differents prefixes)
    for volume in $($USE_SUDO docker volume ls -q 2>/dev/null | grep -i "opstracker"); do
        $USE_SUDO docker volume rm -f "$volume" 2>/dev/null || true
    done

    # Supprimer le dossier
    sudo rm -rf "$INSTALL_DIR"

    echo -e "${GREEN}[OK] Installation precedente nettoyee${NC}"
else
    # Meme sans dossier existant, nettoyer d'eventuels volumes orphelins
    for volume in $($USE_SUDO docker volume ls -q 2>/dev/null | grep -i "opstracker"); do
        echo -e "${YELLOW}  Suppression du volume orphelin: $volume${NC}"
        $USE_SUDO docker volume rm -f "$volume" 2>/dev/null || true
    done
fi

# Cloner le repo
sudo git clone --depth 1 https://github.com/ElegAlex/OPSTRACKER.git "$INSTALL_DIR"
sudo chown -R $USER:$USER "$INSTALL_DIR"
cd "$INSTALL_DIR"

echo -e "${GREEN}[OK] OpsTracker telecharge dans $INSTALL_DIR${NC}"

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

# Session cookie (false pour HTTP, true pour HTTPS)
COOKIE_SECURE=false
ENVEOF

echo -e "${GREEN}[OK] Configuration generee (secrets aleatoires)${NC}"

# =============================================================================
# 6. INSTALLATION
# =============================================================================

echo -e "${YELLOW}[6/7] Installation (peut prendre plusieurs minutes)...${NC}"

echo "  Construction de l'image Docker..."
$USE_SUDO docker compose build --no-cache || error_exit "Echec du build Docker"

echo "  Demarrage des services..."
$USE_SUDO docker compose up -d || error_exit "Echec du demarrage des services"

# Attente que les conteneurs soient healthy
echo "  Attente que les services soient prets..."
MAX_WAIT=180
WAITED=0
while [[ $WAITED -lt $MAX_WAIT ]]; do
    # Verifier que le conteneur app est healthy
    APP_STATUS=$($USE_SUDO docker compose ps app --format '{{.Health}}' 2>/dev/null || echo "unknown")
    if [[ "$APP_STATUS" == "healthy" ]]; then
        echo -e "${GREEN}  Services prets${NC}"
        break
    fi
    sleep 5
    WAITED=$((WAITED + 5))
    echo "  ... attente ($WAITED/$MAX_WAIT sec) - status: $APP_STATUS"
done

if [[ $WAITED -ge $MAX_WAIT ]]; then
    echo -e "${YELLOW}  [WARN] Timeout atteint, tentative de continuer...${NC}"
fi

# Attente supplementaire pour que PostgreSQL soit completement pret
echo "  Verification de la connexion PostgreSQL..."
PG_READY=false
for i in 1 2 3 4 5 6 7 8 9 10 11 12; do
    if $USE_SUDO docker compose exec -T app php bin/console dbal:run-sql "SELECT 1" < /dev/null &>/dev/null; then
        echo -e "${GREEN}  PostgreSQL pret${NC}"
        PG_READY=true
        break
    fi
    echo "  ... attente PostgreSQL ($i/12)"
    sleep 5
done

if [[ "$PG_READY" != "true" ]]; then
    echo -e "${YELLOW}  [WARN] PostgreSQL peut ne pas etre pret${NC}"
fi

# =============================================================================
# 7. MIGRATIONS ET CREATION ADMIN
# =============================================================================

echo -e "${YELLOW}[7/7] Configuration de la base de donnees...${NC}"

# Migrations avec retry
echo "  Execution des migrations Doctrine..."
MIGRATION_SUCCESS=false
for attempt in 1 2 3; do
    echo "  Tentative $attempt..."
    if $USE_SUDO docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction < /dev/null 2>&1; then
        MIGRATION_SUCCESS=true
        echo -e "${GREEN}  [OK] Migrations executees${NC}"
        break
    else
        echo -e "${YELLOW}  [WARN] Tentative $attempt echouee, nouvel essai dans 5s...${NC}"
        sleep 5
    fi
done

if [[ "$MIGRATION_SUCCESS" != "true" ]]; then
    echo -e "${RED}  [ERREUR] Les migrations ont echoue. Verifiez manuellement :${NC}"
    echo "    cd /opt/opstracker && docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction"
fi

# Creation admin
echo "  Creation du compte administrateur..."
ADMIN_OUTPUT=$($USE_SUDO docker compose exec -T app php bin/console app:create-admin \
    admin@opstracker.local \
    Admin \
    Admin \
    --password='Admin123!' < /dev/null 2>&1)
ADMIN_EXIT=$?

if [[ $ADMIN_EXIT -eq 0 ]]; then
    echo -e "${GREEN}  [OK] Compte administrateur cree${NC}"
elif echo "$ADMIN_OUTPUT" | grep -qi "existe\|already\|duplicate"; then
    echo -e "${YELLOW}  [INFO] Le compte admin existe deja${NC}"
else
    echo -e "${YELLOW}  [WARN] Creation admin: $ADMIN_OUTPUT${NC}"
fi

# =============================================================================
# SUCCES
# =============================================================================

IP=$(hostname -I 2>/dev/null | awk '{print $1}')

echo ""
echo "======================================================"
echo "       OpsTracker installe avec succes !              "
echo "======================================================"
echo ""
echo -e "${CYAN}  URL locale :    http://localhost${NC}"
echo -e "${CYAN}  URL reseau :    http://$IP${NC}"
echo ""
echo "  Identifiants par defaut :"
echo -e "${GREEN}    Email :       admin@opstracker.local${NC}"
echo -e "${GREEN}    Mot de passe: Admin123!${NC}"
echo ""
echo -e "${RED}  /!\\ IMPORTANT: Changez le mot de passe admin immediatement !${NC}"
echo ""
echo "  Commandes utiles :"
echo "    cd /opt/opstracker"
echo "    docker compose logs -f      # Voir les logs"
echo "    docker compose restart      # Redemarrer"
echo "    docker compose down         # Arreter"
echo ""
