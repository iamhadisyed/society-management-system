# Database Schema

Source of truth: `/backend/database/migrations/*`. This document is a navigable summary — for exact column types/constraints, read the migration file named in each section.

Conventions (enforced via `App\Support\Migration\Columns`, see `backend/app/Support/Migration/Columns.php`):
- Every **tenant-owned** table has `society_id` (FK → `societies.id`, cascade delete) — see `Columns::society()`.
- Every tenant-owned table has `created_by` / `updated_by` (FK → `users.id`), `created_at`, `updated_at`, `deleted_at` (soft delete) — see `Columns::audit()`.
- Money columns: `decimal(12,2)`, PKR only. Dates stored UTC, presented as `Asia/Karachi` / `DD-MM-YYYY`.
- Platform-level tables (no `society_id`): `societies`, `platform_admins`, `subscription_plans`, `society_subscriptions`, `platform_audit_log`.

## 0001_01_01_000000 — Users & auth base
- `users` — society staff + residents in one table, `user_type` enum(`society_staff`,`resident`), `society_id` nullable-then-FK'd, `email` nullable+unique-per-society, `phone`, `language` enum(en,ur), soft deletes.
- `password_reset_tokens` — email-based reset (optional channel; primary reset is admin-driven, see verification module).

## 0001_01_01_000010 — Platform tables
- `societies` — code (used by residents to find the society), logo, address, bank details (name/account/IBAN), `status` (active/suspended), `settings` JSON (sos_escalation_seconds, verification lockout, blood donor cooldown days, complaint reopen days, etc.)
- `platform_admins` — separate auth table/guard for the Platform Administrator (not society-scoped).
- `subscription_plans`, `society_subscriptions` — plan/price/cycle, per-society subscription with start/end/status.
- `platform_audit_log` — platform-admin actions (society created/suspended, plan changed, ...).

## 0001_01_01_000020 — RBAC, audit, notifications
- `permissions` — fixed code-defined catalogue (`bills.generate`, `complaints.reassign`, ...).
- `roles` — society-scoped, dynamic (created by Society Administrator); `is_default` marks seeded roles.
- `role_permission`, `role_user` — pivots.
- `audit_logs` — generic business audit trail (action, subject_type/id, old/new values JSON, IP, user_agent) — every create/edit/delete/approve/waive/collect/print writes here via a model observer.
- `device_tokens` — FCM tokens per user/device/platform.
- `notifications` — in-app notification center (type, title, body, data JSON deep-link, read_at).

## 0001_01_01_000030 — Units & property
- `unit_categories`, `tariff_types` — dynamic, society-scoped lookups.
- `blocks` → `streets` → `units` hierarchy.
- `units` — `reference_number` (permanent, unique, never reused), `current_bill_number` (denormalized, drives the verification flow), `linked_user_id`/`linked_at` (app-linked status), `residence_status` enum(owner,tenant), `owner_name`, `occupant_name`.

## 0001_01_01_000040 — Billing engine
- `charge_heads` — dynamic, `frequency` enum(monthly,quarterly,yearly,one_time).
- `rate_matrix` — Category × Tariff × Charge Head → amount, **versioned** via `effective_from`/`effective_to` (never updated in place once used).
- `unit_charge_overrides` — per-unit extra charge or waiver, `value_type` fixed/percent, approver, effective window.
- `bill_runs` — `status` draft→generated→locked, `announcement` text (Urdu/English), lock metadata.
- `bills` — one per unit per run; **snapshots** owner/address/tariff/app-linked-status at issue time so historic bills never change; `arrears`, `this_month_total`, `adjustments_total`, `surcharge_amount`, `payable_within_due`, `payable_after_due`, `amount_paid`, `status` (unpaid/partially_paid/paid/overdue).
- `bill_items` — line items (serial, charge head, description, amount).
- `bill_adjustments` — credit/debit lines with reason, optionally tied to a specific bill.
- `one_off_charges` — ad-hoc unit charges (form fees, booking fees, fines) queued to land on the next bill run.

## 0001_01_01_000050 — Payments, receipts, reconciliation
- `payments` — method (cash/bank/jazzcash/easypaisa/raast/other), collected_by.
- `payment_bill` — pivot: one payment can settle multiple bills (partial payments).
- `receipts` — own numbering, one per payment.
- `payment_proofs` — resident-uploaded proof, approve/reject workflow, links to resulting `payments` row on approval.
- `bank_statement_imports` + `bank_reconciliation_rows` — bulk CSV/Excel import, auto-match by reference/bill number + amount, `status` matched/unmatched/conflicting/resolved.
- `payment_gateway_transactions` — gateway abstraction (jazzcash/easypaisa/raast), stores raw gateway response JSON.

## 0001_01_01_000060 — Verification (no OTP)
- `verification_attempts` — every attempt logged (device_id, ip_address) for lockout/rate-limiting.
- `unit_claim_disputes` — raised when a unit already has `linked_user_id` and a new match comes in; never auto-overwrites.
- `pending_verifications` — name/bill-number mismatch queue for manual Society Admin approval (never a hard reject).

## 0001_01_01_000070 — Complaints
- `departments`, `department_agents` — dynamic departments with a head + agents.
- `complaint_categories` — dynamic, mapped to a department.
- `complaints` — `ticket_number` unique, `priority` (low/medium/high/urgent), `status` (open/assigned/in_progress/on_hold/resolved/closed/reopened/dropped), `sla_due_at`/`sla_breached`, `rating` 1-5.
- `complaint_status_history`, `complaint_comments`, `complaint_attachments` (before/after photos).

## 0001_01_01_000080 — SOS
- `sos_alerts` — type (medical/fire/security/other), lat/lng, status (pending/acknowledged/escalated/resolved), ack/resolve metadata.
- `sos_escalations` — log of who was notified on escalation (Security Supervisor, Society Admin).

## 0001_01_01_000090 — Blood bank
- `blood_donors` — multiple per user/unit, blood_group enum(8 groups), `is_available`, `last_donation_date` (drives cooldown auto-hide).
- `blood_requests` — group, units_needed, hospital, urgency, needed_by.
- `blood_request_responses` — donor "I can help" responses, `status` offered/contact_shared/declined.

## 0001_01_01_000100 — Home services & car pooling
- `service_categories`, `service_providers` (verified badge, listing_fee), `service_provider_reviews`.
- `ride_offers`, `ride_requests`, `ride_seat_requests` (accept/decline).

## 0001_01_01_000110 — Online forms
- `form_templates` — dynamic builder metadata: fee, fee_payment_mode, `requires_dues_clearance` (NOC), `generates_pdf`.
- `form_fields` — text/number/date/dropdown/checkbox/file, required flag, options JSON.
- `form_submissions` — status open/in_process/accepted/rejected, `generated_pdf_path`.
- `form_submission_values`, `form_comments`, `form_attachments`.

## 0001_01_01_000120 — Visitor & gate management
- `visitor_passes` — pre_approved/unexpected/delivery/ride_hailing, QR `code`, approval workflow.
- `gate_logs` — entry/exit timestamps, logged_by (guard).
- `domestic_staff`, `domestic_staff_logs` — CNIC, photo, entry/exit.
- `vehicles` — registration number, sticker number.

## 0001_01_01_000130 — Community modules
- `notices` (bilingual body, target all/blocks, pin, push_sent), `events` + `event_rsvps`.
- `gallery_albums` + `gallery_photos`.
- `info_desk_contacts`, `info_desk_documents` (bylaws/FAQ/forms).
- `polls`, `poll_options`, `poll_votes` (unique per unit).
- `lost_found_items` (lost/found, is_returned).
- `marketplace_listings` + `marketplace_listing_photos` (moderation workflow).
- `resident_directory_opt_ins`.
- `facilities`, `facility_bookings` (slot-based, approval, fee).
- `water_tanker_requests`.

## 0001_01_01_000140 — Advertisements
- `advertisers` — society_id nullable (platform-level advertisers allowed).
- `ad_campaigns` — scope platform/society, `target_society_ids` JSON, date window.
- `ad_placements` — home_carousel / ad_list / bill_pdf.
- `ad_impressions`, `ad_clicks` — lightweight event tables for reporting.

## 0001_01_01_000150 — Expenses, finance, staff & payroll
- `expense_categories`, `vendors`, `expenses` (attachment, approver, payment method).
- `staff` — CNIC, designation, department, wage_type (daily_wage/monthly_salary), rate.
- `staff_attendance` — daily marking, unique per staff/date.
- `payroll_runs` + `payslips` — allowances/deductions/advances, `pdf_path`.

## 0001_01_01_000160 — Ownership transfer (added, Pakistan-market fit)
- `ownership_transfers` — append-only ledger per unit: sale/inheritance/gift, previous/new owner name+CNIC, `transfer_fee` (optionally linked to a generated `one_off_charges` row), approval workflow (pending→approved/rejected→completed). Completing a transfer updates `units.owner_name`/`residence_status` and releases the unit's `linked_user_id` (new occupant re-verifies fresh).
- `ownership_transfer_documents` — sale deed, CNIC copies, etc.

## 0001_01_01_000170 — Elections / AGM (added, Pakistan-market fit)
- `elections` — nomination window, voting window, `status` (draft/nominations_open/voting_open/closed/cancelled), `results_visibility` (after_close/live).
- `election_positions` — e.g. President, General Secretary; `seats_available`.
- `election_candidates` — nominee (resident user + unit), symbol, manifesto, approval workflow.
- `election_votes` — one vote per unit per position (unique constraint), immutable once cast. Never exposed via a unit→candidate listing endpoint - only aggregate counts.
- `election_results` — certified per-candidate vote counts + winner flag, snapshotted at close (not just computed live) so a published result can't silently change if a vote row is later touched by a bug/migration.

## Not yet in migrations (planned, tracked in PROGRESS.md)
- Materialized/cached report tables are intentionally avoided — all reports are computed on read from the tables above to avoid drift; heavy aggregate reports use the `cache` table (file/database cache driver) for short-TTL caching instead of new tables.
