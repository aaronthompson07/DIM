# DoItMeow (Initial Build)

This repository now contains a first-pass PHP + MySQL foundation for DoItMeow with:

- Multi-tenant schema (single database with tenant scoping)
- Two-step login flow (company login then user PIN)
- Demo login entry point
- Today dashboard with open/completed stats
- Task completion workflow (completed tasks drop from open list)
- Infinity task behavior (overdue infinity tasks stay visible until completed)
- Completed-today popup endpoint + UI
- Polling-based live refresh every 5 seconds
- Automatic SQL migration runner on page load
- Limited mode with maintenance banner when a migration fails
- Audit log table and logging hooks for key actions
- Subscription feature-table foundations

## Quick Setup

1. Copy `.env.example` to `.env`.
2. Set your MySQL credentials in `.env`.
3. Point your domain/subdomain **document root** to the `public` directory.
4. Enable URL rewriting for clean URLs:
   - **Apache / cPanel:** ensure `mod_rewrite` is enabled (`public/.htaccess` is included).
   - **IIS / Plesk on Windows:** no action needed — `public/web.config` ships the equivalent
     rewrite rules. The IIS **URL Rewrite** module must be installed (standard on Plesk Windows),
     and PHP must be enabled for the site in Plesk.
5. Load the site once; migrations auto-run on first request.

### Plesk on Windows notes

- Set the subscription/site **Document Root to `public`** (not the repository root). A defensive
  `web.config` in the repo root blocks direct web access to secrets and source if that is
  misconfigured, but clean URLs only work when the document root is `public`.
- Give the site's IIS application pool identity **write access** to `storage/cache` and
  `storage/support_uploads` (used for weather caching and support ticket image uploads).
- HTTPS is auto-detected for secure session cookies; no code change needed.

Static assets are served from `public/resources`:

- `public/resources/css/app.css`
- `public/resources/js/app.js`

## Current Routes

- `/` index/features page
- `/login` login page
- `/dashboard` today dashboard

API:

- `POST /api/auth/company-login`
- `GET /api/auth/company-users`
- `POST /api/auth/pin-login`
- `POST /api/auth/demo-login`
- `POST /api/auth/logout-user`
- `POST /api/auth/logout-all`
- `GET /api/tasks/today-summary`
- `GET /api/tasks/today-open`
- `GET /api/tasks/today-completed`
- `POST /api/tasks/complete`

## Notes

- Demo login uses a direct demo-auth endpoint (`/api/auth/demo-login`).
- PIN lockout is enforced per selected user after 5 failed attempts.
- Tenant PIN uniqueness is enforced by `UNIQUE (tenant_id, pin_lookup_hash)`.
- Migration failure activates limited mode and keeps read-only flows available.
- Infinity tasks appear on later days when still incomplete; non-infinity tasks remain day-bound.

## Next Phase (Recommended)

- Task create/edit APIs + recurrence expansion job/generator
- Owner/super-admin portals and tenant lifecycle management
- Feature-gating enforcement middleware
- Tenant self-signup + trial expiry + PayPal + ads mode
- Reporting endpoints/charts
- ArrowChat tenancy-aware integration
- Full policy checks per role + impersonation mode + log viewer
