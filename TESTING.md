# SVNTeX 2.0 Plugin – Functional Test Plan

This guide lists manual test scenarios to validate the recent wallet, referral, RB (Referral Bonus), PB (Profit Bonus), withdrawal fees, and reporting updates.

## 1. Environment Setup
Use a clean WordPress + WooCommerce site.
- PHP 8.1+ recommended
- WordPress >= 6.4
- WooCommerce >= 8.x

Quick start (see docker-compose.yml if added):
1. `docker compose up -d`
2. Visit http://localhost:8080 – complete WordPress install.
3. Install & activate WooCommerce (minimal setup wizard).
4. Place `svntex2-plugin` folder into `wp-content/plugins/` (or mount via compose volume).
5. Activate the plugin in WP admin.
6. Visit Settings > Permalinks and click Save once (ensures rewrites refreshed).

## 2. Core Tables & Schema Migrations
[ ] Verify tables exist: `svntex_wallet_transactions`, `svntex_referrals`, `svntex_kyc_submissions`, `svntex_withdrawals`, `svntex_profit_distributions`, `svntex_pb_payouts`.
[ ] Confirm wallet table has `category` column.
[ ] Confirm withdrawals table has `tds_amount`, `amc_amount`, `net_amount` columns.

## 3. Registration & Login (Custom Pages)
[ ] Visit /customer-register/ and create a new user A.
[ ] Ensure customer ID is auto-generated if logic configured.
[ ] Logout; visit /customer-login/ – verify redirect logic & rate limit not triggered.

## 4. Referral Flow & Qualification Threshold (>= 2400 net)
1. While logged in as User A, copy referral ID (mechanism: meta `customer_id` or username – ensure you know expected input for referred user).
2. Open a fresh browser/incognito.
3. Register referred User B using the referral source (set meta `referral_source` manually if UI not yet created).
4. Place WooCommerce order for User B with products subtotal >= 2400 and **shipping excluded**.
   - [ ] Complete payment → mark order Completed (admin if needed).
   - [ ] Confirm in DB/User A wallet: transaction `referral_commission` created (type), category = income.
   - [ ] Confirm referral qualified only if net (subtotal minus shipping) >= 2400.
5. Try order with subtotal 2399 → [ ] should NOT qualify.

## 5. Referral Bonus (RB) – First Qualifying Event
Scenario A (Order first):
[ ] RB triggers on first qualifying order (User B) → User A gets `referral_bonus` (income) once.
Scenario B (Top-up first):
1. Create User C referred by User A.
2. Manually (or via future UI) create wallet top-up transaction for User C: `svntex2_wallet_add_transaction( C_ID, 'wallet_topup', 3000, 'manual', [], 'topup' );`
3. [ ] RB awarded to User A; [ ] referral qualified (threshold met) if top-up >= 2400.
4. Second top-up or order should NOT award RB again.

## 6. Wallet Category Balances
[ ] Insert mixed transactions (income vs topup) and call helper: `svntex2_wallet_get_balances( USER_ID )` via temporary snippet – check keys: total, income, topup, withdraw.
[ ] Withdrawal request must compare **income** category only.

## 7. Withdrawals & Fees
1. Ensure User A has income balance (commissions + bonuses).
2. Call `svntex2_withdraw_request( A_ID, 1000 )` (or use UI if added later).
3. Approve withdrawal: `svntex2_withdraw_process( ID, 'approved')`.
   - [ ] Wallet has `withdraw_hold` then `withdraw_complete` (0 amount final ledger) with meta storing gross/tds/amc/net.
   - [ ] Fees computed: 2% TDS + 8% AMC.
4. Reject path: request then reject – [ ] refund appears as `withdraw_refund`.

## 8. KYC Gate
1. Submit basic KYC for User A (insert row in table or use function `svntex2_kyc_submit`).
2. Set status approved: `svntex2_kyc_set_status( A_ID, 'approved')`.
3. PB payout (later) should only consider KYC approved + RB awarded.

## 9. PB (Profit Bonus) Eligibility Tracking
(Current partial implementation) – Only provisional/active statuses + spend slabs.
1. Create at least 6 qualifying referrals for User A (B,C,D,E,F,G) each with qualifying order/top-up >=2400.
   - [ ] After 2 referrals status becomes `provisional` (meta `_svntex2_pb_status`).
   - [ ] After 6 total status becomes `active`.
2. Simulate monthly spend: add `wallet_topup` and/or `purchase` transactions in prior month.
3. Ensure one refund (`refund` negative) reduces spend.
4. Confirm `svntex2_pb_resolve_slab_percent(spend)` returns expected slab (e.g., 10,499 -> 0.50).

## 10. PB Distribution (Cron)
1. Define temporary filters (in a mu-plugin or theme functions.php):
```
add_filter('svntex2_pb_month_revenue', function($rev,$month){ return 500000; },10,2);
add_filter('svntex2_pb_remaining_wallet_total', function($r,$month){ return 100000; },10,2);
add_filter('svntex2_pb_cogs', function($c,$month){ return 250000; },10,2);
add_filter('svntex2_pb_maintenance_percent', function(){ return 0.05; });
```
2. Run cron manually: `do_action('svntex2_monthly_distribution');`
3. [ ] Check `svntex_profit_distributions` row inserted for previous month.
4. [ ] Check `svntex_pb_payouts` rows for eligible users; payout sum == company_profit (allow rounding off by a few paise due to decimals).
5. [ ] Wallet transactions type `profit_bonus` created.

## 11. Reports Tab
[ ] Navigate Admin → SVNTeX 2.0 → Reports.
[ ] Filter by date range containing test transactions.
[ ] CSV export downloads and includes wallet rows + withdrawal fee summary.

## 12. Edge Cases
[ ] Attempt withdrawal > income balance → error.
[ ] RB awarding attempt after already awarded → no duplicate.
[ ] Referral commission order processed twice (status flip) → second run ignored (meta flag present).
[ ] PB cron re-run (transient lock) → no duplicate payouts.

## 13. Data Integrity Spot Checks
[ ] All financial wallet inserts include `category` as expected.
[ ] JSON-like meta arrays stored serialized safely (LONGTEXT) – inspect sample.
[ ] Negative amounts appear only for withdraw_hold or refund types.

## 14. Pending (Future Implementation Tests) – Mark When Added
- Suspense holding for PB if KYC pending.
- Temporary / permanent block & reactivation logic.
- Lifetime status after year 2 (rolling window logic).
- Admin UI for profit inputs instead of filters.
- Automated job to recompute remaining wallet top-up.

## 15. Suggested Automation Next
- Introduce PHPUnit + WP test suite for: slab resolution, referral qualification threshold, withdrawal fee math, profit normalization.
- Add snapshot of distribution sum equals company_profit.

---
Maintain a checklist log in this file as features mature.
