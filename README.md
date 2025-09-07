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
