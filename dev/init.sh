#!/usr/bin/env bash
set -euo pipefail

DC="docker compose -f dev/docker-compose.yml"

echo "[+] Starting containers..."
$DC up -d --build

echo "[+] Waiting for WordPress core files (initial extraction)..."
sleep 20

echo "[+] Installing WP core (if not already) via WP-CLI inside container..."
$DC exec -T wordpress bash -c 'if ! wp core is-installed >/dev/null 2>&1; then wp core install --url="http://localhost:8088" --title="SVNTeX Dev" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email; fi'

echo "[+] Installing WooCommerce (latest) ..."
$DC exec -T wordpress bash -c 'wp plugin install woocommerce --activate --force'

echo "[+] Activating svntex2-plugin ..."
$DC exec -T wordpress bash -c 'wp plugin activate svntex2-plugin'

echo "[+] Create sample simple product (ID reuse if exists) ..."
$DC exec -T wordpress bash -c 'if ! wp post list --post_type=product --field=ID | grep -q 101; then wp wc product create --name="Qualifying Product" --type=simple --regular_price=3000 --user=1 >/dev/null; fi'

echo "[+] Done. Visit: http://localhost:8088/"
