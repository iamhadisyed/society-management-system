# Decisions Log

Ambiguities in the spec and the decisions made to resolve them. Updated continuously as the build progresses.

## Added modules (post-spec, user-requested, 2026-09-21)
Two modules not in the original spec were added at the user's request, aimed at making the product a better fit for the Pakistani housing-society market specifically:
- **Ownership Transfer**: an append-only ledger of ownership/tenancy changes per unit (sale, inheritance, gift), with transfer fee charging and document attachments (sale deed, CNIC copies). Completing a transfer updates `units.owner_name`/`residence_status` and reuses the existing "release unit" mechanism (see Auth section below) so the new owner/tenant must re-verify their own app account - the old resident's login never silently carries over.
- **Elections / AGM**: nominations → candidates per position → secret ballot (one vote per unit per position) → certified results. Deliberately separate from the generic Polls module because society elections have a real procedural shape (nomination window, candidate approval, per-position seats, certified/published results) that a yes/no poll doesn't capture, and are often a legal/bylaws requirement for housing societies here.
- Ballot secrecy: `election_votes` stores which unit voted for which candidate (to enforce one-vote-per-unit-per-position and prevent double voting), but no list endpoint ever exposes the unit→candidate mapping - only aggregate counts are ever returned, and only after the election closes (or immediately if `results_visibility` allows live counts, mirroring the Polls module's `results_visibility` setting).

## Sequencing
- Build order follows the spec's required order: DB schema → API list → screens list → business rules → implement backend module-by-module → web → mobile.
- `/web` (Materialize "full-version", TypeScript, App Router) was supplied by the user as a zip and extracted as-is into `/web`. The "starter-kit" and `demo-configs` variants from the zip were discarded (not needed).

## Web template adaptation (Materialize → static export + Laravel API) — DONE
This plan has been executed (see git history on `task/web-static-export-fixes`); `npm run build` now produces a working static `out/` with zero errors. What actually happened, vs. the original plan above:
- **Auth**: `next-auth`/Prisma/`src/prisma` deleted entirely. `src/contexts/AuthContext.tsx` + `src/libs/apiClient.ts` (plain `fetch`, not axios - no need for the extra dependency) call the Laravel API and store the bearer token in `localStorage`. `AuthGuard`/`GuestOnlyRoute` became plain client components gated on this context instead of server-side `getServerSession()`.
- **Routing/redirects**: `next.config.ts` `redirects()` removed; `src/app/page.tsx` added as a client component replacing it (`router.replace()` to the default locale's home page). `generateStaticParams` added to `[lang]/layout.tsx` **and** to `[lang]/[...not-found]/page.tsx` - every dynamic segment needs it under `output: 'export'`, including catch-alls (which need a non-empty placeholder segment, not `[]`, since a required catch-all can't match zero segments).
- **Route handlers / fake-db / actions.ts**: all deleted outright rather than migrated - every file importing `src/app/server/actions.ts` (31 of them) turned out to be demo content with no real counterpart, so there was nothing to rewire.
- **Demo apps**: deleted (not just hidden from nav) - academy, chat, email, kanban, logistics, ecommerce, invoice, calendar, roles, permissions, user, charts, forms, react-table, widget/wizard/dialog-examples, pricing, faq, user-profile, crm/analytics/academy/ecommerce/logistics dashboards, the whole front-pages marketing site, and the duplicate "-v1/-v2" auth showcase pages (kept one real copy of reset-password/verify-email, UI-only, not backend-wired). This cascaded into removing now-orphaned support code: the Redux store's chat/calendar/kanban/email slices, several chart-library wrapper components, most of `components/dialogs/*`, and ~15 now-unused npm packages (next-auth, prisma, mapbox-gl, react-map-gl, @fullcalendar/*, @tiptap/*, recharts, apexcharts, etc).
- **Menu**: *not yet done*. `verticalMenuData.tsx`/`horizontalMenuData.tsx`/`searchData.ts` (~2,850 lines) still contain entries pointing at the deleted demo routes - harmless to the build (plain string `href`s) but will 404 if clicked. Deliberately left alone rather than improvised, since a real nav requires knowing what the actual panel screens will be; rebuild it as those screens get built.
- **Locales**: changed to `en`/`ur` (the template shipped `en`/`fr`/`ar`, with Urdu entirely missing). `ur` marked RTL; `ur.json` dictionary is a placeholder duplicate of `en.json`, flagged via a `_translation_status` key - needs real translation before shipping.
- One Next.js app serving both Platform Admin and Society panels based on `user.user_type` - *still pending*, no real panel screens exist yet (only auth pages + account-settings).

## Multi-tenancy (gotcha)
- `App\Models\User` deliberately does **not** use `BelongsToSociety`/`SocietyScope`. `Tenant::id()` resolves the tenant by reading the authenticated user (`Auth::guard('sanctum')->user()`); if `User` itself carried the global scope, resolving that same guard call would re-query `User`, re-triggering the scope, in infinite recursion (confirmed via a 500 "Maximum call stack size reached" while testing `/api/v1/me`). Society-scoped user listings (e.g. "all staff in my society") filter by `society_id` explicitly in the controller/service instead.

## Multi-tenancy
- `society_id` lives on every tenant-owned table. Platform-level tables (societies, subscription_plans, society_subscriptions, platform_admins, platform_ad tables, platform audit log) are the only ones without it.
- Enforced via a global Eloquent scope (`BelongsToSociety` trait + `SocietyScope`) applied automatically from the authenticated user's `society_id`, plus a `EnsureSocietyContext` middleware that 403s any request missing tenant context for tenant routes.
- Platform Administrator accounts are a separate `platform_admins` table/guard (not a role inside a society) since they are not scoped to any single society.

## Auth / no-OTP verification
- Resident login identity = unit's permanent `reference_number` + password (spec explicit). Staff/admin login = email + password.
- "Latest bill number" check is implemented as: `units.current_bill_number` (denormalized, updated every bill run) compared against user input; older numbers rejected with a clear message (not silently accepted).
- Claim dispute: if `units.app_user_id` already set, a second verification attempt with matching data does NOT overwrite it — instead creates a `unit_claim_disputes` row for admin resolution, and the original link is untouched.
- Failed-attempt lockout is tracked per `(device_id, unit_id)` and per source IP (`verification_attempts` table), 5 attempts → cooldown (configurable, default 30 min), enforced by both app-level check and Laravel's rate limiter middleware.

## Billing
- Rate versioning: `rate_matrix` rows are never updated in place after use; a new row with a later `effective_from` is inserted and the old row's `effective_to` is set. Bill generation always picks the rate row effective for the bill's billing month.
- Bill lock: `bill_runs.status` = draft → generated → locked. Only `draft`/`generated` runs can be regenerated; `locked` runs are immutable, corrections happen via `bill_adjustments` on the next run.
- Bill numbering: `bill_number` unique per `(society_id, bill_run_id sequence)`, human-readable format `{society_code}-{YYYYMM}-{unit_seq}`. `reference_number` is permanent per unit, assigned once at unit creation, never reused.

## SOS
- Escalation is double-guarded: the scheduler (`schedule:run` every minute) checks all unacknowledged SOS older than the escalation threshold AND the mobile polling endpoint (hit every 5–10s from the security dashboard) also runs the same escalation check inline on each call, so escalation isn't delayed by up to a full minute of cron granularity.

## API-only Laravel gotchas (found while testing the Billing module)
- Laravel 11's `ApplicationBuilder` registers a default `redirectGuestsTo(fn () => route('login'))` even when no web auth scaffolding exists. Since this app has no `login` named route, an unauthenticated request without an explicit `Accept: application/json` header (i.e. `expectsJson()` false) crashed with a 500 `RouteNotFoundException` instead of a clean 401 - the framework attempts the redirect before the exception handler ever sees an `AuthenticationException`. Fixed in `bootstrap/app.php` by overriding `redirectGuestsTo(fn () => null)` plus an explicit `AuthenticationException` JSON renderer, so every unauthenticated request gets `401 {"message":"Unauthenticated."}` regardless of the client's Accept header (mobile/web clients should still send it, but the API no longer depends on that).
- `RateMatrix`'s table is `rate_matrix` (singular, matching the migration), not Eloquent's guessed `rate_matrices` - needs an explicit `protected $table`. Worth double-checking on any other model whose class name pluralizes irregularly.

## Hosting/queues
- All "real-time" behavior (SOS, notifications) is push (FCM) + polling, never sockets, per the GoDaddy shared-hosting constraint.
- Queue driver: `database`. `schedule:run` cron (every minute) chains `queue:work --stop-when-empty --max-time=50` (kept under the 1-minute cron cadence) so no long-running worker process is needed.

## Currency/locale
- All monetary columns are `decimal(12,2)` in PKR, no multi-currency.
- All datetime columns stored UTC in DB (Laravel default), converted to `Asia/Karachi` and `DD-MM-YYYY` at the presentation layer (API resources / Blade PDF templates), per spec.

## Branding: logo + favicon (replacing template placeholders)

The web panel shipped with the Materialize template's default abstract
zigzag logo mark and Laravel/Next.js placeholder favicons — not real
branding. Replaced with:

- **Mark**: a flat, minimal rounded-square tile containing a simple white
  house pictogram (roof + wall + door cutout), no gradients/shading, in
  `web/src/@core/svg/Logo.tsx`. Chosen per user direction: wordmark =
  "Society" (full product name shown as text beside the icon, already
  wired via `themeConfig.templateName` in `Logo.tsx`'s wrapper component),
  style = modern minimal/flat, color = purple/white (matches the original
  spec's description of the resident app as "soft purple/white
  card-based UI").
- **Color**: `primaryColorConfig.ts`'s default `primary-1` changed from
  `#666CFF` (blue-indigo) to a genuine purple (`light #A78BFA` / `main
  #7C3AED` / `dark #5B21B6`, violet-500 family) so the whole app's default
  theme, not just the static logo, matches the purple/white direction.
  `primary-2..5` (alternate user-selectable theme colors) left untouched.
- **Wordmark text**: `themeConfig.templateName` changed from `'Materialize'`
  to `'Society'`.
- **Favicons**: regenerated from the same mark (rasterized via
  `cairosvg`/`Pillow`, embedding 16/32/48px PNG frames in a single
  multi-size `.ico`) and used to replace both `web/src/app/favicon.ico`
  (was the default Next.js icon) and `backend/public/favicon.ico` (was
  empty/unset).

## MySQL identifier length limit (election_results unique index)

A third instance of the "passes on SQLite, fails on real MySQL"
schema-bug class (see the `domestic_staff_logs` FK entry above):
Laravel's auto-generated name for the composite unique index on
`election_results` (`election_position_id` + `election_candidate_id`)
is `election_results_election_position_id_election_candidate_id_unique`
- 66 characters, over MySQL's hard 64-character identifier limit
(errno 1059). SQLite has no such limit, so it never surfaced locally.

Fixed by giving that index an explicit short name
(`election_results_position_candidate_unique`). Audited every other
composite `unique()`/`index()` and `foreignId()`-generated constraint
name across all 18 migration files by computing Laravel's naming
convention and its length directly - this was the only one over the
limit.
