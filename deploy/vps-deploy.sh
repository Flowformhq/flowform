#!/bin/bash
# VPS Deploy Script for FlowFormHQ
# Run this on your VPS after the repo is cloned and static files are built

set -e

DEPLOY_DIR="/opt/AWS/deploy/flowformhq"
COMPOSE_FILE="$DEPLOY_DIR/deploy/docker-compose.prod.yml"

echo "=========================================="
echo "FlowFormHQ VPS Deploy"
echo "=========================================="
echo ""

# Create deploy directory if it doesn't exist
if [ ! -d "$DEPLOY_DIR" ]; then
    echo "Creating deploy directory: $DEPLOY_DIR"
    mkdir -p "$DEPLOY_DIR"
fi

cd "$DEPLOY_DIR"

# Check if static files exist
if [ ! -d "$DEPLOY_DIR/deploy/marketing" ]; then
    echo "ERROR: deploy/marketing/ directory not found."
    echo "Make sure the static sites are built and copied to:"
    echo "  $DEPLOY_DIR/deploy/marketing/"
    echo "  $DEPLOY_DIR/deploy/docs/"
    exit 1
fi

if [ ! -d "$DEPLOY_DIR/deploy/docs" ]; then
    echo "ERROR: deploy/docs/ directory not found."
    exit 1
fi

# Check if .env exists (for engine)
if [ ! -f "$DEPLOY_DIR/deploy/.env" ]; then
    echo "WARNING: deploy/.env not found. Engine deployment will fail."
    echo "Copy deploy/.env.example to deploy/.env and fill in real values."
    echo "Continuing with static sites only..."
fi

# Check networks exist
echo "Checking Docker networks..."
if ! docker network ls | grep -q "traefik-public"; then
    echo "ERROR: traefik-public network not found."
    echo "Make sure Traefik is running and the network is created."
    exit 1
fi

if ! docker network ls | grep -q "backend"; then
    echo "WARNING: backend network not found."
    echo "Engine deployment will fail without it."
fi

# Deploy
echo ""
echo "Starting containers..."
docker compose -f "$COMPOSE_FILE" up -d --build --remove-orphans

# Run migrations if engine is deployed
if [ -f "$DEPLOY_DIR/deploy/.env" ]; then
    echo ""
    echo "Running database migrations..."
    sleep 5
    docker exec flowform-engine php artisan migrate --force || true
    
    echo ""
    echo "Optimizing Laravel..."
    docker exec flowform-engine php artisan config:cache || true
    docker exec flowform-engine php artisan route:cache || true
    docker exec flowform-engine php artisan view:cache || true
fi

echo ""
echo "=========================================="
echo "Deployment complete!"
echo "=========================================="
echo ""
echo "Check status:"
echo "  docker compose -f $COMPOSE_FILE ps"
echo ""
echo "Check logs:"
echo "  docker compose -f $COMPOSE_FILE logs -f"
echo ""
echo "Once DNS is pointed, verify with:"
echo "  curl -I https://flowformhq.com"
echo "  curl -I https://docs.flowformhq.com"
echo "  curl -I https://demo.flowformhq.com"
echo ""
