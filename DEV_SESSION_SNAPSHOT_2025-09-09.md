SVNTeX2 - Dev Session Snapshot
Date: 2025-09-09  (local session snapshot)

Purpose
- Capture chat history summary, edits made in this session, important commit hashes, deployment details, and next steps so you can restart your Mac without losing context.

Summary of actions taken (high level)
- Implemented and iterated a GitHub Actions deploy workflow that rsyncs plugin files to Hostinger.
- Added a temporary `deploy-debug.php` endpoint to verify live deploys.
- Fixed logout issues by adding a server-side admin-post logout endpoint (`svntex2_handle_logout`).
- Redesigned the SVNTeX dashboard UI in `views/dashboard.php` (responsive layout, mobile drawer/hamburger, debug badge/panel).
- Bumped plugin version to 0.2.6 in `svntex2-plugin.php`.
- Added server-side filters and template overrides to hide WooCommerce/theme chrome and inject the SVNTeX dashboard into My Account.

Important files changed / created
- `svntex2-plugin.php` (root)
  - Purpose: plugin bootstrap and core logic. Version bumped to 0.2.6; added server-side logout endpoint; enqueue logic and My Account override.
  - Location: `/Users/mac/Desktop/svntex2.0/svntex2-plugin/svntex2-plugin.php`

- `views/dashboard.php`
  - Purpose: dashboard UI. Added responsive CSS, mobile drawer menu, debug badge and collapsible debug panel, logout links.
  - Location: `/Users/mac/Desktop/svntex2.0/svntex2-plugin/views/dashboard.php`

- `deploy-debug.php` (new)
  - Purpose: temporary debug endpoint to confirm that GitHub Actions/rsync deployed files to Hostinger. Prints commit/time and runtime values.
  - Location: `/Users/mac/Desktop/svntex2.0/svntex2-plugin/deploy-debug.php`

- `.github/workflows/deploy.yml`
  - Purpose: GitHub Actions workflow to deploy plugin to Hostinger via SSH+rsync. Include list modified to transfer `deploy-debug.php` and other plugin files.
  - Location: `.github/workflows/deploy.yml`

Other files touched
- `assets/css/style.css`, `assets/css/landing.css`, `assets/js/*` possibly enqueued/registered; `includes/functions/*` modules remained in place.

Recent relevant commit hashes (local/session reported)
- 84ae1c5 — bump plugin version to 0.2.6
- 8d233ec — add `deploy-debug.php` (debug file)
- f77a173 — CI include list update (deploy.yml) to add debug file
- 07cdf47 — UI changes (mobile drawer menu)
- d23567c / a19d719 — build badges referenced in dashboard (timestamps/short hashes used in debug)

Hostinger deploy target (used by CI)
- Host: 145.79.211.169 (user: u271371274), SSH port: 65002
- Target path: `/home/u271371274/domains/svntex.com/public_html/wp-content/plugins/svntex2-plugin/`
- Deployment mechanism: GitHub Actions -> ssh-agent using secret key -> rsync --include-from list -> remote pre-clean

Runtime / live verification
- The `deploy-debug.php` endpoint was verified reachable on the live site and returned commit/time and runtime values (user screenshot confirmed).
- Debug panel inserted into `views/dashboard.php` shows current user id, KYC, wallet balance, and WooCommerce function availability.

Short-term recommendations before restart
1) Commit and push local working tree (if not already). This snapshot file is created locally but not committed.
   - To commit and push from your machine (zsh):

```bash
cd /Users/mac/Desktop/svntex2.0/svntex2-plugin
git add DEV_SESSION_SNAPSHOT_2025-09-09.md
git commit -m "chore: add dev session snapshot 2025-09-09"
git push origin main
```

2) If you want a full backup zip of the plugin directory before reboot, create one now:

```bash
tar -czf svntex2-plugin-backup-2025-09-09.tgz -C /Users/mac/Desktop/svntex2.0 svntex2-plugin
```

3) If you want me to also remove temporary debug artefacts (`deploy-debug.php` and the debug panel in `views/dashboard.php`) I can produce a patch and commit those changes before you restart. Say "remove debug" and I'll prepare that.

Next steps I can take (choose one)
- Commit and push this snapshot file to the repo for you.
- Create a zip backup of the plugin directory and place it in the project root.
- Remove temporary debug artifacts and push changes.
- Nothing else — you can restart, file is saved locally.

Notes and caveats
- This snapshot file captures the session summary and key file locations and commit hashes as reported during the session, not the full raw chat transcript.
- If you power off before committing/pushing, this file remains in the project folder but will not be on origin until you push.

End of snapshot.
