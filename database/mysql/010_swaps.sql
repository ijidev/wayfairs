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
