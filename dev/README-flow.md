Local Execution Flow (Docker + WP-CLI)
=====================================

Prereqs: Docker Desktop installed.

Steps:
1. Start environment & install WP
   bash dev/init.sh

2. Run full automated referral + PB test flow
   bash dev/test-flow.sh

3. Open http://localhost:8088/wp-admin (admin / admin) to inspect:
   - Wallet transactions table (DB viewer or custom admin Reports tab)
   - SVNTeX Admin > Distributions (profit inputs & suspense)

What the test does:
 - Creates referrer + 6 referees each purchasing a qualifying product (3000)
 - Triggers referral commission + RB for first referee
 - Accumulates 6 referrals (status should become active)
 - Inserts last month profit inputs and fires monthly distribution action
 - Displays payout or suspense rows

Adjusting distribution mode:
 - In WP Admin > SVNTeX 2.0 > Settings change PB Distribution Mode.
 - Re-run step 6 with a new last month (or adjust LAST_MONTH variable) to compare results.

Manual Checks:
 - wp user meta get <referrer_id> _svntex2_pb_status
 - wp db query "SELECT * FROM wp_svntex_wallet_transactions WHERE user_id=<referrer_id>"

Cleanup:
 docker compose -f dev/docker-compose.yml down -v
