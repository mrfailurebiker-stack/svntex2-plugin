# System Audit & Clone Discovery Checklist

Use this to capture the current system precisely, so we can reproduce it 1:1 (or better) in this codebase.

## Access & Environment
- Admin URL:
- Temporary admin/demo credentials (role, username):
- 2FA / OTP details (if any):
- API base (if separate):
- Hosting/platform details:
- Data export options available (DB dump, CSV, API):
- Legal/IP ownership confirmed (yes/no):

## Authentication & Roles
- Auth method (session/JWT/OAuth/OTP/SSO):
- Roles and capabilities:
- Password policy & OTP flows:
- Session timeout/remember-me:
- Canonical domain & cookie settings (www vs non-www, HTTPS-only):

## Core Modules (tick all that exist)
- [ ] Dashboard KPIs
- [ ] Users & Profiles (fields, search, edit)
- [ ] Products (CPT fields, images, video, categories/brands/tags, shipping class)
- [ ] Vendors
- [ ] Wallet (balance, transactions, top-up)
- [ ] Referrals (linking, qualifying)
- [ ] KYC (statuses, evidence)
- [ ] Reviews moderation
- [ ] Orders / Commerce (if any):
- [ ] Bulk tools (import/export)
- [ ] Analytics (what charts/metrics?):
- [ ] Settings (site, SEO, email/SMS, GST/tax):

### For each module capture
- Screens/routes (URLs):
- Fields and validations:
- Actions (CRUD/business rules):
- Filters/sorting/pagination:
- Edge cases and error messages:

## Data Model
- Entities + fields (name, type, required, defaults):
- Relationships (1-1, 1-many, many-many):
- Derived/computed fields (e.g., company_profit):
- Taxonomies (categories, brand, tag, shipping class):

## API Inventory
- Base URL:
- Auth header/cookie:
- Endpoints (method, path, request/response shape, status codes):
- Rate limits & pagination:

## Integrations
- Payment gateways:
- Email/SMS providers:
- Storage/CDN:
- Analytics/BI:

## Non-Functional
- Performance SLAs (TTFB, load):
- Cache/CDN behavior:
- Security (CORS, CSP, CSRF, XSS mitigations):
- Logging & Monitoring:
- Backups & DR:

## Gaps / Bugs to fix in the clone
- 

## Acceptance Criteria
- Pixel/function parity list:
- Admin UX behaviors (mobile menu, redirects, etc.):
- Health and debug endpoints present:
- CI/CD deploy to Hostinger via FTPS:
