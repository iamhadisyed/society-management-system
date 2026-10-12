# Deployment Guide — GoDaddy cPanel Shared Hosting

This guide deploys the two pieces of this system to a single GoDaddy cPanel shared-hosting account:

- **`api.yoursociety.com`** → Laravel backend (`/backend`)
- **`panel.yoursociety.com`** → Next.js web panel, static-exported (`/web`)

Mobile app builds (React Native, `/mobile`) point at `api.yoursociety.com` and are distributed via the Play Store / App Store / internal APK — not part of this hosting setup.

No AWS, no long-running Node/websocket servers, no Supervisor — everything here works within standard cPanel shared-hosting limits. See `docs/decisions.md` for why each of these choices was made.

---

## 0. Prerequisites

- A GoDaddy (or any cPanel) hosting plan with **PHP 8.2+**, **MySQL**, **SSH or File Manager access**, and **cron job** access.
- Two subdomains created in cPanel → *Domains*: `api.yoursociety.com` and `panel.yoursociety.com`.
- Composer available (via SSH) or the ability to upload a pre-built `vendor/` folder — see step 2.
- Node.js 18+ available *locally* (to build the Next.js static export before upload — cPanel shared hosting does not need Node installed on the server itself, since we upload pre-built static files).

---

## 1. Create the MySQL database

cPanel → **MySQL® Databases**:

1. Create a database, e.g. `cpaneluser_societydb`.
2. Create a database user with a strong password.
3. Add the user to the database with **ALL PRIVILEGES**.
4. Note the host (usually `127.0.0.1` or `localhost` on shared hosting), database name, username, password — you'll need these for `.env`.

---

## 2. Deploy the Laravel backend (`api.yoursociety.com`)

### 2.1 Where files go

cPanel subdomains get their own document root, e.g. `~/api.yoursociety.com`. Laravel must **not** be publicly served from its project root (it would expose `.env`, `app/`, etc.) — only `backend/public` may be web-accessible. Two supported layouts:

**Option A (recommended, needs SSH):** upload the whole `backend/` project *outside* the web root, then point the subdomain's document root at `backend/public`.

```
~/societyapp/backend/            <- full Laravel project (composer.json, app/, etc.)
~/api.yoursociety.com -> symlink or docroot pointed at ~/societyapp/backend/public
```

In cPanel → **Domains** → edit the `api.yoursociety.com` subdomain → set **Document Root** to `/home/cpaneluser/societyapp/backend/public`.

**Option B (File Manager only, no SSH):** upload the whole `backend/` folder directly into `~/api.yoursociety.com/`, then in `~/api.yoursociety.com/public/index.php` adjust the two `require` paths to point one level up correctly (they already do by default — `dirname(__DIR__)` — as long as `public/` stays inside the uploaded `backend/` folder), and add an `.htaccess` in `~/api.yoursociety.com/` (the subdomain root) that rewrites everything into `public/`:

```apache
# ~/api.yoursociety.com/.htaccess
RewriteEngine On
RewriteRule ^$ public/ [L]
RewriteRule (.*) public/$1 [L]
```

Option A is cleaner and is assumed for the rest of this guide.

### 2.2 Upload the code

Via SSH (recommended):

```bash
cd ~
git clone <your-repo-url> societyapp
cd societyapp/backend
composer install --no-dev --optimize-autoloader
```

Without SSH: build `vendor/` locally with `composer install --no-dev --optimize-autoloader`, zip the whole `backend/` folder (including `vendor/`), upload via File Manager, and extract in place.

### 2.3 Configure `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` (see `backend/.env.example` for the full annotated list) — at minimum:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yoursociety.com
APP_TIMEZONE=Asia/Karachi

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cpaneluser_societydb
DB_USERNAME=cpaneluser_dbuser
DB_PASSWORD=<your-db-password>

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

CORS_ALLOWED_ORIGINS=https://panel.yoursociety.com

MAIL_MAILER=smtp
MAIL_HOST=mail.yoursociety.com          # your cPanel mailbox's SMTP host
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=noreply@yoursociety.com
MAIL_PASSWORD=<mailbox-password>
```

### 2.4 Run migrations and seed the permission catalogue

```bash
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\PermissionSeeder --force
```

(Skip `SocietySampleDataSeeder` in production — that's demo data for local testing only. Real societies are onboarded via the Platform Admin panel, which provisions their default roles automatically.)

### 2.5 Storage, permissions, and the public disk symlink

```bash
php artisan storage:link
chmod -R 755 storage bootstrap/cache
```

`storage/app/public` (public disk: ad creatives, society logos) becomes reachable at `https://api.yoursociety.com/storage/...`. Everything else — payment proofs, CNIC images, ownership-transfer documents, bulk bill PDFs — lives on the **private** `local` disk (`storage/app/private`) and is only ever served through authenticated download routes, never directly by the webserver. Do not symlink or expose that directory.

### 2.6 Optimize for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Re-run these three after every future deploy (or clear with `php artisan optimize:clear` while debugging).

### 2.7 The one cron job that runs everything

cPanel → **Cron Jobs** → add a job running **every minute**:

```
* * * * * cd /home/cpaneluser/societyapp/backend && php artisan schedule:run >> /dev/null 2>&1
```

This single line drives all of the following (already wired in `backend/routes/console.php`):

- `queue:work --stop-when-empty --max-time=50` every minute — processes bill generation, bulk PDF, and (once built) notification jobs from the `database` queue, without needing a long-running worker process.
- `bills:mark-overdue` daily — flips unpaid/partially-paid bills with a past due date to `overdue`.
- Any further scheduled commands added as later modules land (SOS escalation checks, ad campaign expiry, DB backups — see § 4 below and `PROGRESS.md`).

Use the exact PHP binary path if `php` isn't on cPanel's cron `PATH` — check via SSH with `which php`, and if needed use something like `/opt/cpanel/ea-php82/root/usr/bin/php` instead of `php` in the cron line.

### 2.8 SSL

cPanel → **SSL/TLS Status** → run **AutoSSL** for `api.yoursociety.com` (and `panel.yoursociety.com` in the next section). GoDaddy issues a free Let's Encrypt-based certificate automatically; just make sure both subdomains are listed and force HTTPS redirects are enabled (**Domains** → *Force HTTPS Redirect*).

---

## 3. Deploy the Next.js web panel (`panel.yoursociety.com`)

The panel is built with `output: 'export'` — a static site with no Node server needed on the host (see `docs/decisions.md` for the adaptation work this required on the Materialize template).

### 3.1 Build locally (or in CI)

```bash
cd web
npm install
echo "NEXT_PUBLIC_API_BASE_URL=https://api.yoursociety.com/api/v1" > .env.production
npm run build
```

With `output: 'export'` set in `next.config.ts`, `npm run build` produces a static `out/` directory containing plain HTML/CSS/JS.

### 3.2 Upload

Upload the **contents** of `web/out/` (not the folder itself) into the `panel.yoursociety.com` subdomain's document root via File Manager or:

```bash
rsync -avz --delete web/out/ user@yourhost:~/panel.yoursociety.com/
```

### 3.3 SPA routing on static hosting

Static export produces one HTML file per route, so deep links work without extra config in most cases. If a hard refresh on a nested route 404s, add an `.htaccess` in `panel.yoursociety.com/`:

```apache
ErrorDocument 404 /404.html
```

### 3.4 Re-deploying after changes

Every panel update is: rebuild locally (`npm run build`) → re-upload `out/`. There is no server-side build step on cPanel.

---

## 4. Scheduled database backups

Shared-hosting backup tools are limited, so the app takes its own daily MySQL dump (tracked in `PROGRESS.md`, module 1 — wire this in alongside the Notifications module):

```
0 2 * * * cd /home/cpaneluser/societyapp/backend && php artisan backup:database >> /dev/null 2>&1
```

Design: a `mysqldump` piped to `storage/app/backups/YYYY-MM-DD.sql.gz`, keeping the last 7 daily files (deleting older ones on each run) — stored on the **private** disk, never public. Until that artisan command is built, use cPanel's own **Backup Wizard** (Full/Partial account backup) as an interim manual safety net.

---

## 5. Mobile app configuration

The React Native app (`/mobile`, not yet scaffolded — see `PROGRESS.md`) will read its API base URL and Firebase config from environment/build config rather than hardcoding:

```
API_BASE_URL=https://api.yoursociety.com/api/v1
```

FCM: create a Firebase project, download `google-services.json` (Android) / `GoogleService-Info.plist` (iOS) into the mobile project, and put the **server-side** service-account JSON on the backend only (`FIREBASE_CREDENTIALS` in `.env` — see § 2.3), never in the mobile app bundle.

---

## 6. Post-deploy smoke test checklist

After each deploy, verify:

- [ ] `https://api.yoursociety.com/up` returns 200 (Laravel's built-in health route).
- [ ] `POST https://api.yoursociety.com/api/v1/auth/platform/login` with a seeded platform admin returns a token.
- [ ] `https://panel.yoursociety.com` loads and can log in against the API (confirms CORS is correctly scoped to the panel's domain).
- [ ] Cron is firing: `tail -f storage/logs/laravel.log` while waiting a minute, or check `SELECT * FROM jobs` empties out shortly after queuing a test job.
- [ ] `storage/app/public` files are reachable at `/storage/...`; a payment-proof-style file under `storage/app/private` is **not** reachable by direct URL.
- [ ] SSL padlock on both subdomains, HTTP→HTTPS redirect works.

---

## 7. Updating an existing deployment

```bash
cd ~/societyapp
git pull origin main
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

For the panel: rebuild (`npm run build` in `/web`) and re-upload `out/` as in § 3.2. No downtime coordination is needed for the panel since it's static files; for the API, migrations that only add tables/columns are safe to run live, but plan a maintenance window (`php artisan down` / `php artisan up`) for any migration that alters existing columns on a live production database.
