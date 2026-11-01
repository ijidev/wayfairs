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
