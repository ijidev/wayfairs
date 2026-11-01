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
