# Society Management System

A complete, multi-tenant SaaS platform for housing societies in Pakistan — one Laravel API backing a Next.js web panel (Platform Admin + Society Management) and a role-based React Native mobile app (Resident + Staff/Operations), purpose-built to run on ordinary GoDaddy cPanel shared hosting.

> Status: **actively under construction, module by module.** This file is the single source of truth for "what is this, what's done, what's left, how do I run it." For the exhaustive module-by-module checklist, see [`PROGRESS.md`](./PROGRESS.md).

---

## 1. The idea

Pakistani housing societies mostly run maintenance billing, complaints, security, and community life on WhatsApp groups, paper registers, and spreadsheets. This project is a single system that gives:

- **The Platform Administrator** (the SaaS operator) a way to onboard societies, sell subscriptions, and run cross-society ads.
- **A Society's staff** (admin, billing officer, cashier, accounts, HR, department heads/agents, security, communications) one web panel with dynamic, checkbox-defined roles and permissions — no hardcoded job titles.
- **Residents** a mobile app for their bill, complaints, SOS, blood bank, visitor passes, community life, and more.
- **Guards and field agents** the same mobile app in "staff mode" for SOS response, gate logs, and complaint resolution.

Two things make it specifically fit the Pakistani market rather than being a generic HOA tool ported over:

1. **No SMS OTP anywhere** — too costly at scale here. Residents verify by matching their name + unit's latest bill number (no OTP, no phone verification cost), and the bill PDF itself carries an "app-linked: yes/no" line as an anti-fraud signal.
2. **Built for GoDaddy cPanel shared hosting**, not AWS — no long-running processes, no websockets, no Supervisor. Real-time features use FCM push + short polling; heavy work (bill generation, bulk PDFs) runs as chunked queued jobs driven by one cron line.

Two additional modules were added beyond the original spec, specifically because they're near-universal needs for Pakistani societies: **Ownership Transfer tracking** (sale/inheritance/gift, with transfer fees and document records) and an **Elections/AGM module** (nominations, secret ballot, certified results) — see `docs/decisions.md` for the reasoning.

Full original specification, every business rule, and every ambiguity resolution live in [`docs/decisions.md`](./docs/decisions.md).

---

## 2. Repository layout

```
/backend   Laravel 11 REST API (PHP 8.2+, MySQL) — see backend/
/web       Next.js web panel, built on the Materialize MUI template, static-export
/mobile    React Native app (Resident mode + Staff/Operations mode) — not yet started
/docs      schema.md, decisions.md, deployment.md, (api.md/screens.md pending)
PROGRESS.md   Full module-by-module checklist
```

Each backend module was built on its own git branch, tested against a running server end-to-end, then merged into `main`. `main` is the always-deployable branch.

---

## 3. Tech stack

| Layer | Choice | Why |
|---|---|---|
| Backend API | Laravel 11, PHP 8.2+, MySQL | Runs on any cPanel shared-hosting PHP stack |
| Auth | Laravel Sanctum (token-based) | Works for a static-export web app + mobile, no session/cookie coupling |
| Web panel | Next.js (Materialize MUI template), `output: 'export'` | No Node server needed on the host — plain static files |
| Mobile | React Native, one codebase, role-based UI | Resident and Staff/Operations share one app |
| Queue/Scheduler | Laravel `database` queue driver + one cron (`schedule:run` every minute) | No Supervisor/long-running workers required |
| PDF | DomPDF (barryvdh/laravel-dompdf) | Pure PHP, no external binary |
| QR codes | simplesoftwareio/simple-qrcode (SVG) | Pure PHP, no Imagick/GD binary dependency |
| Excel import/export | maatwebsite/excel | Bulk unit import, future reports export |
| Push notifications | Firebase Cloud Messaging | Free, works without a persistent connection |

---

## 4. What's been achieved

All of the below is implemented **and verified end-to-end against a running server** (not just written — actually exercised: HTTP requests, queue processing, generated PDFs inspected). Full detail with test notes: [`PROGRESS.md`](./PROGRESS.md).

### Backend (Laravel) — done
- **Database schema**: all 84+ tables as versioned migrations, multi-tenant (`society_id` + automatic query scoping), full audit columns, soft deletes everywhere. See [`docs/schema.md`](./docs/schema.md).
- **Auth & tenancy**: Sanctum token auth for three account types (society staff, residents, platform admins), automatic tenant scoping, dynamic RBAC with 10 seeded default roles and a checkbox-style permission catalogue.
- **No-OTP resident verification**: society/unit lookup, name+latest-bill-number matching, pending-manual-approval queue, claim-dispute handling for already-linked units, per-device/IP lockout, multi-unit switcher.
- **Platform Admin module**: society onboarding (creates the Society Administrator account + provisions default roles in one transaction), subscription plans, platform-run ad campaigns, dashboard stats, platform audit log.
- **Units & Property**: blocks/streets/units hierarchy, dynamic unit categories & tariff types, CSV/Excel bulk import, full CRUD.
- **Billing engine**: versioned rate matrix, unit-level overrides (extra/waiver), one-off charges, standalone credit/debit adjustments, arrears carry-forward, configurable late surcharge, bill runs (generate → lock → regenerate) as queued/chunked jobs, 3-copy bill PDF with QR code and ad slot, bulk PDF export, daily overdue-marking scheduler.
- **Ownership Transfer** *(Pakistan-market addition)*: sale/inheritance/gift ledger, transfer-fee charging, document uploads, approval workflow that updates the unit owner and safely releases the app account link.
- **Elections/AGM** *(Pakistan-market addition)*: nomination window, candidate approval, secret ballot (one vote per unit per position, never exposed per-voter), certified results.

### Backend — not yet built
Payments/reconciliation, Complaints, SOS, Blood Bank, Home Services, Car Pooling, Online Forms, Visitor & Gate Management, Community modules (notices/events/gallery/polls/marketplace/facility booking/water tanker), Advertisements' impression/click tracking endpoints, Expenses & Finance, Staff & Payroll, the Notifications system (FCM + in-app center + bulk sends), file upload/image-compression service, and DB backup scheduler. All of these have their tables already migrated (see `docs/schema.md`) — what's missing is the business logic, controllers, and routes.

### Web panel (`/web`) — not started
The Materialize Next.js template is in place, but none of the adaptation work described in `docs/decisions.md` (stripping `next-auth`/Prisma, wiring Sanctum auth, dynamic permission-based navigation, static-export config) has been done yet, and no real screens are wired to the API.

### Mobile app (`/mobile`) — not started
Empty; React Native project has not been scaffolded.

### Docs
- ✅ `docs/schema.md`, `docs/decisions.md`, `docs/deployment.md`
- ⬜ `docs/api.md` + Postman collection, `docs/screens.md`

---

## 5. Deploying this project (step by step)

Full detail, troubleshooting, and the post-deploy smoke-test checklist: **[`docs/deployment.md`](./docs/deployment.md)**. Condensed version:

1. **Create a MySQL database** in cPanel (*MySQL® Databases*) and note the host/name/user/password.
2. **Upload the Laravel backend** (`/backend`) to the server, outside the public web root, and point the `api.yoursociety.com` subdomain's document root at `backend/public`.
3. **Install dependencies**: `composer install --no-dev --optimize-autoloader`.
4. **Configure `.env`**: copy `backend/.env.example`, run `php artisan key:generate`, fill in DB credentials, `APP_URL`, `CORS_ALLOWED_ORIGINS` (the panel's domain), and mail/SMTP settings.
5. **Migrate & seed the permission catalogue**: `php artisan migrate --force && php artisan db:seed --class=Database\Seeders\PermissionSeeder --force`.
6. **Link storage & set permissions**: `php artisan storage:link && chmod -R 755 storage bootstrap/cache`.
7. **Cache for production**: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
8. **Add one cron job**, every minute: `* * * * * cd ~/societyapp/backend && php artisan schedule:run >> /dev/null 2>&1` — this alone drives the queue worker, overdue-bill marking, and every future scheduled task (SOS escalation checks, DB backups, etc.).
9. **Build and upload the web panel**: locally run `npm run build` in `/web` (with `output: 'export'` and `NEXT_PUBLIC_API_BASE_URL` set), then upload the contents of `web/out/` to the `panel.yoursociety.com` subdomain's document root.
10. **Enable SSL** on both subdomains via cPanel AutoSSL, force HTTPS.
11. **Smoke test**: hit `https://api.yoursociety.com/up`, log in as the seeded platform admin, load the panel, confirm CORS works, confirm a queued job drains within a minute.

Mobile app builds point `API_BASE_URL` at `https://api.yoursociety.com/api/v1` and ship via the Play Store/App Store/internal distribution — no server-side deploy step for it.

---

## 6. Local development

```bash
# Backend
cd backend
composer install
cp .env.example .env
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env   # or point at a local MySQL
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan serve

# In a second terminal, process queued jobs (bill generation, bulk PDFs, ...)
php artisan queue:work --stop-when-empty
```

Seeded accounts (via `DatabaseSeeder`): a platform admin (`platform@admin.test` / `password`) and a demo society (`DEMO01`) with a Society Administrator (`admin@demo.test` / `password`) plus realistic sample blocks/streets/units/categories/departments.

Web and mobile local setup will be documented here once those modules are underway.

---

## 7. Contributing workflow used on this project

Every module is built on its own branch off `main`, tested against a real running server (not just unit-tested in isolation), then merged with `--no-ff` and pushed. `main` is always the deployable branch. See git log / `PROGRESS.md` for the module-by-module history.
