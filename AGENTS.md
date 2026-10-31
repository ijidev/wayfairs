# Wayfairs PHP banking script guardrails

## Product truth

1. Never seed fake users, balances, KYC results, transactions, provider states,
   confirmations, or reconciliation. The installer may create only the
   owner-supplied administrator, site configuration, and selected asset catalog.
2. Fiat and crypto operations remain explicit manual settlement workflows until
   a real provider is configured and verified. Never label manual records as
   provider-connected, bank-settled, broadcast, confirmed, or reconciled.
3. Every completed value movement uses balanced immutable double-entry postings.
   Never update a balance column or use binary floating point for money.
4. SQLite is for local preview/testing only. Hosted financial use targets MySQL
   8.4+ with InnoDB and requires real MySQL migration, locking, concurrency,
   backup, and restore verification.

## Security

- The public document root is `public/`. Config, SQLite, keys, migrations, logs,
  and uploads must remain outside it and be denied by server configuration.
- Never commit credentials, customer PII, KYC documents, bank details, private
  keys, seed phrases, webhook secrets, or generated installation config.
- Passwords use PHP's strongest available `password_hash` algorithm. Sessions are
  random, server-stored, rotating, HttpOnly, SameSite, and CSRF-protected.
- Sensitive destination details are encrypted with Sodium using an install-time
  key. Logs and browser payloads expose only safe labels/last-four summaries.
- Admin impersonation requires a current administrator password and a reason,
  is short-lived and audited, displays a persistent banner, retains the real
  admin identity, and cannot perform financial, password, KYC-decision, role,
  restriction, or configuration mutations.
- Account status, KYC policy, limits, and capability restrictions are enforced
  server-side before holds or postings—not merely hidden in the UI.

## Engineering

- PHP 8.2+ with PDO; no Composer/runtime package is required.
- Keep SQLite and MySQL migrations semantically paired and numbered. Never edit
  an applied migration; add a new one.
- Controllers coordinate; domain services own authentication, ledger, banking,
  invoices, restrictions, auditing, and installation behavior.
- All state shown to users comes from persisted rows or real runtime probes.
- A feature is complete only after positive, denial, replay/idempotency, and
  failure-path tests. Production/provider readiness requires separate observed
  evidence.
