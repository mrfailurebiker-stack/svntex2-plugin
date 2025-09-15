# Clone Build Plan (SVNTeX 2.x)

This plan assumes we replicate the existing software’s admin experience and APIs on top of WordPress + custom plugin + static admin SPA (already in this repo), extending where needed.

## Phases
1) Discovery & Access
- Collect admin URL and temporary credentials (or staging clone)
- Walk through each module; fill the discovery checklist
- Export data samples (CSV/API) to model fields precisely

2) Data Model & APIs
- Extend CPT/meta/taxonomies to match current software
- Add/adjust REST endpoints for parity (users, products, vendors, wallet, referrals, KYC, reviews, analytics, orders if applicable)
- Write minimal unit tests for critical routes

3) Admin UI Parity
- Mirror screens/flows in `custom-admin/panel.html` with components for each module
- Add missing modules (reviews moderation UI, bulk tools, analytics charts, attributes/variations if needed)

4) Migration
- Import historical data (CSV/API/DB dump) into WP tables/CPT + custom tables
- Map IDs and preserve relationships

5) Validation
- Acceptance checklist with the owner against their live system
- Fix gaps; add polish (mobile UX, toasts, accessibility)

6) Cutover
- Final deploy to Hostinger via existing FTPS CI
- Enable server-level canonical redirect and cache purge

## Deliverables
- Extended plugin code (CPT/meta/taxonomies, REST, helpers)
- Admin SPA screens and scripts for all modules
- Import/export tools for migration
- Minimal docs for ops (health checks, debug, deploy)

## Timeline (indicative)
- Discovery: 1–2 days (depends on access)
- Build: 3–7 days (module complexity)
- Migration: 1–2 days
- Validation/Cutover: 1–2 days

## Assumptions
- We have legal right to clone the app
- You provide a temporary admin user and, if possible, API tokens or a staging dump
- We’ll maintain WordPress as platform for speed and extensibility
