SVNTeX Custom Admin (outside WP)

This folder will contain a minimal standalone admin portal served as static HTML + JS, backed by WordPress REST API endpoints (hidden). To access, deploy to /domains/svntex.com/public_html/admin/ via CI.

- index.html: login screen (JWT or nonce cookie from WP REST)
- app.js: calls WP REST routes under /wp-json/svntex2/v1/ with a token stored in localStorage.
- Protect by obscurity + capability checks server-side.
