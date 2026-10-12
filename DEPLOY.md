# Deployment Guide — Society Management System

## Overview

This repo contains a multi-tenant Society Management System for housing societies in Pakistan: a **Laravel 11 (PHP) REST API** in `/backend`, and a **Next.js static-exported web panel** (Platform Admin + Society Management, built on the Materialize MUI template) in `/web`. A React Native mobile app in `/mobile` is planned but not yet started — it is **not** part of this deployment (mobile apps ship via app stores, not cPanel). The backend deploys to a PHP subdomain; the web panel deploys as plain static files to a second subdomain — no Node.js runtime is required anywhere on the server.

## Deployment Flow

```
Pull latest from main
      │
      ▼
Install dependencies locally
  composer install --no-dev --optimize-autoloader   (backend)
  npm install                                        (web)
      │
      ▼
Configure environment
  backend/.env   — DB credentials, APP_URL, CORS_ALLOWED_ORIGINS, mail
  web/.env.production — NEXT_PUBLIC_API_BASE_URL (must point at the LIVE API)
      │
      ▼
Run build command(s)
  backend: none required (plain PHP, no asset build needed to function)
  web:     npm run build
      │
      ▼
Locate build output folder(s)
  web/out/   (static export — the only build artifact)
      │
      ▼
Upload to GoDaddy — which folder goes where
  backend/  (whole project, minus .git)  → outside web root, docroot → backend/public
  web/out/* (contents, not the folder)    → panel subdomain's docroot
      │
      ▼
Apply server config (.htaccess / permissions)
  backend/public/.htaccess already present (Laravel default, no changes needed)
  web: add .htaccess for extensionless URL rewriting (see Step 6)
  chmod -R 755 backend/storage backend/bootstrap/cache
      │
      ▼
Verify live site
```

## Step-by-Step

### 1. Pull the project

```bash
git clone https://github.com/iamhadisyed/society-management-system.git
cd society-management-system
```

### 2. Local setup — Backend

```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan key:generate
```

Environment: copy `backend/.env.example` to `backend/.env`. These values **must** be set correctly for production:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://api.yoursociety.com` — the real API subdomain
- `APP_TIMEZONE=Asia/Karachi` — leave as-is regardless of server location
- `DB_CONNECTION=mysql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — from cPanel → MySQL® Databases
- `CORS_ALLOWED_ORIGINS=https://panel.yoursociety.com` — the real web panel domain (not `*`)
- `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database` — correct as shipped; no Redis/Memcached needed
- `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` — your cPanel mailbox's SMTP credentials
- `FIREBASE_CREDENTIALS`/`FIREBASE_PROJECT_ID` and `JAZZCASH_*`/`EASYPAISA_*` — leave blank for now; these back features (push notifications, payment gateways) that aren't implemented yet (see `PROGRESS.md`)

Then run migrations and seed the permission catalogue (not the demo sample data):

```bash
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\PermissionSeeder --force
php artisan storage:link
```

### 3. Local setup — Frontend

```bash
cd web
npm install
```

> `web/` has both a `package-lock.json` and a leftover `pnpm-lock.yaml` from the original template. `npm install` (used throughout this guide and verified working) is the one actually exercised — delete `pnpm-lock.yaml` to avoid confusion about which package manager owns this project.

Environment: create `web/.env.production` with:

```
NEXT_PUBLIC_API_BASE_URL=https://api.yoursociety.com/api/v1
```

This **must** point at the live backend URL *before* running the build below — static builds bake this value in at build time; changing it after the build has no effect and requires a full rebuild + re-upload.

### 4. Build

```bash
# Backend: no build step. It's plain PHP - the API runs directly from
# the uploaded source once `.env` is configured and `composer install`
# has populated vendor/. (backend/package.json + vite.config.js are
# unused leftovers from Laravel's default welcome page, not part of
# this app's real routes - see "Known issues" below.)

# Frontend:
cd web
npm run build
```

Confirm the output folder produced: **`web/out/`** (set by `output: 'export'` in `web/next.config.ts`; verified by actually running this build — 25 static HTML files, zero errors).

### 5. GoDaddy folder placement

This project uses the two-subdomain plan already documented in `docs/deployment.md`:

- **Backend** (`api.yoursociety.com`): upload the entire `backend/` project (including `vendor/` — built via `composer install --no-dev` as above) to a location **outside** any subdomain's public web root, e.g. `~/societyapp/backend/`. In cPanel → *Domains*, set the `api.yoursociety.com` subdomain's **Document Root** to `~/societyapp/backend/public`. Laravel's app code, `.env`, and `storage/` must never be directly web-accessible — only `public/` should be.
- **Frontend** (`panel.yoursociety.com`): upload the **contents** of `web/out/` (not the `out` folder itself) directly into the `panel.yoursociety.com` subdomain's document root.
- No other app exists in this repo yet to place (mobile is unbuilt and out of scope here).

### 6. .htaccess (frontend)

The web panel is a Next.js static export with real per-route HTML files (e.g. `out/en/login.html`), not a single-page app relying on client-side history routing with one `index.html` — so most navigation works without any rewrite rules. However, visiting a route **without** the `.html` extension (e.g. `https://panel.yoursociety.com/en/login`, which is what every internal link in the app actually requests) needs Apache told to serve the matching `.html` file. Add this `.htaccess` in the `panel.yoursociety.com` document root (alongside the uploaded `out/` contents):

```apache
RewriteEngine On

# Serve the matching .html file for extensionless paths
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME}.html -f
RewriteRule ^(.*)$ $1.html [L]

# Custom 404
ErrorDocument 404 /404.html
```

If a different routing mode is adopted later (e.g. hash-based `#/...` routing), this step would not be needed — that is not the case today.

### 7. File permissions (Laravel backend)

```bash
chmod -R 755 backend/storage backend/bootstrap/cache
```

### 8. Verification checklist

- [ ] Homepage loads: `https://panel.yoursociety.com` redirects to `/en/pages/account-settings` (or to login if logged out)
- [ ] Refreshing a deep/nested route (e.g. `https://panel.yoursociety.com/en/login`) does not 404
- [ ] `https://api.yoursociety.com/up` returns 200 (Laravel's built-in health check)
- [ ] A real API call from the panel succeeds — log in and confirm the request reaches the API (not a CORS error, not a blank page)
- [ ] Login flow works end to end: staff login → token stored → `/me` returns the user and permissions
- [ ] The cron job is firing: add it in cPanel (`* * * * * cd ~/societyapp/backend && php artisan schedule:run >> /dev/null 2>&1`) and confirm a queued job (e.g. a bill-run generation) drains within a minute
- [ ] SSL is active and HTTP redirects to HTTPS on both subdomains

## Known issues / things to double check

- **`backend/package.json`, `vite.config.js`, `tailwind.config.js`, `postcss.config.js` are unused.** They're Laravel's default scaffolding for compiling `resources/views/welcome.blade.php`, which isn't part of this application (an API-only backend with no Blade frontend). No `npm install`/`npm run build` is needed in `backend/` for anything to work; these files can be deleted to avoid confusion, or left as harmless dead weight.
- **Two lockfiles in `web/`** (`package-lock.json` and `pnpm-lock.yaml`) — pick one (this guide uses `npm`) and delete the other.
- **`FIREBASE_CREDENTIALS`/`FIREBASE_PROJECT_ID` and `JAZZCASH_*`/`EASYPAISA_*`** in `backend/.env.example` are placeholders for the Notifications and Payments modules, which aren't built yet (see `PROGRESS.md`) — leave them blank; nothing currently live depends on them.
- **No real post-login dashboard exists yet.** `themeConfig.homePageUrl` in the web panel currently points at `/pages/account-settings` as a placeholder (the only real authenticated page built so far) — update it once a real dashboard page ships.
- **Several auth pages are UI-only.** `register`, `forgot-password`, `reset-password`, and `verify-email` render but aren't wired to real backend endpoints yet (only staff login is live) — see `docs/decisions.md`.
- **The sidebar/search navigation still references deleted demo pages** (dead links, not a build error) — cosmetic only, needs a real rewrite once actual panel screens are built; does not block this deployment.
- **Mobile app (`/mobile`) is empty** — not part of this or any GoDaddy deployment; it will ship separately via app stores once built.
