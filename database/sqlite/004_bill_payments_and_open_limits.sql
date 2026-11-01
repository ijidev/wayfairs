-- Bill payments reuse the bank-transfer settlement machinery; this column keeps
-- them listed separately from ordinary transfers.
ALTER TABLE withdrawal_requests ADD COLUMN payment_purpose TEXT NOT NULL DEFAULT 'transfer'
  CHECK (payment_purpose IN ('transfer','bill'));

-- Earlier installs seeded a minimum deposit or withdrawal on the standard
-- currencies. Clear only those untouched seeded values; administrators can still
-- set a minimum deliberately in the asset settings.
UPDATE assets SET min_deposit_minor = 0
  WHERE code IN ('USD','BTC','ETH') AND min_deposit_minor IN (1000, 100000, 1000000000000000);
UPDATE assets SET min_withdrawal_minor = 0
  WHERE code IN ('USD','BTC','ETH') AND min_withdrawal_minor IN (1000, 100000, 1000000000000000);
