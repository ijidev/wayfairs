# Wayfairs decision record

## WDR-001 — Dependency-free PHP distribution

Use PHP 8.2+ and PDO without Composer/runtime packages so the application can be
uploaded to standard shared hosting. A small internal autoloader and front
controller keep the distribution self-contained.

## WDR-002 — SQLite preview, MySQL hosted target

SQLite supports local installation and automated tests. MySQL 8.x or MariaDB
10.4+ with InnoDB is the hosted financial source of truth. Numbered migrations
are paired, and no SQLite test is accepted as evidence of MySQL
locking/concurrency behavior.

## WDR-003 — Manual rails are explicit

Without approved provider credentials, deposits/withdrawals are real persisted
requests with manual administrator verification/completion. They are not labeled
connected, settled, broadcast, confirmed, or reconciled by configuration alone.

## WDR-004 — Wayfairs is a setting, not hardcoded identity

The installer defaults the site name to **Wayfairs**. Administrators can change
name, tagline, support/contact, address, colors, and deposit instructions under
Site Info. Templates always read persisted settings.

## WDR-005 — Safe support impersonation

Impersonation requires administrator reauthentication and a reason, expires in
30 minutes, retains/restores the original admin session, is prominently shown,
and blocks value movement and sensitive mutations. Audit events retain the real
administrator and effective user.

## WDR-006 — Live USD estimates are provider-backed and non-authoritative

USD equivalents use cached observations from Frankfurter for fiat currencies
and CoinGecko for crypto assets. Decimal conversion never uses binary floating
point. The original asset amount remains authoritative, rate source/freshness is
shown, stale cache is labelled, and absence stays unavailable. CoinGecko's
keyless API is preview-grade; production requires an appropriate API plan and
`WAYFAIRS_COINGECKO_API_KEY`.

## WDR-007 — Bank transfers reuse the withdrawal settlement invariant

External beneficiary transfers are distinguished by `request_kind`, but reuse
the existing hold, encrypted destination, approval/rejection, double-entry, and
idempotency machinery. This avoids a second balance path. Full IBAN/account data
never enters browser queue payloads; operations sees a safe summary until an
approved secure payout integration exists.

## WDR-008 — Customer deletion means closure, never ledger erasure

Administrators can create and edit customers, block/restrict/close access, and
control individual currency-account status. Closing is reversible and preserves
users, transactions, journal entries, postings, receipts, and audit events.
There is no transaction-history deletion control.

## WDR-009 — Translation is browser-native and optional

The interface uses the browser Translator API when available, keeping
translation on-device. Unsupported browsers receive an honest browser-menu
fallback. Identifiers, inputs, account data, amounts, and customer identity
elements are excluded from the page text walker.

## WDR-010 — Administrator balance changes are explicit adjustments

Funding or debiting a customer never occurs by pretending to be that customer.
An active administrator posts an idempotent `adjustment` transaction through a
balanced journal entry. Debits cannot exceed available funds, closed accounts
are rejected, the note becomes customer-visible transaction evidence, and the
real administrator is retained in transaction metadata and the audit trail.

## WDR-011 — Remove an asset only after a zero-evidence check

The unused legacy fiat catalog was deleted only after an online mode-0600 backup
and observed zero transactions, postings, holds, requests, transfers, invoices,
beneficiaries, and crypto addresses. Its one empty account and corresponding
ledger scaffolding were then removed transactionally. Assets with financial
evidence remain immutable/archivable rather than deletable.

## WDR-012 — Public source does not imply an open-source grant

The standalone publication is prepared as a clean snapshot so ignored runtime
configuration, databases, sessions, backups, and prior preview credentials do
not enter public history. The project carries an explicit all-rights-reserved
license; public GitHub visibility alone grants no reuse rights.

## WDR-013 — Support both document-root layouts, with deny rules as the default

Shared hosting often refuses to move a domain's document root, so the entry
point must survive `public_html/` being the project root. The repository ships
a root `.htaccess` front controller plus a root `index.php` shim, and a deny
`.htaccess` in `app/`, `config/`, `database/`, `docs/`, `scripts/`, `storage/`,
and `tests/`. Pointing the document root at `public/` remains the recommended
layout, in which those root files are outside the web root and inert.

Consequences worth recording, because they are not obvious:

- A rewritten request enters `public/.htaccess`, and a child `.htaccess` takes
  over rewriting for its own subtree. The parent never sees `/public/...`, so
  the `/public` to clean-URL redirect must live in `public/.htaccess`. Verified
  by request tracing on a real Apache 2.4.58 instance; the parent-only version
  silently left `/public` in URLs.
- Directory listing was possible only because the document root had no
  `.htaccess`; `Options -Indexes` plus the rewrite fallback removes it, so the
  fix is not merely cosmetic. `storage/config.php`, `storage/*.sqlite`, and the
  executable `tests/run.php` were previously downloadable, which is a
  disclosure problem, so `docs/DEPLOYMENT.md` documents rotation steps.
- `HEAD` now routes to `GET` handlers with the body discarded. Without it every
  uptime monitor and `curl -I` saw 404, which would have made the documented
  verification matrix lie about a healthy deployment.
- The deployment guards (`root shim exists`, deny rules present, indexes off,
  `/public` canonicalisation) are asserted in `tests/run.php` because a missing
  dotfile during upload re-opens the exposure silently.

## WDR-014 — Hosted schema targets MariaDB and older MySQL, not MySQL 8 only

cPanel servers commonly run MariaDB, which has no `utf8mb4_0900_ai_ci`
collation and rejects the import with `ERROR 1273 Unknown collation`. The
hosted schema therefore declares `utf8mb4` with `utf8mb4_unicode_ci` on every
table, which exists in MySQL 5.5+ and MariaDB 10.x. No table relies on a
MySQL-8-only default, so an import succeeds even when the database itself was
created with the server's `latin1` default.

Two real defects were found only by executing the schema instead of trusting
it, both pre-existing and independent of the collation:

- `site_settings.updated_by` inherited `utf8mb4` while `users.id` is
  `ascii`/`ascii_bin`, so its foreign key failed with `ERROR 1005 ... incorrectly
  formed`. Every `CHAR(36)` key column now states its charset explicitly, and a
  test asserts that no such column is left implicit.
- The web installer path (`Migrator`) shared the same collation, so choosing
  MySQL in `/install` failed exactly like the file import. It now uses the same
  portable collation.

Evidence: the generated file was imported into MariaDB 10.11 (24 tables, 6
triggers, 3 ledger rows), the web installer completed against it, authenticated
admin pages rendered with zero PHP warnings, and credit/replay/debit/overdraft
adjustments produced balanced journals. MySQL 8.x was not installed here; the
claim that it works is untested and must be reported as such.
