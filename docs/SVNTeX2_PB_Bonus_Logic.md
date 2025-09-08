# SVNTeX 2.0 – Partnership Bonus (PB) & Referral Bonus (RB) Logic Specification

Document Version: 1.0  
Status: Authoritative functional specification for implementation & stakeholder review  
Scope: Defines all rules, statuses, timelines, calculations, examples, and data artifacts for PB lifecycle, RB trigger, eligibility gating, suspense handling, and dashboard UX messaging.

---
## 1. Definitions
| Term | Definition |
|------|------------|
| Customer ID | System-generated identifier: `SVN-` + 6 digits (e.g., `SVN-023451`). |
| Qualifying Purchase / Top-Up | A single order subtotal (excluding shipping) or wallet top-up ≥ ₹2,499. |
| Qualifying Referral | A referred customer who completes at least one qualifying purchase/top-up. |
| RB (Referral Bonus) | One-time 10% of the referee’s first qualifying base amount credited to referrer. |
| PB (Partnership Bonus) | Monthly profit share allocated to eligible customers based on slab and lifecycle status. |
| Slab | Percentage band derived from a customer’s monthly qualifying spend. |
| Activation Month | Month in which the 2nd qualifying referral is achieved. |
| Inclusion Start Month | The *following* month after Activation Month; first month counted toward PB pool. |
| Distribution Month | The month PB payouts (for prior month) are credited (early next month). |
| Cycle | A 12‑month period beginning from the customer’s join month (or a reset baseline after suspension). |
| Maintenance Requirement | Additional referrals required in same cycle to keep PB active into next cycle. |
| Suspension | Status blocking PB payouts (new payouts held in suspense). |
| Suspense | Holding area for provisional/suspended users’ computed PB shares (not yet credited). |

---
## 2. Data Entities & Storage
| Data | Storage Mechanism | Notes |
|------|------------------|-------|
| Wallet Transactions | Table: `wp_svntex_wallet_transactions` | Types: `wallet_topup`, `purchase`, `referral_bonus`, `referral_commission`, `profit_bonus`, `withdraw_hold`, etc. |
| PB Payouts | `wp_svntex_pb_payouts` | Credited users only. |
| PB Suspense | `wp_svntex_pb_suspense` | Held rows: provisional/suspended at distribution time. |
| Profit Distribution Header | `wp_svntex_profit_distributions` | One row per processed month. |
| Referrals | `wp_svntex_referrals` | Links + qualification state. |
| KYC Submissions | `wp_svntex_kyc_submissions` | Gating withdrawals & PB inclusion. |
| User Meta – Status | `_svntex2_pb_status` | `inactive|provisional|active|suspended|lifetime` (lifetime future). |
| User Meta – Referral Total | `_svntex2_pb_referral_count_total` | Monotonic counter of qualifying referrals. |
| User Meta – Activation Time | `_svntex2_pb_activated_on` | Timestamp when became provisional. |
| User Meta – Active Since | `_svntex2_pb_active_since` | Timestamp when first reached `active`. |
| User Meta – Monthly Snapshot | `_svntex2_pb_ref_m_YYYY-MM` | Integer referrals qualified that month. |
| User Meta – RB Awarded Flag | `_svntex2_rb_awarded` | Boolean 1/0. |

---
## 3. Lifecycle Status Flow
```
 inactive  --(2 qualifying referrals)--> provisional --(total 6 in cycle)--> active
    ^                                                |                         \
    |                                                | (fail maintenance)       \ (24 consecutive active months*)
 (reset / new cycle)                                  v                           --> lifetime (future)
                                                 suspended <--(regain 2+4)---
```
*Lifetime currently deferred; placeholder for extended retention incentive logic.

### 3.1 Status Triggers
| Transition | Condition |
|------------|-----------|
| inactive → provisional | On achieving 2nd qualifying referral in any month. Activation Month recorded. |
| provisional → active | When cumulative qualifying referrals in same cycle reach 6 (2 + 4 additional). |
| active → suspended | At cycle boundary if maintenance (+4 after initial 2) not completed OR renewal rule not met (see Section 5). |
| suspended → provisional | (Reset pattern) After new cycle start, once 2 new qualifying referrals appear (treated as new activation). |
| provisional → active (after suspension) | Once total new referrals in the *current* reset cycle reach 6 again. |

### 3.2 Inclusion & Timing
| Milestone | Timing Rule |
|-----------|-------------|
| Activation Month | Month containing 2nd qualifying referral. |
| Inclusion Start Month | First PB-eligible operational month = Activation Month + 1. |
| First Payout Display | Distribution run for Inclusion Start Month occurs early next month (credited then). |

Example A (Fast Activation): 2 referrals in January → Inclusion February → First payout credited early March (for February).

Example B (Staggered): 1 referral January + 1 referral February → Activation February → Inclusion March → First payout credited early April.

---
## 4. Referral Bonus (RB) Logic
1. Trigger: First qualifying purchase/top-up (≥ ₹2,499) by a referred user.
2. Amount: 10% of that qualifying base (net of shipping if order-based).
3. One-time per referee, whichever event qualifies first (purchase vs top-up).
4. Credited as `referral_bonus` (category `income`).
5. Sets `_svntex2_rb_awarded` on referee (used as PB gating condition for referrer’s inclusion? *Current gating is on the PB recipient’s own RB award.*)

---
## 5. Annual Cycle, Maintenance & Renewal (Clarified Business Rule)
Each cycle = 12 calendar months starting from join month or from *new activation baseline* after suspension.

### 5.1 Initial Activation Requirements (Cycle Year 1)
- 2 qualifying referrals → Activation.
- 4 additional referrals (for a total of 6) before end of Month 12 → Maintains active status.

### 5.2 Renewal Requirement (End of Cycle)
- In Month 12 (final month of the cycle), user must add **2 new qualifying referrals** to seed next cycle’s activation.
- If not achieved by cycle end → status moves to `suspended` on Month 13 (new cycle start), regardless of earlier maintenance fulfillment.

### 5.3 Suspension & Recovery
- Suspended users earn no PB; computed shares (if any provisional assumption) go to suspense (currently only provisional/suspended withholding).
- Recovery requires full pattern again in new cycle: 2 (activation) + 4 (maintenance) = 6 total.

### 5.4 Edge Case Handling
| Scenario | Outcome |
|----------|---------|
| Only 5 total referrals in first 12 months | Suspend Month 13. |
| Achieved 6 by Month 10 but no 2 new in Month 12 | Suspend Month 13 (renewal failed). |
| Achieved 2 in Month 12 but no +4 previously | Still suspended: missing maintenance. |
| Suspended then refers 4 in Month 13 | Still not active (needs first 2 recognized as activation, then reach 6). |

---
## 6. Monthly Spend & Slab Resolution
### 6.1 Qualifying Spend Components
- Positive types: `wallet_topup`, `purchase`.
- Negative adjustments: `refund` (reduces net spend).

### 6.2 Slab Table
| Threshold (₹ ≥) | Slab % |
|-----------------|--------|
| 2,499 | 10% |
| 3,499 | 15% |
| 4,499 | 20% |
| 5,499 | 25% |
| 6,499 | 30% |
| 7,499 | 35% |
| 8,499 | 40% |
| 9,499 | 45% |
| 10,499 | 50% |
| 11,499 | 55% |
| 12,499 | 60% |
| 13,499 | 62% |
| 14,499 | 65% |
| 15,499 | 67% |
| 16,499 | 68% |
| 17,499 | 69% |
| 19,999 | 70% (cap) |

### 6.3 Resolution Algorithm
```
slab = 0
for each threshold ascending:
  if spend >= threshold: slab = percent
return slab
```

### 6.4 Dashboard Display Elements
- Current Month Spend
- Current Slab %
- Next Slab Threshold (delta)
- Historical last 3 month slabs

---
## 7. PB Distribution Calculation
### 7.1 Inputs
| Symbol | Description |
|--------|-------------|
| P_m | Company profit for month m after adjustments. |
| U_m | Set of eligible users for month m. |
| s_i | Slab percent for user i (decimal). |
| Σs | Sum of all slab percents for eligible users. |

### 7.2 Eligibility Conditions (month m)
1. Status = `active` (future: include `lifetime`).
2. KYC approved.
3. RB awarded (if gating rule retained).
4. Monthly spend ≥ 2,499 (lowest slab threshold).
5. Inclusion Start Month ≤ m (activated previously). 

### 7.3 Current Implemented Formula (Normalized Share Mode)
```
User Share Ratio r_i = s_i / Σs
Payout_i = P_m * r_i
```
Rounded to 2 decimals; sum may differ by minor rounding remainder.

### 7.4 Alternate Business-Spec Formula (Profit Value × Slab %) – Planned Mode
```
ProfitValue = P_m / |U_m|
Payout_i = ProfitValue * s_i
```
Requires configuration flag: `normalized` vs `direct_formula`.

### 7.5 Suspense Handling
If status provisional or suspended at run time:
- Row inserted into `svntex_pb_suspense` with reason = status, amount = computed payout.
- Not credited to wallet until manual release (automated release cron future enhancement).

### 7.6 Rounding
- Individual payouts: round(amount, 2)
- Optional remainder allocation: (Not yet implemented in new normalized slab model; legacy distribution may add remainder to top earner.)

---
## 8. Referral Bonus Calculation
```
If referee makes first qualifying event (amount >= 2499) and RB not yet awarded:
  RB = round(base_amount * 0.10, 2)
  Credit referrer (income)
  Mark referee meta _svntex2_rb_awarded = 1
```
Base amount excludes shipping for orders; raw amount for top-ups.

---
## 9. Withdrawal Logic
1. Only `income` category balance is withdrawable.
2. Fees:
   - TDS = 2% of gross
   - AMC = 8% of gross
   - Net = Gross − (TDS + AMC)
3. Flow:
   - Request: `withdraw_hold` (negative ledger entry)
   - Approve: add fee metadata via status update (final 0-amount confirmation transaction optional) – net disbursed off-ledger operationally.
   - Reject: `withdraw_refund` (refunds held amount)
4. Validation gates:
   - KYC approved
   - PB status active

---
## 10. Notifications & UX Messaging (Customer-Facing)
| Event | Message Template |
|-------|------------------|
| Registration | “Welcome! Refer 2 qualifying friends (≥ ₹2,499) to activate PB earnings.” |
| 1st Referral | “Great! 1 of 2 referrals complete—1 more unlocks PB activation.” |
| Activation (Same Month) | “Activated! PB earnings start next month; first payout visible the following month.” |
| Activation (Next Month) | “Activation in {ACTIVATION_MONTH}. Earnings begin {INCLUSION_MONTH}; first payout credited in {DISPLAY_MONTH}.” |
| Maintenance Progress | “Maintenance: {X}/4 additional referrals. Complete before {CYCLE_END} to stay active.” |
| Renewal Needed | “Renewal: Add 2 new referrals this month to keep PB active next cycle.” |
| Suspension | “PB Suspended. Start new cycle: Refer 2 to activate, then 4 more to maintain.” |
| Suspense Hold | “₹{AMOUNT} PB payout held (Status: {STATUS}). Become Active to release future payouts.” |

---
## 11. Example Timelines
### Example 1 – Fast Activation
- Join: Jan
- 2 Referrals: Jan
- Activation Month: Jan
- Inclusion Start: Feb
- First Payout: Early Mar (for Feb)

### Example 2 – Staggered Activation
- Join: Jan
- Referral #1: Jan
- Referral #2: Feb
- Activation Month: Feb
- Inclusion Start: Mar
- First Payout: Early Apr (for Mar)

### Example 3 – Missed Maintenance
- Join: Jan
- Only 5 total referrals by Dec (needs 6)
- Status: Suspended from Jan (Month 13)
- Recovery: Must do 2 (activation) + 4 (maintenance) anew within new cycle.

### Example 4 – Maintenance Success but No Renewal
- Achieves 6 by Aug
- Fails to add required 2 renewal referrals in Month 12 (Dec)
- Suspended Month 13 despite prior maintenance.

---
## 12. Edge Cases & Clarifications
| Scenario | Rule Application |
|----------|------------------|
| Referee qualifies after referrer suspended | Does **not** directly unsuspend; counts toward new cycle if still within logic window. |
| Multiple qualifying events same day | Each referral counts once when first qualifying threshold crossed. |
| Refund reduces net spend below slab threshold | Slab re-evaluated at distribution; may lower payout share. |
| Suspended user later reaches 6 without distinct 2+4 pattern | Must satisfy order: first 2 (activation), then reach 6 (maintenance). |
| Provisional month with no spend ≥ 2499 | No slab → zero PB share (no suspense row created). |

---
## 13. Open / Future Enhancements
| Area | Planned Improvement |
|------|---------------------|
| Lifetime Status | Formal rule (e.g., active 24 consecutive calendar months). |
| Automatic Renewal Alerts | Pre-month-12 email + in-app banner. |
| Automated Suspense Release | Cron to scan active statuses and release eligible held payouts. |
| Profit Input Admin UI | Structured monthly profit entry & override vs filters. |
| Distribution Mode Toggle | Switch between normalized share and direct formula. |
| API Endpoints | `/pb/status`, `/pb/projection`, `/wallet/transactions` pagination. |
| Encryption | KYC document + sensitive meta AES-256 at rest. |
| Fraud Detection | Heuristics: same IP referrals cluster, rapid top-up/refund loops. |
| Audit Trail | Append-only ledger of sensitive actions. |

---
## 14. Pseudocode Summary (Key Routines)
### 14.1 Activation Handling
```
function onReferralQualified(referrer):
  increment total_referrals
  increment month_referral_snapshot(current_month)
  if status == inactive and total_referrals >= 2:
     status = provisional
     activation_month = current_month
  if total_referrals >= 6 and status in {provisional, active?}:
     status = active
```
### 14.2 Lifecycle Maintenance (Monthly Cron Before Distribution)
```
for user with pb_status:
  total12 = sum(referrals last 12 months)
  if status == active and cycle ended and (maintenance not met OR renewal not met):
      status = suspended
  if status == suspended and pattern (2 then 6) satisfied in new cycle:
      status = active
```
### 14.3 Distribution (Normalized Mode)
```
collect eligible active users (status active, KYC ok, RB ok, spend>=2499)
for each -> determine slab percent s_i
Σs = sum(s_i)
for each user:
   payout = company_profit * (s_i / Σs)
   credit profit_bonus (if active) else insert suspense
```

---
## 15. Implementation Checklist Mapping
| Feature | Implemented | Notes |
|---------|-------------|-------|
| Slab Resolution | Yes | `pb.php` function `svntex2_pb_resolve_slab_percent`. |
| Monthly Spend Aggregation | Yes | Credits minus refunds. |
| RB Award | Yes | First qualifying top-up/purchase. |
| Activation / Maintenance Counters | Partial | Cycle renewal & strict 2 + 4 annual reset logic still to refine. |
| Suspense Table | Yes | `svntex_pb_suspense`. |
| Admin Suspense Release | Yes | Distributions tab. |
| Automatic Release | No | Future cron. |
| Renewal Enforcement (Month 12) | Not fully enforced | Needs explicit month boundary detection logic. |
| Lifetime Elevation | Placeholder | Time-based meta checks only. |
| Direct Formula Mode | Not yet | Config toggle pending. |

---
## 16. Risks & Mitigations
| Risk | Impact | Mitigation |
|------|--------|-----------|
| Ambiguous cycle boundaries | Incorrect suspensions | Implement calendar-based cycle index calculation. |
| Large suspense backlog | Admin workload / liability | Add auto-release + aging report. |
| Rounding drift | Minor payout mismatch | Track remainder and allocate to highest slab or create adjustment row. |
| Retroactive refunds | Overpayment risk | Add negative adjustment capture & optional clawback logic. |
| Referral fraud clusters | Financial leakage | Pattern detection + soft lock + KYC escalation. |

---
## 17. Developer Integration Notes
- Always call lifecycle evaluation before distribution.
- Keep meta access batched (consider caching monthly snapshots in a derived table if volume grows).
- Expose projection endpoint so dashboard can show: “Projected PB next month (range).”
- Add feature flags: `SVNTEX2_PB_DISTRIBUTION_MODE`, `SVNTEX2_PB_AUTORELEASE_SUSPENSE`.

---
## 18. Example Customer Dashboard Components
| Component | Purpose |
|-----------|---------|
| Activation Progress Bar | Visual (2 of 2, then 6 of 6). |
| Renewal Countdown | Shows Month 10–12 progress & required referrals. |
| Slab Card | Current spend, slab %, next threshold delta. |
| Suspense Alert | If held payouts exist. |
| Earnings Timeline | Join → Activation → Inclusion → First Payout. |

---
## 19. Change Log (Doc)
| Version | Date | Summary |
|---------|------|---------|
| 1.0 | 2025-09-08 | Initial comprehensive PB/RB specification extracted & structured. |

---
## 20. Approval & Ownership
| Role | Name / Placeholder | Responsibility |
|------|--------------------|----------------|
| Product Owner | (Assign) | Business rule authority |
| Tech Lead | (Assign) | Ensures logic alignment in code |
| Backend Engineer | (Assign) | Implements cron & distribution refinements |
| QA Lead | (Assign) | Derives test cases from Sections 3–11 |

---
*End of Document*
