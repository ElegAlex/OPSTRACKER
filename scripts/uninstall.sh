#!/bin/bash
set -e

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║           OpsTracker - Desinstallation                    ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

INSTALL_DIR="${INSTALL_DIR:-/opt/opstracker}"

if [[ ! -d "$INSTALL_DIR" ]]; then
    echo -e "${YELLOW}OpsTracker non trouve dans $INSTALL_DIR${NC}"
    echo ""
    read -p "Specifier un autre chemin ? (laisser vide pour annuler) : " CUSTOM_DIR
    if [[ -z "$CUSTOM_DIR" ]]; then
        echo "Desinstallation annulee."
        exit 0
    fi
    INSTALL_DIR="$CUSTOM_DIR"
fi

if [[ ! -d "$INSTALL_DIR" ]]; then
    echo -e "${RED}❌ Dossier $INSTALL_DIR introuvable.${NC}"
    exit 1
fi

echo -e "${YELLOW}⚠ Cette operation va :${NC}"
echo "  - Arreter et supprimer les conteneurs Docker"
echo "  - Supprimer les volumes (base de donnees)"
echo "  - Supprimer le dossier $INSTALL_DIR"
echo ""
read -p "Continuer ? (o/N) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Oo]$ ]]; then
    echo "Desinstallation annulee."
    exit 0
fi

echo ""
echo -e "${YELLOW}Arret des conteneurs...${NC}"
cd "$INSTALL_DIR"
docker compose down -v --remove-orphans 2>/dev/null || sudo docker compose down -v --remove-orphans 2>/dev/null || true

echo -e "${YELLOW}Suppression du dossier...${NC}"
cd /
sudo rm -rf "$INSTALL_DIR"

echo ""
echo -e "${GREEN}✅ OpsTracker desinstalle${NC}"
echo ""
echo "Note: Docker n'a pas ete desinstalle."
echo "Pour desinstaller Docker : sudo apt remove docker-ce docker-ce-cli containerd.io"
echo ""
