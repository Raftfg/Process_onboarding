#!/bin/bash

# Script de test pour le processus d'onboarding
# Usage: ./test-onboarding.sh [--clean]

echo "🧪 Test du processus d'onboarding MedKey"
echo "========================================"
echo ""

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Vérifier que Laravel est installé
if [ ! -f "artisan" ]; then
    echo -e "${RED}❌ Erreur: Ce script doit être exécuté depuis la racine du projet Laravel${NC}"
    exit 1
fi

# Exécuter les tests
echo -e "${YELLOW}▶️  Exécution des tests d'onboarding...${NC}"
echo ""

if php artisan test:onboarding "$@"; then
    echo ""
    echo -e "${GREEN}✅ Tous les tests sont passés avec succès!${NC}"
    exit 0
else
    echo ""
    echo -e "${RED}❌ Certains tests ont échoué${NC}"
    exit 1
fi
