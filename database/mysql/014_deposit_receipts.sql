-- Proof-of-payment slips are stored outside the public document root. The
-- deposit row keeps only the random storage name and a safe display label.
ALTER TABLE deposit_requests
  ADD COLUMN receipt_file VARCHAR(255) NULL AFTER journal_entry_id,
  ADD COLUMN receipt_name VARCHAR(255) NULL AFTER receipt_file,
  ADD COLUMN receipt_mime VARCHAR(100) NULL AFTER receipt_name,
  ADD COLUMN receipt_size BIGINT UNSIGNED NULL AFTER receipt_mime,
  ADD COLUMN receipt_uploaded_at DATETIME(6) NULL AFTER receipt_size;
