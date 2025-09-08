#!/usr/bin/env bash
set -euo pipefail
DC="docker compose -f dev/docker-compose.yml"

# Helpers
wp() { $DC exec -T wordpress wp "$@"; }

echo "[1] Create referrer user (if missing)"
if ! wp user get referrer >/dev/null 2>&1; then wp user create referrer referrer@example.com --user_pass=Passw0rd! --role=customer; fi

REF_ID=$(wp user get referrer --field=ID)
echo "Referrer ID: $REF_ID"

echo "[2] Create first referee with referral_source meta and place order (qualifying purchase)"
if ! wp user get referee1 >/dev/null 2>&1; then wp user create referee1 referee1@example.com --user_pass=Passw0rd! --role=customer; fi
REF1_ID=$(wp user get referee1 --field=ID)
wp user meta update $REF1_ID referral_source referrer

# Create order for referee1 (3000 net) and complete -> triggers referral + RB
PROD_ID=$(wp post list --post_type=product --field=ID | head -n1)
ORDER_JSON=$(wp wc order create --user=$REF1_ID --status=pending --line_items='[{"product_id":'"$PROD_ID"',"quantity":1}]')
ORDER_ID=$(echo "$ORDER_JSON" | php -r '$o=json_decode(stream_get_contents(STDIN),true);echo $o["id"]??"";')
wp wc order update $ORDER_ID --status=completed >/dev/null
echo "Order $ORDER_ID completed for referee1"

echo "[3] Show wallet transactions for referrer (expect referral_commission + referral_bonus)"
wp db query "SELECT id,type,amount,reference_id,created_at FROM wp_svntex_wallet_transactions WHERE user_id=$REF_ID ORDER BY id DESC LIMIT 5" --skip-column-names

echo "[4] Simulate additional 5 referrals to reach Active (create users + orders)"
for n in 2 3 4 5 6; do
  U=referee$n
  if ! wp user get $U >/dev/null 2>&1; then wp user create $U $U@example.com --user_pass=Passw0rd! --role=customer; fi
  UID=$(wp user get $U --field=ID)
  wp user meta update $UID referral_source referrer
  OJSON=$(wp wc order create --user=$UID --status=pending --line_items='[{"product_id":'"$PROD_ID"',"quantity":1}]')
  OID=$(echo "$OJSON" | php -r '$o=json_decode(stream_get_contents(STDIN),true);echo $o["id"]??"";')
  wp wc order update $OID --status=completed >/dev/null
  echo "Referral $n order $OID complete"
done

echo "[5] Check PB status + cycle meta for referrer"
wp user meta get $REF_ID _svntex2_pb_status || true
wp user meta get $REF_ID _svntex2_pb_cycle_ref_total || true

echo "[6] Insert profit inputs for LAST month and run distribution"
LAST_MONTH=$(date -v-1m +%Y-%m 2>/dev/null || date -d "-1 month" +%Y-%m)
wp db query "INSERT INTO wp_svntex_profit_inputs (month_year,revenue,remaining_wallet,cogs,maintenance_percent,notes,created_at) VALUES ('$LAST_MONTH',100000,5000,30000,0.05,'Test run',NOW()) ON DUPLICATE KEY UPDATE revenue=VALUES(revenue)" || true

echo "Trigger monthly distribution (runs for last month)"
wp eval 'do_action("svntex2_monthly_distribution"); echo "Triggered distribution\n";'

echo "[7] Show PB payouts or suspense entries for referrer"
wp db query "SELECT * FROM wp_svntex_pb_payouts WHERE user_id=$REF_ID ORDER BY id DESC LIMIT 5" || true
wp db query "SELECT * FROM wp_svntex_pb_suspense WHERE user_id=$REF_ID ORDER BY id DESC LIMIT 5" || true

echo "[8] Done"
