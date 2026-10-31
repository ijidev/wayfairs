-- Distinguish external bank transfers from ordinary withdrawals while reusing
-- the same reservation and immutable settlement machinery.
ALTER TABLE withdrawal_requests ADD COLUMN request_kind TEXT NOT NULL DEFAULT 'withdrawal'
  CHECK (request_kind IN ('withdrawal','bank_transfer'));
ALTER TABLE withdrawal_requests ADD COLUMN user_remark TEXT;
ALTER TABLE deposit_requests ADD COLUMN user_remark TEXT;

-- Crypto assets need their provider-specific CoinGecko identifier. Fiat assets
-- are resolved directly by ISO code through Frankfurter.
ALTER TABLE assets ADD COLUMN usd_provider_reference TEXT;
UPDATE assets SET usd_provider_reference = 'bitcoin'
  WHERE code = 'BTC' AND asset_type = 'crypto' AND usd_provider_reference IS NULL;
UPDATE assets SET usd_provider_reference = 'ethereum'
  WHERE code = 'ETH' AND asset_type = 'crypto' AND usd_provider_reference IS NULL;

-- Only successful observed rates are persisted. Missing/failed provider state
-- remains unavailable rather than being replaced with a fabricated value.
CREATE TABLE usd_rates (
  asset_id TEXT PRIMARY KEY REFERENCES assets(id) ON DELETE CASCADE,
  rate_decimal TEXT NOT NULL,
  provider TEXT NOT NULL CHECK (provider IN ('base','frankfurter','coingecko')),
  provider_reference TEXT,
  source_updated_at TEXT,
  fetched_at TEXT NOT NULL
) STRICT;
