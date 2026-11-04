-- Single-use password reset tokens. Only a hash of the token is stored.
CREATE TABLE password_resets (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TEXT NOT NULL,
  used_at TEXT,
  created_at TEXT NOT NULL,
  ip TEXT
) STRICT;
CREATE INDEX password_resets_user_idx ON password_resets(user_id, created_at);

-- Transaction PIN: stored as a password hash, with its own lockout counter.
ALTER TABLE users ADD COLUMN transaction_pin_hash TEXT;
ALTER TABLE users ADD COLUMN pin_failed_attempts INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN pin_locked_until TEXT;

INSERT OR IGNORE INTO email_templates (id, template_key, subject, body, updated_at) VALUES
('password-reset-default', 'password_reset', 'Reset your Wayfairs password', 'Dear {first_name},

We received a request to reset the password on your Wayfairs account.

Use this link to choose a new password. It can be used once and expires in {minutes} minutes:
{reset_url}

If you did not request this, you can ignore this message. Your password will not change.

Regards,
Wayfairs', datetime('now'));
