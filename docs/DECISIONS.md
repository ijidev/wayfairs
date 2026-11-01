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

## WDR-015 — A bank transfer asks for one banking route at a time

A beneficiary account is addressed either by IBAN or by a local account number,
never both. Showing IBAN, SWIFT, account number, and routing code together left
customers guessing which fields applied and risked submitting a stale IBAN after
switching intent.

The form now presents two selectable routes — **International (IBAN)** and
**Local account number** — and renders only the matching field group. The
inactive group is hidden, its inputs are disabled and cleared, and `required` is
applied only to the active route (IBAN, or account number). The selector is a
plain radio group, so it works without JavaScript; with scripting disabled both
groups are shown with a note to fill in whichever applies. Bank name, bank
address, beneficiary details, amount, and remark stay common to both routes.

No server change was needed: `BankingService::submitBankTransfer` already
accepts an IBAN **or** an account number and treats SWIFT/BIC and the routing
code as optional. Verified over HTTP by submitting a funded transfer through
each route; both reserved funds and both history rows redacted the destination
to last four characters.

## WDR-016 — The public homepage sells the product, the product shows the mechanics

The public homepage is marketing surface. It leads with "Your money. Multiple
currencies. One place." and covers multi-currency balances, receiving and
sending payments, personal and business use, invoices, security and trust, and a
final call to action. It deliberately excludes manual-settlement narration,
double-entry ledger description, Sodium encryption internals, impersonation
procedure, internal audit mechanisms, and any statistics that are not real.

Honesty is not removed, it is relocated: settlement state, hold behaviour, and
redacted destinations appear on the pages where money actually moves
(deposits, withdrawals, transfers, and their history tables). No provider,
integration, statistic, or testimonial is invented, and the homepage lists only
the asset codes the installation has enabled.

The installer's default tagline and footer changed to the same positioning; the
footer partial also replaces the previously seeded "secure manual settlement
preview" line on older installations. An administrator can still override either
value in Settings.

## WDR-017 — Self-repairing migrations instead of manual database steps

Date: 2026-11-01

Migrations already run on each request; the failure mode was a migration that
stopped part-way (MySQL DDL is not transactional, and SQLite batches cannot be
rolled back once run). The next request then failed on the same statement. The
migrator now:

- takes an exclusive lock (`storage/migrate.lock`) so two requests cannot apply
  the same migration at once;
- returns immediately when nothing is pending, so the per-request cost is one
  bookkeeping read;
- runs statements one at a time and skips `ADD COLUMN` clauses and `CREATE TABLE`
  statements that already exist, so an interrupted migration finishes on the
  next request instead of failing;
- keeps trigger bodies whole when splitting SQLite files;
- answers a failed update with a retry page and a server log line, not a crash.

No manual SQL is needed on deployment. Migration 004 is the first to rely on this.
Checksums of applied migrations are still enforced.

## WDR-018 — Bill payments reuse the transfer engine; flights are not faked

Date: 2026-11-01

Bill payments go through the same bank-transfer reservation and settlement path,
tagged with `withdrawal_requests.payment_purpose = 'bill'` (migration 004), so
they are listed on Pay a bill and kept out of transfer history. The biller's bank
details and bill reference are the only inputs.

Flight booking has no provider in this installation. The dashboard shows an
honest "not available on your account yet" card with no action, rather than a
search form that cannot book anything.

Transfer hardening: the selected route decides which identifier is used and any
value in the other field is ignored; IBAN and account numbers accept pasted
spaces, hyphens and dots; free text is cleaned to valid UTF-8 before encryption.
Migration 004 also clears the untouched seeded minimum deposit and withdrawal
(USD 10, BTC and ETH floors), and new installs seed none.

## WDR-019 — Bank-detail encryption falls back to OpenSSL AES-256-GCM

Date: 2026-11-01

Beneficiary bank details were encrypted only with libsodium. On hosts without
the sodium extension, every bank transfer and bill payment failed. Those hosts
still provide OpenSSL, which is already a required extension.

Decision: `Security::encrypt` uses libsodium `secretbox` when it is available,
which keeps every existing record and working server unchanged. When sodium is
missing it uses OpenSSL AES-256-GCM with a random 96-bit IV and a 128-bit tag,
keyed by the same 32-byte application key. Those records are stored with a
`gcm1:` prefix. Decryption reads either format; a sodium record on a server that
lacks sodium is refused with a clear message rather than failing silently.

Security properties are unchanged: the cipher is authenticated, the IV is fresh
per record, plaintext never leaves the server, and a tampered record is refused.
The project rule that names Sodium should now read "Sodium, or OpenSSL AES-256-GCM
where sodium is unavailable". That wording change is awaiting owner approval in
AGENTS.md.

Verified: unit tests cover both formats, tamper refusal, and the legacy sodium
path. A full transfer and bill run passed with sodium present and again with
sodium disabled through PHP's `disable_functions`.

## WDR-020 — Administrator switch for transfers; flight requests are reviewed by hand

Date: 2026-11-01

Bank transfers and bill payments stay off until an administrator turns them on
under Site information → Payments. Turning them on is refused when the server
cannot encrypt bank details. The switch defaults to off on every install.

Flight booking is a request workflow: customers describe the trip, and an
administrator moves it through requested, contacted, booked, declined, or
cancelled. Nothing is priced, charged, or booked automatically. Requests need a
verified identity. The admin status rules only move forward.

## WDR-021 — Payments are reviewed before sending; beneficiaries are matched exactly

Date: 2026-11-01

Bank transfers and bill payments no longer submit directly. The form stores the
entered details in a short-lived server-side draft (session store, 30-minute
expiry, never in hidden fields) and shows a review page with every value that will
be sent, including the held amount and the balance left afterwards. Confirming
re-validates the same stored input, so the confirmed details are the reviewed
details. A repeated confirm reuses the same idempotency token and does not create a
second payment.

Saved beneficiaries come in two scopes: administrator-published (visible to every
customer) and customer-saved (private to the owner). Account identifiers are stored
only inside encrypted details; a keyed hash supports lookup, and the last four
digits support display. A lookup returns the saved details only on an exact match
of the identifier, and never returns the identifier itself.

Schema note: migration 001 already creates an unused `beneficiaries` table from the
original design. Applied migrations are not edited, so the new table is
`saved_beneficiaries` (migration 006). The old table stays empty and unused.

Known limits: lookups are not yet rate-limited, so a customer could try many account
numbers. Matching is exact and returns only the holder name and bank, but a limit
should be added before public launch.
