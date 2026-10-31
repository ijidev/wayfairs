CREATE TABLE site_settings (
  setting_key TEXT PRIMARY KEY,
  setting_value TEXT NOT NULL DEFAULT '',
  is_public INTEGER NOT NULL DEFAULT 0 CHECK (is_public IN (0, 1)),
  updated_by TEXT,
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE TABLE users (
  id TEXT PRIMARY KEY,
  role TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('admin','user')),
  email TEXT NOT NULL,
  email_normalized TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  first_name TEXT NOT NULL,
  last_name TEXT NOT NULL,
  phone TEXT,
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','restricted','blocked','closed','pending')),
  email_verified_at TEXT,
  failed_login_attempts INTEGER NOT NULL DEFAULT 0,
  locked_until TEXT,
  last_login_at TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE INDEX users_role_status_idx ON users(role, status);
CREATE INDEX users_created_at_idx ON users(created_at);

CREATE TABLE sessions (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL UNIQUE,
  parent_session_id TEXT REFERENCES sessions(id) ON DELETE SET NULL,
  original_admin_id TEXT REFERENCES users(id) ON DELETE SET NULL,
  impersonation_reason TEXT,
  impersonation_expires_at TEXT,
  ip_hash TEXT,
  user_agent TEXT,
  last_seen_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  expires_at TEXT NOT NULL,
  revoked_at TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE INDEX sessions_user_active_idx ON sessions(user_id, revoked_at, expires_at);
CREATE INDEX sessions_original_admin_idx ON sessions(original_admin_id, created_at);

CREATE TABLE password_reset_tokens (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TEXT NOT NULL,
  used_at TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE TABLE kyc_profiles (
  user_id TEXT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  status TEXT NOT NULL DEFAULT 'not_started' CHECK (status IN ('not_started','pending','in_review','verified','rejected','needs_resubmission','expired')),
  date_of_birth TEXT,
  country_code TEXT,
  address_line1 TEXT,
  address_line2 TEXT,
  city TEXT,
  region TEXT,
  postal_code TEXT,
  id_type TEXT,
  id_last4 TEXT,
  submission_reference TEXT,
  submitted_at TEXT,
  reviewed_by TEXT REFERENCES users(id) ON DELETE SET NULL,
  reviewed_at TEXT,
  review_reason TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE INDEX kyc_status_submitted_idx ON kyc_profiles(status, submitted_at);

CREATE TABLE capability_restrictions (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  capability TEXT NOT NULL CHECK (capability IN ('login','fiat_deposit','fiat_withdrawal','crypto_deposit','crypto_withdrawal','internal_transfer','invoice','profile_security')),
  reason_code TEXT NOT NULL,
  reason TEXT NOT NULL,
  starts_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  expires_at TEXT,
  released_at TEXT,
  released_by TEXT REFERENCES users(id) ON DELETE SET NULL,
  release_reason TEXT,
  created_by TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE INDEX restrictions_user_capability_idx ON capability_restrictions(user_id, capability, released_at, expires_at);

CREATE TABLE assets (
  id TEXT PRIMARY KEY,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  asset_type TEXT NOT NULL CHECK (asset_type IN ('fiat','crypto')),
  symbol TEXT NOT NULL,
  scale INTEGER NOT NULL CHECK (scale BETWEEN 0 AND 18),
  enabled INTEGER NOT NULL DEFAULT 1 CHECK (enabled IN (0, 1)),
  deposit_enabled INTEGER NOT NULL DEFAULT 1 CHECK (deposit_enabled IN (0, 1)),
  withdrawal_enabled INTEGER NOT NULL DEFAULT 1 CHECK (withdrawal_enabled IN (0, 1)),
  min_deposit_minor INTEGER NOT NULL DEFAULT 0 CHECK (min_deposit_minor >= 0),
  min_withdrawal_minor INTEGER NOT NULL DEFAULT 0 CHECK (min_withdrawal_minor >= 0),
  withdrawal_fee_minor INTEGER NOT NULL DEFAULT 0 CHECK (withdrawal_fee_minor >= 0),
  transfer_fee_minor INTEGER NOT NULL DEFAULT 0 CHECK (transfer_fee_minor >= 0),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE TABLE ledger_accounts (
  id TEXT PRIMARY KEY,
  asset_id TEXT NOT NULL REFERENCES assets(id) ON DELETE RESTRICT,
  owner_type TEXT NOT NULL CHECK (owner_type IN ('user','system')),
  owner_id TEXT,
  code TEXT NOT NULL,
  name TEXT NOT NULL,
  account_class TEXT NOT NULL CHECK (account_class IN ('asset','liability','revenue','expense','equity','clearing')),
  normal_side TEXT NOT NULL CHECK (normal_side IN ('debit','credit')),
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','frozen','closed')),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  UNIQUE(asset_id, code)
) STRICT;

CREATE INDEX ledger_accounts_owner_idx ON ledger_accounts(owner_type, owner_id, asset_id);

CREATE TABLE accounts (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  asset_id TEXT NOT NULL REFERENCES assets(id) ON DELETE RESTRICT,
  ledger_account_id TEXT NOT NULL UNIQUE REFERENCES ledger_accounts(id) ON DELETE RESTRICT,
  account_number TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','restricted','frozen','closed')),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  UNIQUE(user_id, asset_id)
) STRICT;

CREATE INDEX accounts_user_status_idx ON accounts(user_id, status);

CREATE TABLE journal_entries (
  id TEXT PRIMARY KEY,
  public_reference TEXT NOT NULL UNIQUE,
  entry_type TEXT NOT NULL CHECK (entry_type IN ('deposit','withdrawal','transfer','fee','adjustment','invoice_payment','reversal')),
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','posted','reversed')),
  description TEXT NOT NULL,
  idempotency_key TEXT UNIQUE,
  initiated_by TEXT REFERENCES users(id) ON DELETE SET NULL,
  approved_by TEXT REFERENCES users(id) ON DELETE SET NULL,
  related_entry_id TEXT REFERENCES journal_entries(id) ON DELETE RESTRICT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  posted_at TEXT
) STRICT;

CREATE INDEX journals_type_posted_idx ON journal_entries(entry_type, posted_at);

CREATE TABLE postings (
  id TEXT PRIMARY KEY,
  journal_entry_id TEXT NOT NULL REFERENCES journal_entries(id) ON DELETE RESTRICT,
  ledger_account_id TEXT NOT NULL REFERENCES ledger_accounts(id) ON DELETE RESTRICT,
  side TEXT NOT NULL CHECK (side IN ('debit','credit')),
  amount_minor INTEGER NOT NULL CHECK (amount_minor > 0),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE INDEX postings_journal_idx ON postings(journal_entry_id);
CREATE INDEX postings_account_idx ON postings(ledger_account_id, created_at);

CREATE TABLE holds (
  id TEXT PRIMARY KEY,
  account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  source_type TEXT NOT NULL CHECK (source_type IN ('withdrawal','transfer','admin')),
  source_id TEXT NOT NULL,
  amount_minor INTEGER NOT NULL CHECK (amount_minor > 0),
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','captured','released','expired')),
  expires_at TEXT,
  released_at TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  UNIQUE(source_type, source_id)
) STRICT;

CREATE INDEX holds_account_status_idx ON holds(account_id, status, expires_at);

CREATE TABLE transactions (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  journal_entry_id TEXT REFERENCES journal_entries(id) ON DELETE RESTRICT,
  public_reference TEXT NOT NULL UNIQUE,
  transaction_type TEXT NOT NULL CHECK (transaction_type IN ('deposit','withdrawal','transfer_in','transfer_out','fee','invoice_payment','adjustment')),
  direction TEXT NOT NULL CHECK (direction IN ('credit','debit')),
  amount_minor INTEGER NOT NULL CHECK (amount_minor >= 0),
  fee_minor INTEGER NOT NULL DEFAULT 0 CHECK (fee_minor >= 0),
  status TEXT NOT NULL CHECK (status IN ('initiated','pending','processing','completed','failed','reversed','cancelled')),
  counterparty_label TEXT,
  description TEXT NOT NULL,
  metadata TEXT NOT NULL DEFAULT '{}' CHECK (json_valid(metadata)),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  completed_at TEXT
) STRICT;

CREATE INDEX transactions_user_created_idx ON transactions(user_id, created_at);
CREATE INDEX transactions_account_created_idx ON transactions(account_id, created_at);
CREATE INDEX transactions_status_created_idx ON transactions(status, created_at);

CREATE TABLE deposit_requests (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  transaction_id TEXT NOT NULL UNIQUE REFERENCES transactions(id) ON DELETE RESTRICT,
  deposit_type TEXT NOT NULL CHECK (deposit_type IN ('fiat','crypto')),
  amount_minor INTEGER NOT NULL CHECK (amount_minor > 0),
  sender_reference TEXT,
  external_reference TEXT,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','reviewing','completed','rejected','cancelled')),
  reviewed_by TEXT REFERENCES users(id) ON DELETE SET NULL,
  reviewed_at TEXT,
  review_reason TEXT,
  journal_entry_id TEXT REFERENCES journal_entries(id) ON DELETE RESTRICT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE INDEX deposits_status_created_idx ON deposit_requests(status, created_at);
CREATE INDEX deposits_user_created_idx ON deposit_requests(user_id, created_at);

CREATE TABLE withdrawal_requests (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  transaction_id TEXT NOT NULL UNIQUE REFERENCES transactions(id) ON DELETE RESTRICT,
  hold_id TEXT NOT NULL UNIQUE REFERENCES holds(id) ON DELETE RESTRICT,
  withdrawal_type TEXT NOT NULL CHECK (withdrawal_type IN ('fiat','crypto')),
  amount_minor INTEGER NOT NULL CHECK (amount_minor > 0),
  fee_minor INTEGER NOT NULL DEFAULT 0 CHECK (fee_minor >= 0),
  destination_ciphertext TEXT NOT NULL,
  destination_summary TEXT NOT NULL,
  external_reference TEXT,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','processing','completed','rejected','cancelled')),
  reviewed_by TEXT REFERENCES users(id) ON DELETE SET NULL,
  reviewed_at TEXT,
  review_reason TEXT,
  journal_entry_id TEXT REFERENCES journal_entries(id) ON DELETE RESTRICT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE INDEX withdrawals_status_created_idx ON withdrawal_requests(status, created_at);
CREATE INDEX withdrawals_user_created_idx ON withdrawal_requests(user_id, created_at);

CREATE TABLE transfers (
  id TEXT PRIMARY KEY,
  public_reference TEXT NOT NULL UNIQUE,
  sender_user_id TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  sender_account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  recipient_user_id TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  recipient_account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  asset_id TEXT NOT NULL REFERENCES assets(id) ON DELETE RESTRICT,
  amount_minor INTEGER NOT NULL CHECK (amount_minor > 0),
  fee_minor INTEGER NOT NULL DEFAULT 0 CHECK (fee_minor >= 0),
  note TEXT,
  status TEXT NOT NULL DEFAULT 'completed' CHECK (status IN ('completed','failed','reversed')),
  journal_entry_id TEXT NOT NULL UNIQUE REFERENCES journal_entries(id) ON DELETE RESTRICT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE INDEX transfers_sender_created_idx ON transfers(sender_user_id, created_at);
CREATE INDEX transfers_recipient_created_idx ON transfers(recipient_user_id, created_at);

CREATE TABLE invoices (
  id TEXT PRIMARY KEY,
  public_code TEXT NOT NULL UNIQUE,
  issuer_user_id TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  asset_id TEXT NOT NULL REFERENCES assets(id) ON DELETE RESTRICT,
  amount_minor INTEGER NOT NULL CHECK (amount_minor > 0),
  title TEXT NOT NULL,
  description TEXT,
  status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','paid','expired','cancelled')),
  due_at TEXT,
  paid_at TEXT,
  paid_by_user_id TEXT REFERENCES users(id) ON DELETE SET NULL,
  transfer_id TEXT REFERENCES transfers(id) ON DELETE RESTRICT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE INDEX invoices_issuer_status_idx ON invoices(issuer_user_id, status, created_at);

CREATE TABLE beneficiaries (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  asset_id TEXT NOT NULL REFERENCES assets(id) ON DELETE RESTRICT,
  beneficiary_type TEXT NOT NULL CHECK (beneficiary_type IN ('internal','bank','crypto')),
  label TEXT NOT NULL,
  internal_account_id TEXT REFERENCES accounts(id) ON DELETE CASCADE,
  details_ciphertext TEXT,
  details_summary TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE INDEX beneficiaries_user_asset_idx ON beneficiaries(user_id, asset_id);

CREATE TABLE crypto_addresses (
  id TEXT PRIMARY KEY,
  account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  network TEXT NOT NULL,
  address TEXT NOT NULL,
  memo_tag TEXT,
  active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1)),
  assigned_by TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  UNIQUE(account_id, network, address)
) STRICT;

CREATE TABLE notifications (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  notification_type TEXT NOT NULL,
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  read_at TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE INDEX notifications_user_read_idx ON notifications(user_id, read_at, created_at);

CREATE TABLE idempotency_records (
  scope TEXT NOT NULL,
  idempotency_key TEXT NOT NULL,
  request_hash TEXT NOT NULL,
  result_reference TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
  expires_at TEXT NOT NULL,
  PRIMARY KEY(scope, idempotency_key)
) STRICT;

CREATE TABLE audit_events (
  id TEXT PRIMARY KEY,
  actor_user_id TEXT REFERENCES users(id) ON DELETE SET NULL,
  effective_user_id TEXT REFERENCES users(id) ON DELETE SET NULL,
  action TEXT NOT NULL,
  resource_type TEXT NOT NULL,
  resource_id TEXT,
  request_id TEXT NOT NULL,
  reason TEXT,
  metadata TEXT NOT NULL DEFAULT '{}' CHECK (json_valid(metadata)),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now'))
) STRICT;

CREATE INDEX audit_actor_created_idx ON audit_events(actor_user_id, created_at);
CREATE INDEX audit_resource_created_idx ON audit_events(resource_type, resource_id, created_at);

CREATE TRIGGER audit_events_reject_update
BEFORE UPDATE ON audit_events BEGIN
  SELECT RAISE(ABORT, 'audit_events is append-only');
END;
CREATE TRIGGER audit_events_reject_delete
BEFORE DELETE ON audit_events BEGIN
  SELECT RAISE(ABORT, 'audit_events is append-only');
END;
CREATE TRIGGER postings_reject_update
BEFORE UPDATE ON postings BEGIN
  SELECT RAISE(ABORT, 'postings are immutable');
END;
CREATE TRIGGER postings_reject_delete
BEFORE DELETE ON postings BEGIN
  SELECT RAISE(ABORT, 'postings are immutable');
END;
CREATE TRIGGER journals_reject_delete
BEFORE DELETE ON journal_entries BEGIN
  SELECT RAISE(ABORT, 'journal entries are immutable');
END;
CREATE TRIGGER journals_reject_posted_update
BEFORE UPDATE ON journal_entries WHEN OLD.status <> 'draft' BEGIN
  SELECT RAISE(ABORT, 'posted journal entries are immutable');
END;
