-- Swaps are instant currency conversions between two of the customer's own accounts.
ALTER TABLE transactions ADD COLUMN swap_id TEXT;

CREATE TABLE swaps (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  from_account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  to_account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  from_asset_id TEXT NOT NULL REFERENCES assets(id) ON DELETE RESTRICT,
  to_asset_id TEXT NOT NULL REFERENCES assets(id) ON DELETE RESTRICT,
  from_amount_minor INTEGER NOT NULL CHECK (from_amount_minor > 0),
  to_amount_minor INTEGER NOT NULL CHECK (to_amount_minor > 0),
  fee_minor INTEGER NOT NULL DEFAULT 0 CHECK (fee_minor >= 0),
  from_rate TEXT NOT NULL,
  to_rate TEXT NOT NULL,
  idempotency_key TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL DEFAULT 'completed' CHECK (status IN ('completed','failed')),
  created_at TEXT NOT NULL
) STRICT;
CREATE INDEX swaps_user_idx ON swaps(user_id, created_at);

INSERT OR IGNORE INTO site_settings (setting_key, setting_value, is_public, updated_by) VALUES
  ('swap.enabled', '0', 0, NULL),
  ('swap.fee_percent', '0.5', 0, NULL);
