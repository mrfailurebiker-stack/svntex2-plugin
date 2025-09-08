# Changelog

## 0.2.5 - 2025-09-08
- Added PB slab engine integration (spend-based normalized profit distribution).
- Enforced net order qualification (exclude shipping) threshold >= 2400 for referrals.
- Added top-up based referral qualification & RB trigger.
- Gated PB payouts on KYC approval + RB awarded.
- Fixed referral commission undefined variable bug (use net_total base).
- Expanded admin reports (fee summary, CSV export) & updated README.
- Version bump to 0.2.5.

## 0.2.4 - 2025-09-08
- Initial separation of wallet categories and withdrawal fee logic.

## 0.2.3 - 2025-09-07
- Referral bonus (RB) logic scaffolding.

## 0.2.2 - 2025-09-07
- Referral commission slabs & basic reporting.

## 0.2.1 - 2025-09-06
- Core tables, custom auth pages, referrals linking, KYC scaffold, withdrawals scaffold, cron scheduling.
