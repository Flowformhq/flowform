#!/bin/bash
# VPS Deployment Audit Script for FlowFormHQ
# Run this on your VPS and send the output back

set -e

echo "=========================================="
echo "VPS DEPLOYMENT AUDIT"
echo "=========================================="
echo ""

echo "--- 1. SYSTEM INFO ---"
hostname
uname -a
echo ""

echo "--- 2. DOCKER INFO ---"
docker --version
docker-compose --version 2>/dev/null || docker compose version
echo ""

echo "--- 3. RUNNING CONTAINERS ---"
docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Ports}}\t{{.Status}}"
echo ""

echo "--- 4. DOCKER NETWORKS ---"
docker network ls
echo ""

echo "--- 5. DEPLOY DIRECTORY STRUCTURE ---"
ls -la /opt/AWS/deploy/
echo ""

echo "--- 6. TRAEFIK CONFIG (if exists) ---"
if [ -f "/opt/AWS/deploy/docker-compose.yml" ]; then
    echo "Found docker-compose.yml:"
    cat /opt/AWS/deploy/docker-compose.yml
elif [ -f "/opt/AWS/deploy/traefik/docker-compose.yml" ]; then
    echo "Found traefik/docker-compose.yml:"
    cat /opt/AWS/deploy/traefik/docker-compose.yml
else
    echo "Searching for traefik configs..."
    find /opt/AWS/deploy -name "traefik*" -o -name "*traefik*" 2>/dev/null | head -20
fi
echo ""

echo "--- 7. TRAEFIK DYNAMIC CONFIGS ---"
find /opt/AWS/deploy -name "*.toml" -o -name "*.yaml" -o -name "*.yml" 2>/dev/null | grep -i traefik | head -20
echo ""

echo "--- 8. EXISTING SSL CERTS ---"
ls -la /opt/AWS/deploy/ 2>/dev/null | grep -E "acme|certs|ssl|letsencrypt" || echo "No cert dirs in /opt/AWS/deploy/"
find /opt/AWS/deploy -name "acme.json" 2>/dev/null | head -5
echo ""

echo "--- 9. EXPOSED PORTS ---"
ss -tlnp | grep -E ":(80|443|8080|8443)" || netstat -tlnp 2>/dev/null | grep -E ":(80|443|8080|8443)" || echo "No ss/netstat available"
echo ""

echo "--- 10. DISK SPACE ---"
df -h /opt
echo ""

echo "--- 11. MEMORY ---"
free -h 2>/dev/null || vm_stat 2>/dev/null || echo "Memory info unavailable"
echo ""

echo "--- 12. DOMAIN DNS CHECK ---"
if command -v dig &> /dev/null; then
    dig +short flowformhq.com
    dig +short docs.flowformhq.com 2>/dev/null || echo "docs.flowformhq.com not configured"
    dig +short demo.flowformhq.com 2>/dev/null || echo "demo.flowformhq.com not configured"
    dig +short app.flowformhq.com 2>/dev/null || echo "app.flowformhq.com not configured"
elif command -v host &> /dev/null; then
    host flowformhq.com
else
    echo "No dig/host available"
fi
echo ""

echo "=========================================="
echo "AUDIT COMPLETE"
echo "=========================================="
