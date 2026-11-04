-- Single-use password reset tokens. Only a hash of the token is stored.
CREATE TABLE password_resets (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  expires_at DATETIME(6) NOT NULL,
  used_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  ip VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NULL,
  CONSTRAINT password_resets_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  KEY password_resets_user_idx (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transaction PIN: stored as a password hash, with its own lockout counter.
ALTER TABLE users
  ADD COLUMN transaction_pin_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
  ADD COLUMN pin_failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN pin_locked_until DATETIME(6) NULL;

INSERT IGNORE INTO email_templates (id, template_key, subject, body) VALUES
(UUID(), 'password_reset', 'Reset your Wayfairs password', 'Dear {first_name},

We received a request to reset the password on your Wayfairs account.

Use this link to choose a new password. It can be used once and expires in {minutes} minutes:
{reset_url}

If you did not request this, you can ignore this message. Your password will not change.

Regards,
Wayfairs');
