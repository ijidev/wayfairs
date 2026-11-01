# Wayfairs

An installable PHP banking operations script with separate user and admin
dashboards, editable branding/site information, KYC and restrictions, fiat and
crypto account workflows, immutable double-entry ledgering, transaction history,
transfers, invoices/receipts, and audited admin impersonation.

## Runtime requirements

- PHP 8.2+
- PDO SQLite for local preview or PDO MySQL for hosting
- Sodium, OpenSSL, cURL, mbstring, intl, fileinfo
- MySQL 8.4+ with InnoDB for hosted/production use
- Web root pointed to `public/`

No Composer or JavaScript build is required.

## Authenticated workspaces

- **Customer:** selected-currency overview, consolidated transactions,
  currency statements, printable receipts, deposits, withdrawals, transfers,
  invoices, verification, and security/account-status pages.
- **Administrator:** live operations dashboard, customer directory and detail,
  global transactions, deposit/withdrawal decision queues, expandable currency
  configuration, balanced customer funding/debit adjustments, site information,
  and immutable audit history.

The responsive interface uses plain CSS and dependency-free JavaScript. Search
and filter controls operate only on the persisted rows returned by the server.

### USD equivalents and live rates

- USD is the default customer account/card currency when it is enabled.
- Fiat rates are observed from [Frankfurter](https://frankfurter.dev/); crypto
  prices are observed from [CoinGecko](https://www.coingecko.com/).
- Successful observations are cached in `usd_rates`. Stale and unavailable
  states remain visible; the application never substitutes a sample price.
- CoinGecko keyless access is suitable only for this preview. Production should
  set `WAYFAIRS_COINGECKO_API_KEY` to a reviewed plan/key without exposing it to
  the browser.
- All conversions use decimal-string/integer arithmetic. The original ledger
  currency remains authoritative.

### External bank transfers

The customer transfer page defaults to USD and accepts beneficiary country,
legal name, bank, IBAN or local account number, routing code, and SWIFT/BIC.
Requests reserve funds and enter the administrator Bank Transfers queue. Full
destination details remain encrypted; browser pages expose safe summaries only.

### Browser translation

The language control uses the browser's on-device Translator API where
available. If unsupported, it directs the user to the browser's own translation
menu. No server-side or third-party translation widget receives page content.

## MySQL: one-file import

`database/mysql/wayfairs-install.sql` is the complete MySQL 8.4+ schema
(23 tables, append-only triggers, and the migration ledger) in one file. It is
generated from the numbered migrations, so do not edit it by hand:

    php scripts/build-mysql-install.php > database/mysql/wayfairs-install.sql

Upload it to an empty utf8mb4 database with the MySQL command line:

    CREATE DATABASE wayfairs CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
    mysql -u USER -p wayfairs < database/mysql/wayfairs-install.sql

Then open `/install` to create the administrator, site settings, and currencies.
The file contains no customer, balance, or administrator data. phpMyAdmin
usually imports it too, but the command line is the path verified by the
generator; it has not been executed against a live MySQL server in this
environment.

## Install

Two document-root layouts work; see [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md)
for the cPanel and shared-hosting walkthrough and the verification commands.

**A. Document root = `public/`** (recommended)

1. Upload the project outside the web root and point the domain/subdomain
   document root at `public/`.
2. Make `storage/` writable by the PHP process but not publicly accessible.
3. Open `https://your-domain/install`.

**B. Document root = project root** (for hosts that cannot move it)

1. Upload the whole project into `public_html/` with hidden files visible, so
   the shipped `.htaccess` files arrive. The root `.htaccess` + `index.php` send
   every request into `public/` and deny `app/`, `config/`, `database/`,
   `docs/`, `scripts/`, `storage/`, and `tests/` over HTTP.
2. Leave **Base path** empty at `/install`. With the shipped rules, `/public/...`
   and `/index.php` redirect to the clean URL.
3. If unreachable paths return files instead of `403`, stop: the host is
   ignoring `.htaccess` and Layout A is required.

**Either layout, then**

1. Provide the site, administrator, and database settings at `/install`. The
   installer writes `storage/config.php` with mode 0600 where the filesystem
   supports it, and disables itself afterwards.
2. Sign in as the administrator and complete **Admin → Site Info**, asset,
   deposit-instruction, and operating-limit configuration.

If the site was ever reachable through layout B before these rules existed,
read the exposure and rotation steps in `docs/DEPLOYMENT.md` first.

## Local development

Run `npm run dev` (or the equivalent PHP built-in-server command documented in
`package.json`) and complete `/install` with credentials you choose locally.
Generated configuration, databases, sessions, backups, and uploads are ignored
and must never be committed.

## Honest integration boundary

The script provides complete persisted manual workflows. A deposit is credited
only after an administrator records observed receipt; a withdrawal is debited
only when an administrator records completion. External references and remarks
are optional. Crypto
addresses and transaction hashes are manually recorded until a real custody/node
adapter exists. The UI never claims a provider or blockchain connection.

## Security boundary

Admin **Login as user** is support impersonation, not silent takeover: current
admin password and reason are required; the session is short-lived and visibly
bannered; all actions retain both identities; financial/security/configuration
mutations are denied until the administrator exits impersonation.

Customer administration supports creation, profile editing, global
active/restricted/blocked/closed status, and per-currency account controls.
Closing preserves immutable financial and audit history; transaction deletion
is intentionally unavailable.

Administrator funding/debiting is a separate explicit ledger adjustment—not an
impersonated customer action. It requires an adjustment note, prevents
overdrafts, posts balanced immutable entries, creates a customer-visible receipt,
and records the real administrator in the audit trail.
