# SVNTeX 2.0 Customer System (v0.2.1)

Foundation plugin for the SVNTeX 2.0 customer / member platform.

## Included (current)
- Activation hook creating custom tables:
	- Wallet ledger
	- Referrals
	- KYC submissions
	- Withdrawals
	- Profit distributions + PB payouts
- Shortcodes:
	- `[svntex_registration]` (OTP demo + registration)
	- `[svntex_dashboard]` (modern dark mode dashboard)
	- `[svntex_wallet_history]` (recent 25 ledger rows)
	- `[svntex_debug]` (admin-only quick stats)
- Wallet ledger helpers (add transaction, balance)
- REST: `GET /wp-json/svntex2/v1/wallet/balance`
- Referrals scaffold (link + qualification placeholder)
- KYC helpers (submit/status)
- Withdrawals scaffold (request + process with hold/refund)
- Monthly distribution cron scaffold
- WP-CLI commands: `svntex2:wallet:add`, `svntex2:wallet:last`, `svntex2:referral:link`
- Admin menu placeholder

## Pending / Next Phases
- Real OTP provider integration & login flow
- Wallet top-up endpoints + UI
- PB/RB slab logic & referral qualification rules
- KYC document upload form + admin review UI
- Withdrawal front-end + admin approval screen
- Profit distribution calculation algorithm (current scaffold only)
- Security hardening (rate limits, encryption, audit log)

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
Version: 0.2.1

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
	legacy/  (old standalone prototype files retained temporarily)
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
