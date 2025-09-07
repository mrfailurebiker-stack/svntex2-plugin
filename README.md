# SVNTeX 2.0 Customer System (Phase 1 Skeleton)

This repository contains the initial scaffolding for the SVNTeX 2.0 WordPress plugin implementing:

## Included
- Activation hook with custom tables (wallet, referrals, KYC, withdrawals, profit distributions, PB payouts)
- Shortcodes: `[svntex_registration]`, `[svntex_dashboard]`
- Minimal registration flow (email+mobile+password+referral)
- Wallet transaction helper + REST endpoint: `GET /wp-json/svntex2/v1/wallet/balance`
- Basic dashboard view & styling

## Next Phases
- OTP & multi-channel login
- Wallet top-up, PB/RB credit engines
- Referral qualification & PB maintenance logic
- KYC submission & admin workflow
- Withdrawal request & admin approval UI
- Monthly profit distribution scheduler

## Development
Place this folder in `wp-content/plugins/` then activate **SVNTeX 2.0 Customer System**.

Shortcodes:
```
[svntex_registration]
[svntex_dashboard]
```

## Notes
Data retention policy & security hardening still pending (encryption, hashing of sensitive numbers, audit logs).

---
Version: 0.1.0
