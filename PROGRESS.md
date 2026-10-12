# Society Management System — Progress Tracker

Legend: [ ] not started · [~] in progress · [x] done

## 0. Foundation & Docs
- [x] Repo structure (/backend /web /mobile /docs)
- [x] docs/decisions.md (ambiguity log)
- [x] docs/schema.md
- [ ] docs/api.md + Postman collection
- [ ] docs/screens.md
- [x] docs/deployment.md (GoDaddy cPanel guide)
- [~] PROGRESS.md kept up to date (this file)

## 1. Backend — Core Platform
- [x] Laravel project bootstrap (composer, .env.example, config)
- [x] All 84 tables migrated as Laravel migrations (verified: migrate + rollback round-trip clean on SQLite)
- [x] Sanctum auth setup, API versioning /api/v1 (staff login, resident login, platform admin login, /me, logout — all tested end-to-end)
- [x] Multi-tenancy: society_id global scope (SocietyScope) + Tenant helper for console/queue context
- [x] Base model traits: SoftDeletes, HasAuditColumns (created_by/updated_by), BelongsToSociety, LogsAuditTrail
- [x] Audit log system (LogsAuditTrail trait + AuditLog::record() for business actions; verified writing rows)
- [x] Dynamic RBAC (roles, permissions, role_permission/role_user pivots) + PermissionSeeder + RoleProvisioningService (10 default roles)
- [x] No-OTP resident verification: auto-match, pending-manual-approval, claim-dispute, lockout/cooldown — all tested end-to-end
- [ ] File storage: private/public disks, upload service w/ image compression
- [x] Queue: database driver, scheduler wiring (schedule:run every minute -> queue:work chain, see routes/console.php)
- [ ] Notification system: FCM push + in-app notification center + queued bulk sends
- [ ] PDF service (DomPDF + Urdu font embedding) — package installed
- [ ] Excel import/export service (Laravel Excel) — package installed
- [ ] QR code service (pure-PHP) — package installed (simplesoftwareio/simple-qrcode, bacon/bacon-qr-code, no external binaries)
- [ ] DB backup scheduler (daily mysqldump, keep 7)

## 2. Backend — Platform Admin Module
- [x] Society onboarding CRUD (+ society admin account creation + role provisioning) — tested end-to-end
- [x] Subscription plans & society subscriptions — tested end-to-end
- [x] Platform-wide ads (advertisers, campaigns, placements) — impressions/clicks tracking endpoints pending (added in Ads module, shared models already in place)
- [x] Platform audit log & global reports (dashboard stats) — tested end-to-end

## 3. Backend — Units & Property Setup
- [x] Blocks / Streets / Units hierarchy (models + relations + CRUD API)
- [x] Unit categories (dynamic), Tariff types (dynamic) (models + CRUD API)
- [x] Unit CSV/Excel bulk import (UnitsImport, auto-creates missing lookups, per-row error reporting)
- [x] Unit CRUD API (reference number auto-generated + permanent, residence status, app-linked status, release-unit) — tested end-to-end

## 4. Backend — Billing Engine
- [x] Charge heads (dynamic, frequency)
- [x] Rate matrix (category × tariff × charge head) with versioning (effective_from) + grid bulk-set
- [x] Unit-level overrides (extra charge / waiver, fixed or percent) with approver
- [x] One-off charges per unit (model + folded into bill generation; raised by Ownership Transfer module already)
- [x] Arrears carry-forward (surcharge-inclusive once a bill goes overdue) — tested end-to-end
- [x] Adjustments (credit/debit lines) — standalone, folded into next bill run — tested end-to-end
- [x] Late payment surcharge config (fixed or percent, per-society setting)
- [x] Bill runs (generate/preview/lock/regenerate, queued chunked jobs via GenerateBillRunJob) — tested end-to-end incl. lock immutability
- [x] Bill PDF (3-copy layout, QR via SVG data URI, ads slot, announcements, app-linked line) — tested, valid PDF confirmed
- [x] Bulk bill print/download (queued job GenerateBillRunPdfJob, status+download endpoints) — tested with 75 units/225 copies
- [x] Overdue marking scheduler (bills:mark-overdue, daily) — tested end-to-end
- [ ] Due-date reminder push/in-app notifications (deferred to Notifications module #18)

## 5. Backend — Payments, Receipts, Reconciliation
- [ ] Cash/bank payment recording
- [ ] Payment proof upload + approve/reject workflow
- [ ] Bank statement import + auto-reconciliation (matched/unmatched/conflicting)
- [ ] Payment gateway abstraction (JazzCash/Easypaisa/Raast stub + callback routes)
- [ ] Receipts (own numbering, PDF)

## 6. Backend — Resident Registration & Verification (no OTP)
(Built as part of Module 1's core auth work — see VerificationController; tested end-to-end.)
- [x] Society lookup by code/search
- [x] Verification flow (unit + name + latest bill number match)
- [x] Pending manual approval queue
- [x] Claim disputes
- [x] Failed-attempt lockout & cooldown, IP rate limiting
- [ ] Password reset (admin-driven + optional email SMTP)
- [x] Unit switcher (multi-unit login, link-unit endpoint)
- [x] Release unit (admin action) — also triggered automatically by completing an Ownership Transfer

## 7. Backend — Complaints
- [ ] Categories (dynamic) → department mapping
- [ ] Departments, heads, agents
- [ ] Complaint CRUD + status machine + reassignment + priority
- [ ] SLA timers + overdue scheduler alerts
- [ ] Timeline/comments/photos
- [ ] Reopen window, rating after closure

## 8. Backend — SOS
- [ ] SOS create (type, GPS, resident info) + FCM high-priority push
- [ ] Acknowledge / resolve workflow
- [ ] Escalation timer (60s configurable) via scheduler + polling trigger
- [ ] SOS log & response-time report

## 9. Backend — Blood Bank
- [ ] Donor registry (multiple donors per unit) + cooldown auto-hide
- [ ] Donor search/filter (block-only privacy)
- [ ] Blood requests + compatibility push targeting
- [ ] "I can help" contact reveal flow

## 10. Backend — Home Services
- [ ] Categories (dynamic) + providers CRUD
- [ ] Provider ratings/reviews
- [ ] Optional listing fee

## 11. Backend — Car Pooling
- [ ] Offer ride / request ride
- [ ] Seat requests accept/decline + contact reveal
- [ ] Verified-residents-only restriction

## 12. Backend — Online Forms
- [ ] Dynamic form builder (fields, fee)
- [ ] Default forms seeded (NOC, Transfer, Construction, Tenant Reg, Move-in/out, Material Gate Pass)
- [ ] Dues clearance check for NOC + NOC PDF
- [ ] Submission workflow + comments/attachments

## 13. Backend — Visitor & Gate Management
- [ ] Pre-approved guest + QR/code generation
- [ ] Gate scan/entry logging
- [ ] Unexpected visitor approve/deny flow
- [ ] Delivery/ride-hailing entries
- [ ] Domestic staff registry + entry/exit logs
- [ ] Vehicle registration + sticker + guard search

## 14. Backend — Community Modules
- [ ] Notices/News (targeting, pin, push)
- [ ] Events + RSVP
- [ ] Gallery (albums/photos)
- [ ] Info Desk (contacts, bylaws, FAQs, documents)
- [ ] Polls & Surveys (one vote/unit)
- [ ] Lost & Found
- [ ] Marketplace + moderation
- [ ] Resident directory (opt-in)
- [ ] Facility/amenity booking + fees
- [ ] Water tanker requests

## 15. Backend — Advertisements
- [ ] Advertisers & campaigns (platform + society)
- [ ] Placements (carousel, ad list, bill PDF slot)
- [ ] Impressions/clicks tracking + reports
- [ ] Default house ad fallback

## 16. Backend — Expenses & Finance
- [ ] Expense categories, vendors, expense entries
- [ ] Vendor payables
- [ ] Reports (income/expense, collection, defaulters aging, charge-head income, payment methods)
- [ ] Resident transparency report
- [ ] PDF/Excel export

## 17. Backend — Staff & Payroll
- [ ] Staff records
- [ ] Attendance
- [ ] Payroll generation + salary slip PDF

## 18. Backend — Notifications & Scheduler wiring
- [ ] All triggers wired (bills, complaints, SOS, blood, forms, visitors, notices, events, polls, bookings)
- [ ] Bulk batched sends

## 19. Web Panel (Next.js + Materialize)
- [x] Template explored & demo content stripped (academy/ecommerce/invoice/logistics/email/chat/calendar/kanban/roles/permissions/user apps, charts/forms/react-table/widget-wizard-dialog-examples/pricing/faq/user-profile pages, crm/analytics/academy/ecommerce/logistics dashboards, front-pages marketing site, duplicate auth-variant showcase pages — see docs/decisions.md)
- [x] NextAuth + Prisma removed entirely; replaced with client-side token auth (AuthContext + apiClient hitting the Laravel API) — AuthGuard/GuestOnlyRoute rewired as client components
- [x] Static export build (output: 'export') verified — `npm run build` succeeds, 26 pages, zero errors, `out/` produced
- [x] generateStaticParams added for [lang] (en/ur) and the [...not-found] catch-all
- [x] Locales changed to en/ur (dropped template's fr/ar), ur RTL, placeholder ur.json dictionary flagged for real translation
- [ ] Role/permission-based dynamic navigation (current nav/search data still references deleted demo routes — needs a real rewrite once actual panel screens exist)
- [ ] Platform Admin Panel screens
- [ ] Society Management Panel screens (all roles/dashboards) — homePageUrl currently points at account-settings as a placeholder (no real dashboard built yet)
- [ ] Auth pages wired to Sanctum beyond login (register/forgot-password/reset-password/verify-email are UI-only placeholders, not yet calling real endpoints)

## 20. Mobile App (React Native)
- [ ] Project bootstrap + navigation shell
- [ ] Auth + unit switcher
- [ ] Resident mode screens
- [ ] Staff/Operations mode screens
- [ ] FCM integration (incl. SOS full-screen alarm)
- [ ] i18n English/Urdu + RTL

## 22. Backend — Ownership Transfer (added 2026-09-21, Pakistan-market fit)
- [x] Ownership transfer records (sale/inheritance/gift), documents, transfer fee charging
- [x] Approval workflow (Society Admin), on completion updates unit owner + releases app link
- [x] Transfer history per unit (audit trail of past owners) — append-only ledger

## 23. Backend — Elections / AGM (added 2026-09-21, Pakistan-market fit)
- [x] Elections + positions (seats per position)
- [x] Nomination window + candidate approval workflow
- [x] Secret ballot voting (one vote per unit per position)
- [x] Certified/published results (aggregate counts only, never unit->candidate mapping)

## 21. Non-functional
- [~] Seeders with realistic PK sample data (blocks, marla categories, tariff types, departments, sample units done; sample bills not yet seeded)
- [~] .env.example fully documented (backend done with production/Pakistan defaults + inline comments; web/mobile pending those apps)
- [x] Rate limiting on auth/verification (RateLimiter::for + throttle:auth/verification middleware)
- [x] CORS configured (env-driven CORS_ALLOWED_ORIGINS, defaults to panel domain in production)
- [x] docs/deployment.md complete (GoDaddy cPanel step-by-step: backend, static-export web, cron/scheduler, SSL, backups, smoke test, update procedure)
