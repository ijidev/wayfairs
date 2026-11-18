-- Proof-of-payment slips are stored outside the public document root. The
-- deposit row keeps only the random storage name and a safe display label.
ALTER TABLE deposit_requests ADD COLUMN receipt_file TEXT NULL;
ALTER TABLE deposit_requests ADD COLUMN receipt_name TEXT NULL;
ALTER TABLE deposit_requests ADD COLUMN receipt_mime TEXT NULL;
ALTER TABLE deposit_requests ADD COLUMN receipt_size INTEGER NULL;
ALTER TABLE deposit_requests ADD COLUMN receipt_uploaded_at TEXT NULL;
