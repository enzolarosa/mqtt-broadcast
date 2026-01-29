#!/bin/bash
# Helper script for running tests with optional Mosquitto broker

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Functions
print_usage() {
    echo "Usage: ./test.sh [command]"
    echo ""
    echo "Commands:"
    echo "  start       Start Mosquitto and Redis for integration tests"
    echo "  stop        Stop test services"
    echo "  restart     Restart test services"
    echo "  status      Check if services are running"
    echo "  unit        Run unit tests only (no broker required)"
    echo "  integration Run integration tests (requires broker)"
    echo "  all         Run all tests with broker"
    echo "  clean       Stop services and clean up volumes"
    echo ""
    echo "Examples:"
    echo "  ./test.sh start && ./test.sh all"
    echo "  ./test.sh unit"
}

start_services() {
    echo -e "${GREEN}Starting Mosquitto and Redis...${NC}"
    docker compose -f docker-compose.test.yml up -d

    echo -e "${YELLOW}Waiting for services to be ready...${NC}"
    sleep 3

    if docker compose -f docker-compose.test.yml ps | grep -q "Up"; then
        echo -e "${GREEN}✓ Services are running${NC}"
        echo ""
        docker compose -f docker-compose.test.yml ps
    else
        echo -e "${RED}✗ Failed to start services${NC}"
        exit 1
    fi
}

stop_services() {
    echo -e "${YELLOW}Stopping services...${NC}"
    docker compose -f docker-compose.test.yml down
    echo -e "${GREEN}✓ Services stopped${NC}"
}

check_status() {
    echo -e "${YELLOW}Checking services status...${NC}"
    if docker compose -f docker-compose.test.yml ps | grep -q "Up"; then
        echo -e "${GREEN}✓ Services are running${NC}"
        docker compose -f docker-compose.test.yml ps
        echo ""
        echo "Mosquitto: nc -zv 127.0.0.1 1883"
        nc -zv 127.0.0.1 1883 2>&1 | grep -q "succeeded" && echo -e "${GREEN}✓ Port 1883 reachable${NC}" || echo -e "${RED}✗ Port 1883 not reachable${NC}"
    else
        echo -e "${RED}✗ Services are not running${NC}"
        echo "Start them with: ./test.sh start"
    fi
}

run_unit_tests() {
    echo -e "${GREEN}Running unit tests (no broker required)...${NC}"
    vendor/bin/pest --exclude-group=integration "$@"
}

run_integration_tests() {
    if ! nc -zv 127.0.0.1 1883 2>&1 | grep -q "succeeded"; then
        echo -e "${RED}✗ Mosquitto broker not available on port 1883${NC}"
        echo "Start it with: ./test.sh start"
        exit 1
    fi

    echo -e "${GREEN}Running integration tests...${NC}"
    MQTT_BROKER_AVAILABLE=1 vendor/bin/pest --group=integration "$@"
}

run_all_tests() {
    if ! nc -zv 127.0.0.1 1883 2>&1 | grep -q "succeeded"; then
        echo -e "${RED}✗ Mosquitto broker not available on port 1883${NC}"
        echo "Start it with: ./test.sh start"
        exit 1
    fi

    echo -e "${GREEN}Running all tests with real broker...${NC}"
    MQTT_BROKER_AVAILABLE=1 vendor/bin/pest "$@"
}

clean_services() {
    echo -e "${YELLOW}Stopping services and cleaning volumes...${NC}"
    docker compose -f docker-compose.test.yml down -v
    echo -e "${GREEN}✓ Cleanup complete${NC}"
}

# Main
case "${1:-}" in
    start)
        start_services
        ;;
    stop)
        stop_services
        ;;
    restart)
        stop_services
        start_services
        ;;
    status)
        check_status
        ;;
    unit)
        shift
        run_unit_tests "$@"
        ;;
    integration)
        shift
        run_integration_tests "$@"
        ;;
    all)
        shift
        run_all_tests "$@"
        ;;
    clean)
        clean_services
        ;;
    "")
        run_unit_tests
        ;;
    *)
        echo -e "${RED}Unknown command: $1${NC}"
        echo ""
        print_usage
        exit 1
        ;;
esac
