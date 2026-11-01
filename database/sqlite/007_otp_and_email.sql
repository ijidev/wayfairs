-- One-time codes used to confirm sensitive actions (transfers, payments).
CREATE TABLE otp_codes (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  purpose TEXT NOT NULL,
  code_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  consumed_at TEXT,
  attempts INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  ip TEXT
) STRICT;
CREATE INDEX otp_codes_user_idx ON otp_codes(user_id, purpose, created_at);

-- Editable email templates. Placeholders such as {first_name} are filled at send time.
CREATE TABLE email_templates (
  id TEXT PRIMARY KEY,
  template_key TEXT NOT NULL UNIQUE,
  subject TEXT NOT NULL,
  body TEXT NOT NULL,
  updated_by TEXT REFERENCES users(id) ON DELETE SET NULL,
  updated_at TEXT NOT NULL
) STRICT;

INSERT INTO email_templates (id, template_key, subject, body, updated_at) VALUES
('otp-default', 'otp', 'Your Wayfairs verification code', 'Hello {first_name},\n\nYour verification code is {code}. It expires in {minutes} minutes. If you did not request it, ignore this message.', datetime('now')),
('registration-default', 'registration', 'Welcome to Wayfairs', 'Hello {first_name},\n\nYour account has been created. You can sign in at {site_url}.', datetime('now')),
('deposit-default', 'deposit', 'Deposit request received', 'Hello {first_name},\n\nWe received your deposit request for {amount} {currency}. It will be reviewed and credited once confirmed.', datetime('now')),
('withdrawal-default', 'withdrawal', 'Withdrawal request received', 'Hello {first_name},\n\nWe received your withdrawal request for {amount} {currency}. The amount is held until it is processed.', datetime('now')),
('bank_transfer-default', 'bank_transfer', 'Bank transfer submitted', 'Hello {first_name},\n\nYour bank transfer of {amount} {currency} to {beneficiary} has been submitted and is being processed.', datetime('now')),
('bill_payment-default', 'bill_payment', 'Bill payment submitted', 'Hello {first_name},\n\nYour bill payment of {amount} {currency} to {biller} has been submitted and is being processed.', datetime('now')),
('topup-default', 'topup', 'Top-up received', 'Hello {first_name},\n\nA top-up of {amount} {currency} has been credited to your account.', datetime('now')),
('debit-default', 'debit', 'Debit recorded', 'Hello {first_name},\n\nA debit of {amount} {currency} has been recorded on your account.', datetime('now')),
('flight_request-default', 'flight_request', 'Flight request received', 'Hello {first_name},\n\nWe received your flight request from {origin} to {destination}. We will contact you to confirm availability and price.', datetime('now'));
