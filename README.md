# SVNTeX 2.0 Customer System (v0.2.5)

Foundation plugin for the SVNTeX 2.0 customer / member platform.

## Implemented
* Custom tables (ledger, referrals, KYC, withdrawals, profit distributions, PB payouts) with idempotent activation.
* Wallet ledger with category support (income, topup, withdraw, general) and helpers.
* Referral system: linking, qualification threshold (>= 2400 net excluding shipping), tiered commission via filter, one-time Referral Bonus (RB) on first qualifying order or top-up.
* PB (Profit/Partnership Bonus) slab engine (spend-based, normalized company profit distribution) with activation thresholds (2 provisional / 6 active) and KYC + RB gating.
* Custom login & registration pages with OTP demo flow and AJAX auth.
* Withdrawal requests limited to income balance; automatic fee breakdown (2% TDS + 8% AMC) and ledger reflections (hold, complete, refund).
* Admin dashboard (overview, referrals, KYC, withdrawals, distributions, reports) + CSV export including fee summary.
* Monthly cron scheduling & PB distribution integration (previous month run, transient locks for idempotency).
* REST endpoint for wallet balance and WP-CLI utilities.
* Manual functional test plan (`TESTING.md`).

## Remaining / Next
* PB maintenance lifecycle (12‑month rolling, lifetime status, suspension/reactivation) & suspense payouts when KYC pending.
* Admin UI for monthly profit inputs (currently via filters).
* Automated revenue / remaining wallet computation.
* Real OTP service + secure rate limiting & logging.
* Front-end withdrawal & wallet top-up UI.
* Automated test suite (PHPUnit) for slabs, fees, distribution normalization.
* Security hardening (encryption of sensitive KYC data, audit logging, permissions review).

## Development Quick Start
1. Clone repo into `wp-content/plugins/svntex2-plugin`.
2. Activate plugin in WP admin.
3. Create a page with `[svntex_registration]` for signup.
4. Create a page with `[svntex_dashboard]` for member area.
5. (Optional) Add `[svntex_wallet_history]` for quick ledger view.
6. Use WP-CLI tests (example):
	- `wp svntex2:wallet:add 1 50`
	- `wp svntex2:wallet:last 1`

### Local Testing Shortcode
`[svntex_debug]` (only visible to admins) prints current user balance + qualified referrals.

### Deployment (GitHub Actions -> Hostinger via SSH)
Secrets required:
- `SSH_PRIVATE_KEY`: private key matching an authorized public key on the Hostinger account user.

Workflow: `.github/workflows/deploy_ssh.yml` runs on every push to `main` or manual dispatch and rsyncs plugin files (excludes nested legacy folder / .git).

If files missing remotely, confirm:
1. Secret present and not expired.
2. Public key exists in `~/.ssh/authorized_keys` on server.
3. Action log shows rsync file list (no permission errors).
4. Correct target path: `wp-content/plugins/svntex2-plugin/`.

Shortcodes:
```
[svntex_registration]
[svntex_dashboard]
```

## Notes / Security
- Sensitive fields are hashed (KYC partial). Add encryption at rest later.
- OTP is demo (transient only). Replace with SMS/email gateway.
- Add nonce + capability checks before exposing more REST endpoints.
- Ensure server file permissions keep private key secret out of repo.

---
Version: 0.2.5

## Clean File Structure
```
svntex2-plugin/
	svntex2-plugin.php        (main entry)
	README.md
	assets/
		css/style.css
		js/{auth.js,core.js,dashboard.js}
	includes/
		classes/
			auth.php
		functions/
			admin.php
			cli.php
			cron.php
			helpers.php
			kyc.php
			referrals.php
			withdrawals.php
			rest.php
			shortcodes.php
	views/
		dashboard.php
	legacy/  (old prototype, safe to remove in production)
		customer-auth.js
		customer-login.php
		customer-registration.php
		customer-ui.css
		customer-schema.sql
	scripts/
		clean_plugin.sh
```

The `legacy/` folder holds earlier prototype assets no longer used by the active plugin. They can be deleted once confirmed unnecessary.

## WooCommerce Integration Points
- `svntex2_plugin.php` helper `svntex2_get_recent_orders()` uses `wc_get_orders()` (graceful if WC inactive).
- Dashboard view lists recent orders & pricing via `wc_price()` if available.
- Future modules will hook into order completion (e.g., referral qualification, PB/RB credits) using `woocommerce_order_status_completed`.

## Next Refactor Targets
- Introduce service classes for wallet, referral, kyc for cleaner separation.
- Namespace functions to avoid global pollution (future `SVNTEX2\\` namespace).
- Add PHPUnit tests for helpers (wallet arithmetic, referral linking) under a new `tests/` directory.
