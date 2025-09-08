# SVNTeX 2.0 – Pending Feature Logic & Implementation Roadmap

This document enumerates the NOT YET DEVELOPED or PARTIAL modules, required logic details, and proposed implementation approach.

## Legend
- Status: `P` (Planned), `S` (Scaffold exists), `W` (Work in progress), `B` (Blocked dependency)

## 1. PB Lifecycle & Governance
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Rolling 12‑Month Referral Maintenance | P | Only activation (2 => provisional, 6 => active) implemented. No decay or window. | Track monthly referral qualification counts; cron job recalculates last 12 months. If < threshold (e.g., <4 after first activation year) set status `suspended` until regained. |
| Lifetime Status | P | No lifetime escalation. | If user maintains `active` for 24 consecutive months mark `lifetime`. Store `_svntex2_pb_life_start` timestamp and bypass maintenance demotion except for fraud flags. |
| Suspension & Reactivation | P | Not present. | On demotion rules trigger, set `_svntex2_pb_status` to `suspended`; hold PB payouts (log to suspense). Reactivate when criteria met next cron. |
| Suspense Pool Handling | P | None. | If user otherwise eligible but status not `active/lifetime` (e.g., suspended, KYC pending) park computed payout in `svntex_pb_suspense` table for later release. |
| PB Eligibility Cache | P | Derived on the fly. | Store monthly snapshot: spend, slab, payout share for audit + faster dashboards. |

## 2. Profit Input & Calculation UI
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Admin Profit Entry Form | P | Only filter-based injection. | Admin tab form with month selector + revenue, COGS, maintenance% fields stored in `svntex_profit_inputs` table. |
| Automated Remaining Wallet Computation | P | Filter placeholder. | Cron sums positive top-ups minus withdrawals and purchases to estimate liability. Allow manual override. |
| Switch Between Normalized vs Slab Formula | P | Only normalized percent share in `pb.php`. | Config option: mode `normalized` vs `direct_formula` (profit * slab%). Add option page & filter `svntex2_pb_distribution_mode`. |

## 3. Wallet Front-End Enhancements
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Customer Top-Up UI | P | No interface. | Shortcode `[svntex_topup]` integrating WC product or payment gateway to create `wallet_topup` transaction post success webhook. |
| Customer Withdrawal UI | P | Only backend functions. | Add form (income balance display, min/max, fee preview). AJAX endpoint validates & calls `svntex2_withdraw_request`. |
| Transaction History Explorer | P | Dashboard only shows balance. | Paginated REST `/wallet/transactions` (filters: type, category, date). UI panel with infinite scroll. |
| Real-Time Slab & PB Panel | P | Absent. | Compute current month spend + projected slab; display next threshold progress bar. AJAX refresh. |

## 4. KYC Module Completion
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Document Upload & Storage | P | Only table columns & submit helper. | Use WP media library or custom uploads dir `/uploads/svntex2/kyc/`; store JSON of file IDs; restrict mime; generate signed temporary URLs. |
| Encryption at Rest | P | None. | OpenSSL encrypt JSON blob before DB write using `SECURE_AUTH_KEY` derived key + IV per record. |
| Audit Trail | P | No logs. | `svntex_kyc_events` table capturing reviewer, action, timestamp, note. |
| KYC Gating in UI | P | Only payout gating in pb.php. | Dashboard banner with status + CTA to submit / re-submit. |

## 5. OTP & Auth Hardening
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Real SMS/Email OTP | P | Demo transient only (in auth class). | Integrate provider (e.g., Twilio / AWS SNS). Store hashed OTP + attempts, expire after 5m, lock after 5 failures. |
| Device / IP Rate Limits | P | Basic login attempt transient. | Central rate limiter utility using user meta + IP keyed transients. |
| Passwordless Option | P | Not implemented. | Magic link email (nonce + user ID, 15 min expiry) endpoint to auto-login. |

## 6. Security & Compliance
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Audit Log (financial + auth) | P | None. | `svntex_audit_log` table (id, actor_id, action, context JSON, created_at). Hook wallet, withdrawals, KYC, login. |
| Data Export/Erase Hooks | P | Not done. | Implement WordPress personal data exporter/eraser for wallet & KYC. |
| Capability Granularity | P | Single manage_options usage. | Define custom caps: `svntex_manage_withdrawals`, `svntex_manage_kyc`, etc. Map to roles on activation. |
| Rate Limit Across REST | P | None. | Shared limiter + HTTP 429 responses. |

## 7. Automated Testing
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| PHPUnit Config & Bootstrap | P | Absent. | Add `tests/bootstrap.php`, `phpunit.xml.dist`, install wp-env or use WP test suite. |
| Wallet Arithmetic Tests | P | None. | Test running balance correctness & category sums. |
| Referral Qualification Tests | P | None. | Threshold edges: 2399 vs 2400. |
| PB Distribution Normalization Tests | P | None. | Sum of payouts == profit, remainder distribution, slab resolution. |
| Withdrawal Fee Math Tests | P | None. | 1000 -> TDS 20, AMC 80, net 900. |

## 8. Performance & Scaling
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Index Tuning | P | Only basic keys. | Add composite indexes (user_id, created_at) on wallet; monthly partitioning option. |
| Ledger Archival | P | None. | Yearly archive routine moving old rows to `_archive` table. |
| Caching Layer | P | None. | Transient cache for referral counts & monthly spend; bust on new qualifying event. |

## 9. Dashboard UX Enhancements
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Dark Mode Persistence (All Pages) | S | Basic toggle only dashboard. | Unify toggle across brand-init, store preference, apply on auth & landing. |
| Modular Panel Loader | P | Static HTML. | Client-side router switching panels with REST data prefetch. |
| Accessibility Audit | P | Not performed. | Run axe/lighthouse, fix contrast, aria roles, focus states. |

## 10. Internationalization (i18n)
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Text Domain Coverage | P | Many strings not wrapped. | Wrap user-facing strings in `__()` or `_e()`, load text domain on init. |
| POT File Generation | P | None. | Use `wp i18n make-pot` in build script. |

## 11. CLI & Dev Tooling
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Extended WP-CLI Commands | P | Basic +wallet commands only. | Add `svntex2:referrals:list`, `svntex2:pb:simulate`, `svntex2:kyc:approve <user>`. |
| Linting / CI Checks | P | None. | Add GitHub Action with PHP_CodeSniffer (WordPress standard) & PHPUnit run. |

## 12. Error Handling & Observability
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Central Error Codes | P | WP_Error scattered. | Define `includes/errors.php` mapping codes -> messages. |
| Logging Strategy | P | None. | PSR-3 wrapper to error_log with context & filters; toggle via constant. |

## 13. Fraud & Abuse Prevention
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Multi-Account Referral Detection | P | None. | Heuristic: same IP/device referral multiple times -> flag user, store in audit. |
| Rapid Top-up/Reversal Loop Detection | P | None. | Monitor sequence patterns; suspend PB eligibility automatically. |

## 14. Deployment & Release Engineering
| Item | Status | Gap | Proposed Logic |
|------|--------|-----|----------------|
| Versioned DB Migrations | P | Single activation path only. | Migration runner with incremental versions array. |
| Changelog Automation | P | Manual. | GitHub Action to update CHANGELOG on release tag. |

---
## High-Level Architecture Diagram (Current + Planned)

```
+---------------- WordPress + WooCommerce Core ----------------+
|  Users / Orders / Cron / REST Router / Roles & Caps          |
+-------------------------+------------------------------------+
                          |
                    SVNTeX 2.0 Plugin
                          |
        +-----------------+-------------------+-----------------------+
        | Wallet Ledger   | Referrals & RB    | KYC Module            |
        | (transactions)  | (qualification)   | (submissions, status) |
        +--------+--------+---------+---------+-----------+----------+
                 |                  |                     |
                 |                  |                     |
        +--------v-----+    +-------v------+      +-------v---------+
        | Withdrawals  |    | PB Engine    |      | Admin Dashboard |
        | (fees calc)   |    | (slabs, dist)|      | (tabs, reports) |
        +--------+------+    +-------+------+      +--------+--------+
                 |                   |                    |
                 |                   |                    |
                 |             +-----v--------------------+----+
                 |             |   Pending Modules (Roadmap)   |
                 |             +-------------------------------+
                 |             | PB Lifecycle (maintenance)    |
                 |             | Profit Inputs UI              |
                 |             | Suspense Pool                 |
                 |             | Top-up / Withdrawal UI        |
                 |             | KYC Documents + Encryption    |
                 |             | OTP Provider Integration      |
                 |             | Audit Log & Security          |
                 |             | Automated Tests & CI          |
                 |             | Fraud Detection               |
                 |             | i18n & Accessibility          |
                 |             +-------------------------------+
                 |
         +-------v--------+
         | REST / CLI     |  (currently minimal; to expand for history,
         | (balance API)  |   admin automation, maintenance tasks)
         +----------------+
```

## Immediate Sprint Candidates
1. PB Lifecycle + Suspense Table
2. Admin Profit Inputs Table & UI
3. Withdrawal + Top-Up Front-End Panels
4. KYC Document Upload & Encryption
5. REST Endpoints for Transactions & Slab Preview

Keep this file updated each sprint.
