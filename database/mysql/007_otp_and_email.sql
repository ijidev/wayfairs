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
