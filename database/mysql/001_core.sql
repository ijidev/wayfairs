SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE site_settings (
  setting_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  setting_value TEXT NOT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  updated_by CHAR(36) NULL,
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CHECK (is_public IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE password_reset_tokens (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  expires_at DATETIME(6) NOT NULL,
  used_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT reset_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE idempotency_records (
  scope VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  idempotency_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  result_reference VARCHAR(100) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expires_at DATETIME(6) NOT NULL,
  PRIMARY KEY(scope, idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DELIMITER $$
CREATE TRIGGER audit_events_reject_update BEFORE UPDATE ON audit_events FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events is append-only'; END$$
CREATE TRIGGER audit_events_reject_delete BEFORE DELETE ON audit_events FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events is append-only'; END$$
CREATE TRIGGER postings_reject_update BEFORE UPDATE ON postings FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'postings are immutable'; END$$
CREATE TRIGGER postings_reject_delete BEFORE DELETE ON postings FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'postings are immutable'; END$$
CREATE TRIGGER journals_reject_delete BEFORE DELETE ON journal_entries FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal entries are immutable'; END$$
CREATE TRIGGER journals_reject_posted_update BEFORE UPDATE ON journal_entries FOR EACH ROW BEGIN IF OLD.status <> 'draft' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'posted journal entries are immutable'; END IF; END$$
DELIMITER ;
