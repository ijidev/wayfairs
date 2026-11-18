-- Customer deletion tombstones the user row so immutable ledger and audit rows
-- keep a valid foreign-key owner. Personal details are scrubbed separately.
ALTER TABLE users ADD COLUMN deleted_at DATETIME(6) NULL AFTER updated_at;
CREATE INDEX users_deleted_at_idx ON users(deleted_at);

-- Configurable transaction OTP lifetime; the service falls back to 30 minutes.
INSERT INTO site_settings (setting_key, setting_value, is_public, updated_by)
SELECT 'feature.otp_ttl_minutes', '30', 0, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'feature.otp_ttl_minutes');
