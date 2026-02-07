#!/bin/bash

# Script de test manuel pour le microservice d'onboarding
# Usage: ./test_script.sh <master_key>

BASE_URL="http://127.0.0.1:8000"
MASTER_KEY="${1:-}"

if [ -z "$MASTER_KEY" ]; then
    echo "Usage: ./test_script.sh <master_key>"
    echo "Exemple: ./test_script.sh mk_abc123..."
    exit 1
fi

echo "=========================================="
echo "Tests Manuels - Microservice Onboarding"
echo "=========================================="
echo ""

# Test 1: Créer un onboarding
echo "Test 1: Création d'onboarding"
echo "----------------------------"
UUID=$(curl -s -X POST "${BASE_URL}/api/v1/onboarding/start" \
  -H "X-Master-Key: ${MASTER_KEY}" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test-manual-'$(date +%s)'@example.com",
    "organization_name": "Test Manuel '$(date +%s)'"
  }' | jq -r '.uuid')

if [ "$UUID" != "null" ] && [ -n "$UUID" ]; then
    echo "✅ Onboarding créé: ${UUID}"
else
    echo "❌ Échec de création"
    exit 1
fi

echo ""

# Test 2: Vérifier le statut
echo "Test 2: Vérification du statut"
echo "-------------------------------"
STATUS=$(curl -s -X GET "${BASE_URL}/api/v1/onboarding/status/${UUID}" \
  -H "X-Master-Key: ${MASTER_KEY}" | jq -r '.onboarding_status')

if [ "$STATUS" = "pending" ]; then
    echo "✅ Statut correct: ${STATUS}"
else
    echo "❌ Statut incorrect: ${STATUS}"
fi

echo ""

# Test 3: Provisioning
echo "Test 3: Provisioning"
echo "--------------------"
PROVISION_RESULT=$(curl -s -X POST "${BASE_URL}/api/v1/onboarding/provision" \
  -H "X-Master-Key: ${MASTER_KEY}" \
  -H "Content-Type: application/json" \
  -d "{
    \"uuid\": \"${UUID}\",
    \"generate_api_key\": true
  }")

PROVISION_STATUS=$(echo $PROVISION_RESULT | jq -r '.onboarding_status')
IS_IDEMPOTENT=$(echo $PROVISION_RESULT | jq -r '.metadata.is_idempotent // false')

if [ "$PROVISION_STATUS" = "activated" ]; then
    echo "✅ Provisioning réussi: ${PROVISION_STATUS}"
    echo "   Idempotent: ${IS_IDEMPOTENT}"
else
    echo "⚠️  Provisioning: ${PROVISION_STATUS}"
fi

echo ""

# Test 4: Test d'idempotence
echo "Test 4: Test d'idempotence"
echo "--------------------------"
IDEMPOTENT_RESULT=$(curl -s -X POST "${BASE_URL}/api/v1/onboarding/provision" \
  -H "X-Master-Key: ${MASTER_KEY}" \
  -H "Content-Type: application/json" \
  -d "{
    \"uuid\": \"${UUID}\"
  }")

IDEMPOTENT_FLAG=$(echo $IDEMPOTENT_RESULT | jq -r '.metadata.is_idempotent // false')
API_KEY=$(echo $IDEMPOTENT_RESULT | jq -r '.api_key // "null"')

if [ "$IDEMPOTENT_FLAG" = "true" ] && [ "$API_KEY" = "null" ]; then
    echo "✅ Idempotence fonctionne correctement"
else
    echo "⚠️  Idempotence: is_idempotent=${IDEMPOTENT_FLAG}, api_key=${API_KEY}"
fi

echo ""

# Test 5: Vérifier les metadata
echo "Test 5: Vérification des metadata"
echo "----------------------------------"
METADATA=$(curl -s -X GET "${BASE_URL}/api/v1/onboarding/status/${UUID}" \
  -H "X-Master-Key: ${MASTER_KEY}" | jq '.metadata')

if [ "$(echo $METADATA | jq -r '.infrastructure_status')" != "null" ]; then
    echo "✅ Metadata présentes:"
    echo "$METADATA" | jq '.'
else
    echo "❌ Metadata manquantes"
fi

echo ""
echo "=========================================="
echo "Tests terminés"
echo "=========================================="
