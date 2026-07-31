# Product Requirements Document

## AfterburnerX — POS / CRM / Inventory Management System

| | |
|---|---|
| **Status** | Draft |
| **Owner** | Product / Engineering |
| **Target stack** | PHP 8.x, MySQL 8.x |
| **Document version** | 1.0 |
| **Last updated** | 2026-07-31 |

---

## 1. Summary

AfterburnerX is a self-hosted, all-in-one **Point of Sale (POS)**, **Customer Relationship Management (CRM)**, **Inventory**, **Product Catalog**, and **User Management** system built in PHP and MySQL. It targets small-to-medium retail and service businesses (single-store and multi-location) that want a system they can install on their own hosting/VPS, own their data, and update without a dev team on hand.

Two things distinguish AfterburnerX from a typical open-source POS:

1. **A quick installer** — a guided, browser-based setup wizard (à la WordPress/phpMyAdmin) that provisions the database, creates the admin account, and configures the store in minutes, on commodity shared/VPS hosting.
2. **Zip-based updates** — the system ships version updates as downloadable `.zip` packages that are applied in-app (or via CLI) with automatic backup, file diffing, and DB migrations, without requiring git or SSH access.

---

## 2. Goals & Non-Goals

### 2.1 Goals

- Provide a single system that unifies POS sales, inventory, product catalog, CRM, and user/role administration.
- Be installable by a non-technical user on standard LAMP/LEMP hosting in under 10 minutes.
- Support offline/degraded-connectivity POS operation at the register (local queue, sync on reconnect).
- Allow safe, versioned, rollback-capable updates via signed zip packages — no composer/git required in production.
- Support multi-store / multi-register / multi-till deployments from one install.
- Be extensible via a plugin/module hook system without modifying core files (so updates don't clobber customizations).
- Be secure by default (RBAC, audit logging, hashed secrets, CSRF/XSS/SQLi protections).

### 2.2 Non-Goals (v1)

- Native mobile apps (v1 ships a responsive/PWA web UI only).
- Built-in accounting/general-ledger (double-entry bookkeeping) — v1 exports to CSV/QuickBooks-compatible formats instead.
- Multi-tenant SaaS hosting (v1 is single-tenant, self-hosted per business; multi-tenant is a possible v2 direction).
- Built-in payment gateway processing beyond a pluggable adapter interface (actual gateway certifications are per-integration follow-up work).

---

## 3. Target Users & Personas

| Persona | Description | Key needs |
|---|---|---|
| **Store Owner / Admin** | Owns 1–N locations, non-technical to semi-technical | Fast setup, dashboards, safe updates, full control over roles/permissions |
| **Cashier / Sales Associate** | Uses the register daily | Fast checkout UI, barcode scanning, minimal training, works offline |
| **Store/Shift Manager** | Oversees a location | Shift/cash-drawer reconciliation, discounts/void approval, stock counts |
| **Inventory Manager** | Manages purchasing & stock | Purchase orders, receiving, transfers, low-stock alerts, supplier mgmt |
| **Marketing/CRM User** | Runs loyalty & outreach | Customer segmentation, purchase history, promotions, email/SMS campaigns |
| **System Integrator / IT** | Installs & maintains the system for a client | One-click installer, zip updates, backups, logs, hosting portability |
| **Developer (3rd party)** | Builds custom modules/reports | Documented hooks/API, stable DB schema, sandboxed module updates |

---

## 4. Scope Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         AfterburnerX Core                         │
├───────────┬───────────┬────────────┬────────────┬───────────────┤
│    POS    │ Inventory │  Products  │    CRM     │ Users & Roles │
├───────────┴───────────┴────────────┴────────────┴───────────────┤
│         Reporting & Dashboards  |  Notifications  |  Audit Log    │
├───────────────────────────────────────────────────────────────────┤
│   Installer (setup wizard)   |   Updater (zip-based, in-app)      │
├───────────────────────────────────────────────────────────────────┤
│              PHP 8.x App Layer  +  MySQL 8.x Storage               │
└─────────────────────────────────────────────────────────────────┘
```

---

## 5. Functional Requirements

### 5.1 Installer

**Goal:** a non-technical user can go from "unzip on host" to "logged into a working store" in under 10 minutes, with no CLI required (CLI path also supported for power users).

- **FR-INS-01** — Distributed as a single downloadable zip; user uploads/extracts to web root (or `public_html` subfolder) via FTP/hosting file manager or CLI unzip.
- **FR-INS-02** — First request to the app auto-detects "not installed" state and redirects to `/install`.
- **FR-INS-03** — Step 1: Environment/pre-flight check — PHP version, required extensions (`mysqli`/`pdo_mysql`, `mbstring`, `gd`/`imagick`, `curl`, `zip`, `openssl`, `intl`, `bcmath`), writable directories (`/storage`, `/uploads`, `/config`), and memory/upload limits — with pass/fail/remediation hints per item.
- **FR-INS-04** — Step 2: Database connection form (host, port, name, user, password, prefix) with a "Test Connection" action before proceeding.
- **FR-INS-05** — Step 3: Schema install — creates all tables, indexes, FKs, and seed/reference data (currencies, tax classes, default roles, unit-of-measure list) inside a transaction; rolls back cleanly on failure with a readable error.
- **FR-INS-06** — Step 4: Business profile — store name, logo, currency, timezone, locale, default tax rate, business address.
- **FR-INS-07** — Step 5: Admin account creation — enforced strong password policy, email verification optional/toggleable.
- **FR-INS-08** — Step 6: Optional sample data toggle (demo products/customers) for evaluation installs.
- **FR-INS-09** — On completion: generates `/config/app.php` (or `.env`) with DB creds, a random `APP_KEY` encryption key, and writes an `INSTALLED` lock file/flag; the `/install` route becomes inaccessible afterward unless the lock file is removed manually.
- **FR-INS-10** — CLI installer equivalent: `php artisan-style bin/install.php --config=install.json` for scripted/unattended deployments.
- **FR-INS-11** — Installer logs each step to `storage/logs/install.log` for support diagnostics.
- **FR-INS-12** — Support install on both Apache (`.htaccess`) and Nginx (documented sample server block), plus PHP-FPM.

### 5.2 Updater (zip-based)

**Goal:** deliver new versions as `.zip` update packages, applied safely from the admin UI or CLI, with rollback.

- **FR-UPD-01** — Admin ▸ System ▸ Updates screen shows current version, checks a configurable update-feed URL (JSON manifest) for newer versions, or accepts a manually uploaded `.zip`.
- **FR-UPD-02** — Update packages are cryptographically signed (checksum + public-key signature); the updater verifies signature/hash before applying anything and rejects tampered packages.
- **FR-UPD-03** — Update manifest inside the zip declares: version, min-required current version, file manifest (added/changed/removed paths), and an ordered list of DB migration scripts.
- **FR-UPD-04** — Before applying: system puts the app into maintenance mode, takes an automatic **file backup** (zip snapshot of changed paths) and a **DB backup** (`mysqldump` or equivalent) into `storage/backups/{timestamp}/`.
- **FR-UPD-05** — Update applies file changes (extract/overwrite/delete per manifest) then runs pending DB migrations in a transaction where the DB engine allows it; each migration is idempotent/re-runnable.
- **FR-UPD-06** — On any failure mid-update, system automatically restores from the just-created backup and reports the failure reason; partial updates must never leave the system in an inconsistent state.
- **FR-UPD-07** — Post-update: cache/config clear, version number bump recorded in DB `system_meta`, maintenance mode lifted, changelog shown to admin.
- **FR-UPD-08** — Custom code protection: core files vs. user-customizable areas (themes, `/modules/custom/*`, uploaded media, config) are clearly separated so updates never overwrite site-specific customizations or uploads.
- **FR-UPD-09** — CLI equivalent: `php bin/update.php --package=update-2.3.0.zip` for headless/scripted environments.
- **FR-UPD-10** — Update history log (who applied it, when, from/to version, success/failure) visible in admin.
- **FR-UPD-11** — "Check for updates" can run on a schedule (cron) and just notify admins (email/in-app) without auto-applying, unless auto-update is explicitly enabled.

### 5.3 User Management & Access Control

- **FR-USR-01** — Role-Based Access Control (RBAC) with system-defined roles (Super Admin, Store Manager, Cashier, Inventory Manager, Marketing/CRM, Accountant, Read-only/Auditor) and support for custom roles with granular permission toggles (per module, per action: view/create/edit/delete/approve/export).
- **FR-USR-02** — Per-location user assignment (a user can be scoped to one or more stores/registers).
- **FR-USR-03** — Authentication: email+password with bcrypt/argon2 hashing, optional 2FA (TOTP), account lockout after N failed attempts, password reset via email token.
- **FR-USR-04** — Session management: configurable session timeout, forced logout on password change, "active sessions" view with remote revoke.
- **FR-USR-05** — PIN-based quick login/switch-user at the POS terminal for fast cashier handoff without full logout.
- **FR-USR-06** — Full audit log of sensitive actions (login, void, refund, discount override, price change, user/role change, stock adjustment, settings change) with actor, timestamp, IP, before/after values.
- **FR-USR-07** — User profile: name, contact, avatar, employment info (optional HR-lite fields: hire date, hourly wage for future payroll/timeclock module).
- **FR-USR-08** — Optional employee time clock (clock in/out) tied to shifts, for future payroll export.

### 5.4 Product Catalog

- **FR-PRD-01** — Product types: simple, variable (size/color/etc. variants), bundle/kit, service (non-inventoried), and composite (assembled from other products, e.g., recipes).
- **FR-PRD-02** — Core fields: SKU, barcode (UPC/EAN/QR, with generator for products lacking one), name, description, category/subcategory, brand, unit of measure, cost price, retail price, multiple tax classes, images (multi), custom attributes.
- **FR-PRD-03** — Category/attribute management: nested categories, custom attribute sets per category (e.g., size/color for apparel).
- **FR-PRD-04** — Pricing: base price, per-location price override, customer-tier/wholesale pricing, scheduled sales/promotions, quantity-break pricing.
- **FR-PRD-05** — Bulk import/export via CSV/XLSX with field mapping and validation preview; bulk edit for price/category/tax changes.
- **FR-PRD-06** — Barcode label printing (configurable label templates, batch print).
- **FR-PRD-07** — Product search: fast full-text + barcode-exact + SKU-exact lookup optimized for POS scan speed (<200ms typical).

### 5.5 Inventory Management

- **FR-INV-01** — Real-time stock levels per product per location, decremented on sale, incremented on receiving/returns/adjustments.
- **FR-INV-02** — Stock adjustments with required reason codes (damage, theft/shrinkage, count correction, sample/internal use) and audit trail.
- **FR-INV-03** — Purchase Orders: create PO to a supplier, receive partial/full shipments, auto-update cost and stock on receipt, three-way match (PO vs. receipt vs. supplier invoice) optional.
- **FR-INV-04** — Supplier/vendor management: contact info, lead time, preferred products, purchase history.
- **FR-INV-05** — Stock transfers between locations with in-transit tracking and receiving confirmation.
- **FR-INV-06** — Cycle counts / full physical inventory counts, with variance report vs. system stock, and one-click apply-adjustments.
- **FR-INV-07** — Low-stock and out-of-stock alerts (dashboard + email/notification), configurable reorder points and reorder quantities per product/location.
- **FR-INV-08** — Costing method configurable per install: FIFO, weighted average, or fixed cost (affects COGS reporting).
- **FR-INV-09** — Serial/lot/batch number tracking (optional per product) with expiration date tracking for perishable/regulated goods.
- **FR-INV-10** — Inventory valuation report (on-hand qty × cost) at any point in time, by location/category.

### 5.6 Point of Sale (POS)

- **FR-POS-01** — Touch-optimized register UI: category/product grid + search + barcode scan, cart, customer attach, payment.
- **FR-POS-02** — Split tender (cash, card, store credit, gift card, multiple payments on one sale), partial payments/layaway.
- **FR-POS-03** — Discounts: line-item and cart-level, percentage or fixed, coupon codes, manager-approval workflow for overrides beyond a threshold.
- **FR-POS-04** — Returns & exchanges (with/without receipt, restocking rules, refund to original tender or store credit).
- **FR-POS-05** — Held/parked sales (suspend a cart and resume later), quotes/estimates convertible to sales.
- **FR-POS-06** — Cash drawer management: open/close shift with starting float, cash-in/cash-out, end-of-shift reconciliation report (expected vs. counted).
- **FR-POS-07** — Receipt printing (thermal 58/80mm) and emailed/SMS digital receipts; configurable receipt templates.
- **FR-POS-08** — Hardware integration layer: receipt printers (ESC/POS via browser print or local print agent), barcode scanners (keyboard-wedge/HID), cash drawer kick (via printer), card readers via pluggable payment adapters.
- **FR-POS-09** — Offline mode: register keeps functioning against a local cache (IndexedDB/local queue) during connectivity loss; transactions sync to the server once reconnected, with conflict/duplicate-sale detection.
- **FR-POS-10** — Multi-register / multi-till per location, each with its own session, drawer, and reconciliation.
- **FR-POS-11** — Tax handling: multiple tax rates/classes, tax-inclusive or tax-exclusive pricing, tax-exempt customers.

### 5.7 CRM

- **FR-CRM-01** — Customer records: contact info, addresses, tags/segments, notes, purchase history, lifetime value, linked loyalty account.
- **FR-CRM-02** — Loyalty program: points earn/redeem rules, tiers, configurable per business.
- **FR-CRM-03** — Store credit / gift card management (issue, redeem, balance lookup, expiration rules).
- **FR-CRM-04** — Segmentation: filter customers by spend, recency/frequency, tags, product affinity — usable as targets for campaigns.
- **FR-CRM-05** — Campaigns: email/SMS broadcast to a segment (pluggable provider adapters — e.g., SMTP, Twilio-style SMS interface), with basic open/click tracking for email.
- **FR-CRM-06** — Customer-facing communication log (which campaigns/receipts/notifications were sent, when).
- **FR-CRM-07** — Wishlists/saved carts and "notify when back in stock" (optional, ties CRM to Inventory).
- **FR-CRM-08** — GDPR/CCPA-friendly data export and deletion request handling per customer record.

### 5.8 Reporting & Dashboards

- **FR-RPT-01** — Owner dashboard: sales today/week/month, top products, low stock, cash position, at-a-glance KPIs, per location and consolidated.
- **FR-RPT-02** — Sales reports: by day/week/month, by cashier, by location, by category/product, by payment type.
- **FR-RPT-03** — Inventory reports: valuation, turnover, dead stock, shrinkage.
- **FR-RPT-04** — CRM reports: new vs. returning customers, top customers, loyalty liability, campaign performance.
- **FR-RPT-05** — Financial exports: CSV/XLSX/PDF export of any report; QuickBooks-compatible CSV for sales/COGS.
- **FR-RPT-06** — Scheduled email reports (daily close-out summary to owner, weekly digest).

### 5.9 Settings & Configuration

- **FR-CFG-01** — Multi-location/store configuration (name, address, tax jurisdiction, receipt footer, business hours).
- **FR-CFG-02** — Tax rules engine (rates, classes, jurisdiction rules, exemptions).
- **FR-CFG-03** — Payment method configuration and payment gateway adapter management.
- **FR-CFG-04** — Notification settings (email/SMTP config, SMS provider keys, which events trigger notifications).
- **FR-CFG-05** — Backup settings: scheduled DB/file backups (independent of the updater's pre-update backups), retention policy, download/restore from admin UI.
- **FR-CFG-06** — Module/plugin management: enable/disable installed modules; modules register via a defined hook/service-provider interface.
- **FR-CFG-07** — API keys/webhooks management for external integrations.

---

## 6. Non-Functional Requirements

| Category | Requirement |
|---|---|
| **Compatibility** | PHP 8.1+ (target 8.2/8.3), MySQL 8.0+ / MariaDB 10.6+; common shared hosting (cPanel/Plesk) and VPS/Docker. |
| **Performance** | POS barcode-to-cart add < 300ms server-side; product search results < 500ms for catalogs up to 100k SKUs; checkout completion < 1s excluding external payment gateway latency. |
| **Scalability** | Single install should comfortably support 50 registers / 20 locations / 500k products / 1M customers with proper indexing; horizontal read scaling (read replica support) documented for large installs. |
| **Availability** | POS must remain operable during short outages via offline mode (§5.6 FR-POS-09). |
| **Security** | OWASP Top 10 mitigations: prepared statements/PDO only (no raw SQL concatenation), output escaping, CSRF tokens on all state-changing forms, rate limiting on auth endpoints, secure password hashing (argon2id), encrypted secrets at rest, HTTPS enforced in production mode, signed update packages, role-gated API. |
| **Data integrity** | Foreign keys enforced (InnoDB), monetary values stored as integers (minor units) or DECIMAL — never float; all stock/cash mutations logged and reconstructable via audit trail. |
| **Backup/DR** | Automated daily DB backup + on-demand backup, one-click restore, pre-update automatic snapshot with rollback (§5.2). |
| **Localization** | i18n-ready string tables, multi-currency display, multi-timezone, RTL-ready layout for future language packs. |
| **Accessibility** | POS and admin UI meet WCAG 2.1 AA where practical (contrast, keyboard navigation, focus states). |
| **Browser support** | Latest 2 versions of Chrome, Edge, Firefox, Safari; POS UI optimized for tablet/touch as well as desktop. |
| **Extensibility** | Hook/event system (e.g., `before_sale_complete`, `after_stock_adjust`) and module folder convention so third-party modules survive core updates. |
| **Observability** | Structured application logs, error logging with configurable verbosity, health-check endpoint for monitoring. |

---

## 7. High-Level Architecture

- **Pattern:** Server-rendered PHP MVC core (no heavy framework lock-in assumed, but structured like Laravel/Symfony conventions: routes → controllers → services → models/repositories) with a REST/JSON API layer used by the POS front-end (progressive web app: HTML/CSS/JS, works installed-to-homescreen on tablets) and by future integrations.
- **Database:** MySQL/InnoDB, normalized schema with clear module boundaries: `users/roles`, `products/categories/inventory`, `sales/orders/payments`, `customers/loyalty`, `system/settings/audit`.
- **File layout convention** (so updates don't clobber customizations):
  ```
  /app          -> core application code (overwritten by updates)
  /modules/core -> first-party modules (overwritten by updates)
  /modules/custom -> third-party/custom modules (never touched by updater)
  /public       -> web root, assets
  /storage      -> logs, cache, backups, uploads (never touched by updater)
  /config       -> app.php / .env, generated at install (never touched by updater)
  /install      -> installer app (disabled after install)
  ```
- **Background jobs:** cron-driven queue for emails/SMS, scheduled reports, update checks, stock alerts (simple DB-backed job queue to avoid requiring extra services on shared hosting; Redis-backed queue optional for larger installs).
- **API:** versioned REST API (`/api/v1/...`) with token auth, used internally by the POS SPA and available for external integrations (e-commerce sync, accounting, etc. — out of scope for v1 beyond the interface).

---

## 8. Core Data Model (indicative)

Key entities (not exhaustive — full ERD to be produced in technical design phase):

- `users`, `roles`, `permissions`, `role_permissions`, `user_locations`, `audit_logs`
- `locations`, `registers`, `shifts`, `cash_drawer_events`
- `products`, `product_variants`, `categories`, `attributes`, `suppliers`, `purchase_orders`, `purchase_order_items`, `stock_levels`, `stock_movements`, `stock_transfers`
- `customers`, `customer_addresses`, `loyalty_accounts`, `loyalty_transactions`, `store_credits`, `gift_cards`, `campaigns`
- `sales`, `sale_items`, `sale_payments`, `returns`, `discounts`, `tax_classes`, `tax_rates`
- `system_meta` (current version), `update_history`, `settings`, `modules`

---

## 9. Milestones / Phasing

| Phase | Scope | Outcome |
|---|---|---|
| **Phase 0 — Foundations** | Repo scaffolding, DB schema design, auth/RBAC, installer skeleton | Installable empty shell with login |
| **Phase 1 — Core Retail MVP** | Products, Inventory (basic), POS checkout, Users/roles, basic reporting | Usable single-store POS |
| **Phase 2 — Multi-location + Purchasing** | Multi-store/register, suppliers, POs, transfers, cycle counts | Operable for small chains |
| **Phase 3 — CRM & Loyalty** | Customers, loyalty, store credit/gift cards, campaigns | Retention/marketing features live |
| **Phase 4 — Updater & Hardening** | Zip update pipeline, signed packages, backup/rollback, offline POS mode | Production-grade lifecycle management |
| **Phase 5 — Reporting & Extensibility** | Advanced reports, module/hook system, API v1, scheduled reports | Platform ready for 3rd-party modules |

---

## 10. Success Metrics

- Time-to-first-sale after download: **< 15 minutes** (unzip → installer → first completed POS transaction).
- Update success rate: **> 99%** of applied updates complete without manual intervention; **100%** of failed updates auto-rollback cleanly.
- POS transaction completion time: **< 10 seconds** average for a 5-item cash sale.
- Zero data-loss incidents from updates (backups verified restorable).
- Support ticket volume related to "installation" trending down release-over-release.

---

## 11. Risks & Open Questions

| Risk / Question | Notes |
|---|---|
| Shared hosting variability (no shell access, restricted `exec()`, varying PHP builds) | Installer/updater must work through pure PHP (e.g., `ZipArchive`) without relying on shell `unzip`/`mysqldump` binaries; need a PHP-native DB dump/restore fallback. |
| Zip update security | Package signing key management — where are signing keys held, how are they rotated, how is the public key distributed/pinned in the app? |
| Offline POS conflict resolution | Need a defined policy for duplicate/conflicting stock decrements when multiple offline registers sync simultaneously. |
| Payment gateway scope | Which gateways are "officially" supported at launch vs. left to the adapter interface for the community/integrators? |
| Multi-currency vs. multi-tenant | Confirm v1 truly stays single-tenant, single-currency-per-store to bound scope. |
| Framework choice | Decide: lightweight custom MVC vs. adopting Laravel/Symfony/CodeIgniter — affects installer complexity (composer dependency management on shared hosting) and update packaging strategy. |
| Licensing/update-feed hosting | Where does the update manifest/feed live, and is there a license-key gate on updates for a commercial version? |

---

## 12. Out of Scope for v1 (Candidates for Later)

- Native iOS/Android apps
- Built-in e-commerce storefront / website builder
- Full payroll and HR suite
- Multi-tenant SaaS control plane
- AI-based demand forecasting / auto-reordering
- Marketplace for third-party modules
