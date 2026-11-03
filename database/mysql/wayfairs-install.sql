-- Wayfairs MySQL/MariaDB (InnoDB) schema, generated from database/mysql/NNN_*.sql.
-- Do not edit by hand: run `php scripts/build-mysql-install.php`.
--
-- Import into an EMPTY database that you have already created with utf8mb4:
--   CREATE DATABASE wayfairs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   mysql -u USER -p wayfairs < wayfairs-install.sql
--
-- Then open /install (or sign in if already installed) to create the administrator,
-- site settings, and currency catalog. This file contains no customer, balance, or
-- administrator data.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    applied_at DATETIME(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==== migration 001_core (source: 001_core.sql) ====
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE site_settings (
  setting_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  setting_value TEXT NOT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  updated_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CHECK (is_public IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  email VARCHAR(254) NOT NULL,
  email_normalized VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  phone VARCHAR(40) NULL,
  status ENUM('active','restricted','blocked','closed','pending') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME(6) NULL,
  failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME(6) NULL,
  last_login_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY users_role_status_idx (role, status),
  KEY users_created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE site_settings ADD CONSTRAINT site_settings_updated_by_fk FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE sessions (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  parent_session_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  original_admin_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  impersonation_reason TEXT NULL,
  impersonation_expires_at DATETIME(6) NULL,
  ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  user_agent VARCHAR(500) NULL,
  last_seen_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expires_at DATETIME(6) NOT NULL,
  revoked_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT sessions_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT sessions_parent_fk FOREIGN KEY (parent_session_id) REFERENCES sessions(id) ON DELETE SET NULL,
  CONSTRAINT sessions_admin_fk FOREIGN KEY (original_admin_id) REFERENCES users(id) ON DELETE SET NULL,
  KEY sessions_user_active_idx (user_id, revoked_at, expires_at),
  KEY sessions_original_admin_idx (original_admin_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  expires_at DATETIME(6) NOT NULL,
  used_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT reset_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kyc_profiles (
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  status ENUM('not_started','pending','in_review','verified','rejected','needs_resubmission','expired') NOT NULL DEFAULT 'not_started',
  date_of_birth DATE NULL,
  country_code CHAR(2) CHARACTER SET ascii NULL,
  address_line1 VARCHAR(200) NULL,
  address_line2 VARCHAR(200) NULL,
  city VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  postal_code VARCHAR(30) NULL,
  id_type VARCHAR(50) NULL,
  id_last4 VARCHAR(8) NULL,
  submission_reference VARCHAR(120) NULL,
  submitted_at DATETIME(6) NULL,
  reviewed_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  reviewed_at DATETIME(6) NULL,
  review_reason TEXT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT kyc_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT kyc_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  KEY kyc_status_submitted_idx (status, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE capability_restrictions (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  capability ENUM('login','fiat_deposit','fiat_withdrawal','crypto_deposit','crypto_withdrawal','internal_transfer','invoice','profile_security') NOT NULL,
  reason_code VARCHAR(100) NOT NULL,
  reason TEXT NOT NULL,
  starts_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expires_at DATETIME(6) NULL,
  released_at DATETIME(6) NULL,
  released_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  release_reason TEXT NULL,
  created_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT restrictions_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT restrictions_released_by_fk FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT restrictions_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
  KEY restrictions_user_capability_idx (user_id, capability, released_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assets (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  asset_type ENUM('fiat','crypto') NOT NULL,
  symbol VARCHAR(16) NOT NULL,
  scale TINYINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  deposit_enabled TINYINT(1) NOT NULL DEFAULT 1,
  withdrawal_enabled TINYINT(1) NOT NULL DEFAULT 1,
  min_deposit_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  min_withdrawal_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  withdrawal_fee_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  transfer_fee_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CHECK (scale <= 18),
  CHECK (enabled IN (0,1)), CHECK (deposit_enabled IN (0,1)), CHECK (withdrawal_enabled IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ledger_accounts (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  asset_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  owner_type ENUM('user','system') NOT NULL,
  owner_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  code VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name VARCHAR(160) NOT NULL,
  account_class ENUM('asset','liability','revenue','expense','equity','clearing') NOT NULL,
  normal_side ENUM('debit','credit') NOT NULL,
  status ENUM('active','frozen','closed') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT ledger_asset_fk FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
  UNIQUE KEY ledger_asset_code_unique (asset_id, code),
  KEY ledger_accounts_owner_idx (owner_type, owner_id, asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE accounts (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  asset_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  ledger_account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  account_number VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  status ENUM('active','restricted','frozen','closed') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT accounts_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT accounts_asset_fk FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
  CONSTRAINT accounts_ledger_fk FOREIGN KEY (ledger_account_id) REFERENCES ledger_accounts(id) ON DELETE RESTRICT,
  UNIQUE KEY accounts_user_asset_unique (user_id, asset_id),
  KEY accounts_user_status_idx (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE journal_entries (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  entry_type ENUM('deposit','withdrawal','transfer','fee','adjustment','invoice_payment','reversal') NOT NULL,
  status ENUM('draft','posted','reversed') NOT NULL DEFAULT 'draft',
  description VARCHAR(500) NOT NULL,
  idempotency_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
  initiated_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  approved_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  related_entry_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  posted_at DATETIME(6) NULL,
  CONSTRAINT journals_initiator_fk FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT journals_approver_fk FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT journals_related_fk FOREIGN KEY (related_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
  KEY journals_type_posted_idx (entry_type, posted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE postings (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  journal_entry_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  ledger_account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  side ENUM('debit','credit') NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT postings_journal_fk FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
  CONSTRAINT postings_account_fk FOREIGN KEY (ledger_account_id) REFERENCES ledger_accounts(id) ON DELETE RESTRICT,
  CHECK (amount_minor > 0),
  KEY postings_journal_idx (journal_entry_id),
  KEY postings_account_idx (ledger_account_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE holds (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  source_type ENUM('withdrawal','transfer','admin') NOT NULL,
  source_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  status ENUM('active','captured','released','expired') NOT NULL DEFAULT 'active',
  expires_at DATETIME(6) NULL,
  released_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT holds_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
  UNIQUE KEY holds_source_unique (source_type, source_id),
  KEY holds_account_status_idx (account_id, status, expires_at),
  CHECK (amount_minor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  journal_entry_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  transaction_type ENUM('deposit','withdrawal','transfer_in','transfer_out','fee','invoice_payment','adjustment') NOT NULL,
  direction ENUM('credit','debit') NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  fee_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('initiated','pending','processing','completed','failed','reversed','cancelled') NOT NULL,
  counterparty_label VARCHAR(200) NULL,
  description VARCHAR(500) NOT NULL,
  metadata JSON NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  completed_at DATETIME(6) NULL,
  CONSTRAINT transactions_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT transactions_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
  CONSTRAINT transactions_journal_fk FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
  KEY transactions_user_created_idx (user_id, created_at),
  KEY transactions_account_created_idx (account_id, created_at),
  KEY transactions_status_created_idx (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE deposit_requests (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  transaction_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  deposit_type ENUM('fiat','crypto') NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  sender_reference VARCHAR(200) NULL,
  external_reference VARCHAR(200) NULL,
  status ENUM('pending','reviewing','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  reviewed_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  reviewed_at DATETIME(6) NULL,
  review_reason TEXT NULL,
  journal_entry_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT deposits_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT deposits_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
  CONSTRAINT deposits_transaction_fk FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE RESTRICT,
  CONSTRAINT deposits_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT deposits_journal_fk FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
  CHECK (amount_minor > 0),
  KEY deposits_status_created_idx (status, created_at),
  KEY deposits_user_created_idx (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE withdrawal_requests (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  transaction_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  hold_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  withdrawal_type ENUM('fiat','crypto') NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  fee_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  destination_ciphertext LONGTEXT NOT NULL,
  destination_summary VARCHAR(200) NOT NULL,
  external_reference VARCHAR(200) NULL,
  status ENUM('pending','approved','processing','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  reviewed_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  reviewed_at DATETIME(6) NULL,
  review_reason TEXT NULL,
  journal_entry_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT withdrawals_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT withdrawals_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
  CONSTRAINT withdrawals_transaction_fk FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE RESTRICT,
  CONSTRAINT withdrawals_hold_fk FOREIGN KEY (hold_id) REFERENCES holds(id) ON DELETE RESTRICT,
  CONSTRAINT withdrawals_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT withdrawals_journal_fk FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
  CHECK (amount_minor > 0),
  KEY withdrawals_status_created_idx (status, created_at),
  KEY withdrawals_user_created_idx (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfers (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  sender_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  sender_account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  recipient_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  recipient_account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  asset_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  fee_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  note VARCHAR(500) NULL,
  status ENUM('completed','failed','reversed') NOT NULL DEFAULT 'completed',
  journal_entry_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT transfers_sender_user_fk FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT transfers_sender_account_fk FOREIGN KEY (sender_account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
  CONSTRAINT transfers_recipient_user_fk FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT transfers_recipient_account_fk FOREIGN KEY (recipient_account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
  CONSTRAINT transfers_asset_fk FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
  CONSTRAINT transfers_journal_fk FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
  CHECK (amount_minor > 0),
  KEY transfers_sender_created_idx (sender_user_id, created_at),
  KEY transfers_recipient_created_idx (recipient_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  public_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  issuer_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  asset_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  status ENUM('open','paid','expired','cancelled') NOT NULL DEFAULT 'open',
  due_at DATETIME(6) NULL,
  paid_at DATETIME(6) NULL,
  paid_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  transfer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT invoices_issuer_fk FOREIGN KEY (issuer_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT invoices_asset_fk FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
  CONSTRAINT invoices_payer_fk FOREIGN KEY (paid_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT invoices_transfer_fk FOREIGN KEY (transfer_id) REFERENCES transfers(id) ON DELETE RESTRICT,
  CHECK (amount_minor > 0),
  KEY invoices_issuer_status_idx (issuer_user_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE beneficiaries (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  asset_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  beneficiary_type ENUM('internal','bank','crypto') NOT NULL,
  label VARCHAR(160) NOT NULL,
  internal_account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  details_ciphertext LONGTEXT NULL,
  details_summary VARCHAR(200) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT beneficiaries_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT beneficiaries_asset_fk FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
  CONSTRAINT beneficiaries_account_fk FOREIGN KEY (internal_account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  KEY beneficiaries_user_asset_idx (user_id, asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE crypto_addresses (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  network VARCHAR(60) NOT NULL,
  address VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  memo_tag VARCHAR(120) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  assigned_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT addresses_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  CONSTRAINT addresses_admin_fk FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT,
  UNIQUE KEY addresses_unique (account_id, network, address),
  CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  notification_type VARCHAR(80) NOT NULL,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  read_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT notifications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY notifications_user_read_idx (user_id, read_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE idempotency_records (
  scope VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  idempotency_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  result_reference VARCHAR(100) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expires_at DATETIME(6) NOT NULL,
  PRIMARY KEY(scope, idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_events (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  actor_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  effective_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  action VARCHAR(160) NOT NULL,
  resource_type VARCHAR(100) NOT NULL,
  resource_id VARCHAR(160) NULL,
  request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  reason TEXT NULL,
  metadata JSON NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT audit_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT audit_effective_fk FOREIGN KEY (effective_user_id) REFERENCES users(id) ON DELETE SET NULL,
  KEY audit_actor_created_idx (actor_user_id, created_at),
  KEY audit_resource_created_idx (resource_type, resource_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER audit_events_reject_update BEFORE UPDATE ON audit_events FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events is append-only'; END$$
CREATE TRIGGER audit_events_reject_delete BEFORE DELETE ON audit_events FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events is append-only'; END$$
CREATE TRIGGER postings_reject_update BEFORE UPDATE ON postings FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'postings are immutable'; END$$
CREATE TRIGGER postings_reject_delete BEFORE DELETE ON postings FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'postings are immutable'; END$$
CREATE TRIGGER journals_reject_delete BEFORE DELETE ON journal_entries FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal entries are immutable'; END$$
CREATE TRIGGER journals_reject_posted_update BEFORE UPDATE ON journal_entries FOR EACH ROW BEGIN IF OLD.status <> 'draft' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'posted journal entries are immutable'; END IF; END$$
DELIMITER ;

-- ==== migration 002_asset_deposit_details (source: 002_asset_deposit_details.sql) ====
-- Add deposit destination details per asset so admins can publish wallet/bank
-- instructions that reflect on the customer deposit page.
ALTER TABLE assets
  ADD COLUMN deposit_bank_name VARCHAR(120) NULL AFTER transfer_fee_minor,
  ADD COLUMN deposit_account_name VARCHAR(120) NULL AFTER deposit_bank_name,
  ADD COLUMN deposit_account_number VARCHAR(60) NULL AFTER deposit_account_name,
  ADD COLUMN deposit_routing VARCHAR(60) NULL AFTER deposit_account_number,
  ADD COLUMN deposit_swift VARCHAR(40) NULL AFTER deposit_routing,
  ADD COLUMN deposit_wallet_address VARCHAR(255) NULL AFTER deposit_swift,
  ADD COLUMN deposit_network VARCHAR(60) NULL AFTER deposit_wallet_address,
  ADD COLUMN deposit_memo VARCHAR(120) NULL AFTER deposit_network,
  ADD COLUMN deposit_instructions TEXT NULL AFTER deposit_memo;

-- ==== migration 003_bank_transfers_and_usd_rates (source: 003_bank_transfers_and_usd_rates.sql) ====
-- Distinguish external bank transfers from ordinary withdrawals while reusing
-- the same reservation and immutable settlement machinery.
ALTER TABLE withdrawal_requests
  ADD COLUMN request_kind ENUM('withdrawal','bank_transfer') NOT NULL DEFAULT 'withdrawal' AFTER withdrawal_type,
  ADD COLUMN user_remark TEXT NULL AFTER destination_summary;

ALTER TABLE deposit_requests
  ADD COLUMN user_remark TEXT NULL AFTER sender_reference;

-- Crypto assets need their provider-specific CoinGecko identifier. Fiat assets
-- are resolved directly by ISO code through Frankfurter.
ALTER TABLE assets
  ADD COLUMN usd_provider_reference VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER transfer_fee_minor;

UPDATE assets SET usd_provider_reference = 'bitcoin'
  WHERE code = 'BTC' AND asset_type = 'crypto' AND usd_provider_reference IS NULL;
UPDATE assets SET usd_provider_reference = 'ethereum'
  WHERE code = 'ETH' AND asset_type = 'crypto' AND usd_provider_reference IS NULL;

-- Only successful observed rates are persisted. Missing/failed provider state
-- remains unavailable rather than being replaced with a fabricated value.
CREATE TABLE usd_rates (
  asset_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  rate_decimal VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  provider ENUM('base','frankfurter','coingecko') NOT NULL,
  provider_reference VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL,
  source_updated_at DATETIME(6) NULL,
  fetched_at DATETIME(6) NOT NULL,
  CONSTRAINT usd_rates_asset_fk FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==== migration 004_bill_payments_and_open_limits (source: 004_bill_payments_and_open_limits.sql) ====
-- Bill payments reuse the bank-transfer settlement machinery; this column keeps
-- them listed separately from ordinary transfers.
ALTER TABLE withdrawal_requests
  ADD COLUMN payment_purpose ENUM('transfer','bill') NOT NULL DEFAULT 'transfer' AFTER request_kind;

-- Earlier installs seeded a minimum deposit or withdrawal on the standard
-- currencies. Clear only those untouched seeded values; administrators can still
-- set a minimum deliberately in the asset settings.
UPDATE assets SET min_deposit_minor = 0
  WHERE code IN ('USD','BTC','ETH') AND min_deposit_minor IN (1000, 100000, 1000000000000000);
UPDATE assets SET min_withdrawal_minor = 0
  WHERE code IN ('USD','BTC','ETH') AND min_withdrawal_minor IN (1000, 100000, 1000000000000000);

-- ==== migration 005_flight_requests (source: 005_flight_requests.sql) ====
-- Flight booking is a request workflow: customers describe the trip, and
-- operations confirms availability and price before anything is booked.
CREATE TABLE flight_requests (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  idempotency_key VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  origin VARCHAR(120) NOT NULL,
  destination VARCHAR(120) NOT NULL,
  departure_date DATE NOT NULL,
  return_date DATE NULL,
  travellers TINYINT UNSIGNED NOT NULL,
  cabin ENUM('economy','premium_economy','business','first') NOT NULL,
  notes TEXT NULL,
  status ENUM('requested','contacted','booked','declined','cancelled') NOT NULL DEFAULT 'requested',
  admin_note TEXT NULL,
  reviewed_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  reviewed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT flight_requests_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT flight_requests_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT,
  KEY flight_requests_user_idx (user_id, created_at),
  KEY flight_requests_status_idx (status, created_at),
  CHECK (travellers BETWEEN 1 AND 9)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==== migration 006_beneficiaries (source: 006_beneficiaries.sql) ====
-- Saved beneficiaries: administrator-defined (scope 'global') and customer-saved
-- (scope 'user'). The account identifier lives only inside the encrypted details;
-- a keyed hash supports exact lookup and last four digits support display.
CREATE TABLE saved_beneficiaries (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  owner_key VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  scope ENUM('global','user') NOT NULL,
  owner_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  route ENUM('iban','local') NOT NULL,
  nickname VARCHAR(80) NOT NULL,
  country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  beneficiary_name VARCHAR(160) NOT NULL,
  bank_name VARCHAR(160) NOT NULL,
  identifier_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  identifier_last4 VARCHAR(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  details_ciphertext LONGTEXT NOT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT saved_beneficiaries_owner_fk FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT saved_beneficiaries_creator_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
  KEY saved_beneficiaries_owner_idx (owner_key, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==== migration 007_otp_and_email (source: 007_otp_and_email.sql) ====
-- One-time codes used to confirm sensitive actions (transfers, payments).
CREATE TABLE otp_codes (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  purpose VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME(6) NOT NULL,
  consumed_at DATETIME(6) NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  ip VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NULL,
  CONSTRAINT otp_codes_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  KEY otp_codes_user_idx (user_id, purpose, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editable email templates. Placeholders such as {first_name} are filled at send time.
CREATE TABLE email_templates (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  template_key VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  subject VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  updated_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT email_templates_updated_by_fk FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO email_templates (id, template_key, subject, body) VALUES
(UUID(), 'otp', 'Your Wayfairs verification code', 'Hello {first_name},\n\nYour verification code is {code}. It expires in {minutes} minutes. If you did not request it, ignore this message.'),
(UUID(), 'registration', 'Welcome to Wayfairs', 'Hello {first_name},\n\nYour account has been created. You can sign in at {site_url}.'),
(UUID(), 'deposit', 'Deposit request received', 'Hello {first_name},\n\nWe received your deposit request for {amount} {currency}. It will be reviewed and credited once confirmed.'),
(UUID(), 'withdrawal', 'Withdrawal request received', 'Hello {first_name},\n\nWe received your withdrawal request for {amount} {currency}. The amount is held until it is processed.'),
(UUID(), 'bank_transfer', 'Bank transfer submitted', 'Hello {first_name},\n\nYour bank transfer of {amount} {currency} to {beneficiary} has been submitted and is being processed.'),
(UUID(), 'bill_payment', 'Bill payment submitted', 'Hello {first_name},\n\nYour bill payment of {amount} {currency} to {biller} has been submitted and is being processed.'),
(UUID(), 'topup', 'Top-up received', 'Hello {first_name},\n\nA top-up of {amount} {currency} has been credited to your account.'),
(UUID(), 'debit', 'Debit recorded', 'Hello {first_name},\n\nA debit of {amount} {currency} has been recorded on your account.'),
(UUID(), 'flight_request', 'Flight request received', 'Hello {first_name},\n\nWe received your flight request from {origin} to {destination}. We will contact you to confirm availability and price.');

-- ==== migration 008_professional_emails (source: 008_professional_emails.sql) ====
-- Professional transactional email copy. Existing rows are overwritten once; later
-- administrator edits are preserved because this migration runs only once.
UPDATE email_templates SET subject = 'Welcome to Wayfairs', body = 'Dear {first_name},

Welcome to Wayfairs. Your account has been created and is ready to use.

Sign in to complete identity verification before you deposit, withdraw, or transfer funds. Verification is required for all money movement.

If you did not create this account, contact support immediately.

Regards,
Wayfairs', updated_at = CURRENT_TIMESTAMP(6) WHERE template_key = 'registration';

UPDATE email_templates SET subject = 'Deposit request received - {amount} {currency}', body = 'Dear {first_name},

We have received your deposit request.

Amount: {amount} {currency}
Reference: {reference}
Status: Pending verification

The funds will be credited to your account once the deposit is confirmed. You will receive another notification when it is processed.

Regards,
Wayfairs', updated_at = CURRENT_TIMESTAMP(6) WHERE template_key = 'deposit';

UPDATE email_templates SET subject = 'Withdrawal request received - {amount} {currency}', body = 'Dear {first_name},

We have received your withdrawal request.

Amount: {amount} {currency}
Reference: {reference}
Status: Pending

The amount has been held from your available balance and will be released to your nominated account once processed. You will receive another notification when it is completed.

Regards,
Wayfairs', updated_at = CURRENT_TIMESTAMP(6) WHERE template_key = 'withdrawal';

UPDATE email_templates SET subject = 'Bank transfer submitted - {amount} {currency}', body = 'Dear {first_name},

Your bank transfer has been submitted.

Amount: {amount} {currency}
Beneficiary: {beneficiary}
Reference: {reference}
Status: Pending settlement

The amount has been held from your available balance. You will be notified when the transfer is completed or returned.

Regards,
Wayfairs', updated_at = CURRENT_TIMESTAMP(6) WHERE template_key = 'bank_transfer';

UPDATE email_templates SET subject = 'Bill payment submitted - {amount} {currency}', body = 'Dear {first_name},

Your bill payment has been submitted.

Amount: {amount} {currency}
Biller: {biller}
Reference: {reference}
Status: Pending settlement

The amount has been held from your available balance. You will be notified when the payment is completed or returned.

Regards,
Wayfairs', updated_at = CURRENT_TIMESTAMP(6) WHERE template_key = 'bill_payment';

UPDATE email_templates SET subject = 'Account credited - {amount} {currency}', body = 'Dear {first_name},

Your account has been credited.

Amount: {amount} {currency}
Reference: {reference}
Date: {date}

Regards,
Wayfairs', updated_at = CURRENT_TIMESTAMP(6) WHERE template_key = 'topup';

UPDATE email_templates SET subject = 'Account debited - {amount} {currency}', body = 'Dear {first_name},

Your account has been debited.

Amount: {amount} {currency}
Reference: {reference}
Date: {date}

If you did not authorize this transaction, contact support immediately.

Regards,
Wayfairs', updated_at = CURRENT_TIMESTAMP(6) WHERE template_key = 'debit';

UPDATE email_templates SET subject = 'Your Wayfairs verification code', body = 'Dear {first_name},

Your verification code is {code}. It expires in {minutes} minutes.

If you did not request this code, ignore this message and contact support if you are concerned about the security of your account.

Regards,
Wayfairs', updated_at = CURRENT_TIMESTAMP(6) WHERE template_key = 'otp';

UPDATE email_templates SET subject = 'Flight request received', body = 'Dear {first_name},

We have received your flight request.

Route: {origin} to {destination}
Status: Requested

Our team will contact you to confirm availability and pricing before any booking is made. No charge has been applied at this stage.

Regards,
Wayfairs', updated_at = CURRENT_TIMESTAMP(6) WHERE template_key = 'flight_request';

INSERT IGNORE INTO email_templates (id, template_key, subject, body, updated_at) VALUES
(UUID(), 'account_status', 'Important update to your account', 'Dear {first_name},

There has been an update to your account.

Status: {status}

If you have questions about this change, contact support.

Regards,
Wayfairs', CURRENT_TIMESTAMP(6));

-- ==== migration 010_swaps (source: 010_swaps.sql) ====
-- Swaps are instant currency conversions between two of the customer's own accounts.
ALTER TABLE transactions ADD COLUMN swap_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL;

CREATE TABLE swaps (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  from_account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  to_account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  from_asset_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  to_asset_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  from_amount_minor BIGINT UNSIGNED NOT NULL,
  to_amount_minor BIGINT UNSIGNED NOT NULL,
  fee_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  from_rate VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  to_rate VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  idempotency_key VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  status ENUM('completed','failed') NOT NULL DEFAULT 'completed',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT swaps_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT swaps_from_account_fk FOREIGN KEY (from_account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
  CONSTRAINT swaps_to_account_fk FOREIGN KEY (to_account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
  CONSTRAINT swaps_from_asset_fk FOREIGN KEY (from_asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
  CONSTRAINT swaps_to_asset_fk FOREIGN KEY (to_asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
  KEY swaps_user_idx (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO site_settings (setting_key, setting_value, is_public) VALUES
  ('swap.enabled', '0', 0),
  ('swap.fee_percent', '0.5', 0);

-- ==== migration ledger (matches the application checksums) ====
INSERT INTO schema_migrations (version, checksum, applied_at) VALUES
    ('001_core', 'b005c9d14426caf95b8834c76c94d86a01ae14210a33766935160f30e67df25e', UTC_TIMESTAMP(6)),
    ('002_asset_deposit_details', 'c6ef9903920a6d1c37bdcea664cc5bbe05415612982808ad74053d02365dd84d', UTC_TIMESTAMP(6)),
    ('003_bank_transfers_and_usd_rates', '11b64eadc10a767c764dc4447384e5914dea97fc09890f91eda62597f6b3f5a9', UTC_TIMESTAMP(6)),
    ('004_bill_payments_and_open_limits', '4fda0fa746fe5c15e257b651bc68c284c44a6a98f16ca9a208235f8b05a5b72c', UTC_TIMESTAMP(6)),
    ('005_flight_requests', '58d69c73fbcb972320ea7da2d5cb318669c8065b56baa714aa39058a537be8cc', UTC_TIMESTAMP(6)),
    ('006_beneficiaries', 'b60911b44ed0a4cc2ff656625bb6a63c4f4f3c0034c1f2d12dd1b47ace68ef1b', UTC_TIMESTAMP(6)),
    ('007_otp_and_email', '4973a91448907c47f7382cf3c04bae30099ce9a13fc042f0078b6d86b9c18665', UTC_TIMESTAMP(6)),
    ('008_professional_emails', '1a16aa3b5e419310d13341af41d5225973e588d72b04358c21b8830160a2c592', UTC_TIMESTAMP(6)),
    ('010_swaps', 'c682a1af76a11b8aa1fc5723a5bbb9cc61a4bf60b984e7a3e78b358503818a4c', UTC_TIMESTAMP(6));
