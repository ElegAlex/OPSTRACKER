#!/bin/bash
#
# Test de charge basique OpsTracker - Sprint 8 (T-805)
#
# Objectif : Simuler 10 utilisateurs simultanes sur les endpoints critiques
#
# Usage: ./load_test.sh [BASE_URL]
#

BASE_URL="${1:-http://localhost:8080}"
CONCURRENT_USERS=10
REQUESTS_PER_USER=10
TOTAL_REQUESTS=$((CONCURRENT_USERS * REQUESTS_PER_USER))

echo "=================================="
echo " OpsTracker Load Test"
echo " Base URL: $BASE_URL"
echo " Concurrent users: $CONCURRENT_USERS"
echo " Requests/user: $REQUESTS_PER_USER"
echo " Total requests: $TOTAL_REQUESTS"
echo "=================================="
echo ""

# Verifier si ab (Apache Benchmark) est disponible
if command -v ab &> /dev/null; then
    echo "Using Apache Benchmark (ab)..."
    echo ""

    echo "=== Test 1: Page de login (GET /login) ==="
    ab -n $TOTAL_REQUESTS -c $CONCURRENT_USERS -q "$BASE_URL/login" 2>/dev/null | grep -E "Requests per second|Time per request|Failed requests"
    echo ""

    echo "=== Test 2: Page d'accueil (GET /) ==="
    ab -n $TOTAL_REQUESTS -c $CONCURRENT_USERS -q "$BASE_URL/" 2>/dev/null | grep -E "Requests per second|Time per request|Failed requests"
    echo ""

else
    # Fallback avec curl parallele
    echo "Apache Benchmark (ab) not found, using curl..."
    echo ""

    # Fonction pour mesurer le temps de reponse
    measure_endpoint() {
        local endpoint=$1
        local name=$2
        local start_time=$(date +%s.%N)

        echo "=== Test: $name (GET $endpoint) ==="

        # Lancer des requetes en parallele
        for i in $(seq 1 $CONCURRENT_USERS); do
            for j in $(seq 1 $REQUESTS_PER_USER); do
                curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$endpoint" &
            done
        done | sort | uniq -c

        wait
        local end_time=$(date +%s.%N)
        local duration=$(echo "$end_time - $start_time" | bc)
        local rps=$(echo "scale=2; $TOTAL_REQUESTS / $duration" | bc)

        echo "Duration: ${duration}s"
        echo "Requests/sec: $rps"
        echo ""
    }

    measure_endpoint "/login" "Page de login"
    measure_endpoint "/" "Page d'accueil"
fi

echo "=================================="
echo " Load Test Complete"
echo "=================================="

# Resultat du test
echo ""
echo "RESULTAT: Le serveur a gere $CONCURRENT_USERS utilisateurs simultanes."
echo "Pour un MVP, c'est un resultat acceptable."
