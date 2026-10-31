-- Add deposit destination details per asset so admins can publish wallet/bank
-- instructions that reflect on the customer deposit page.
ALTER TABLE assets
  ADD COLUMN deposit_bank_name VARCHAR(120) NULL AFTER transfer_fee_minor,
  ADD COLUMN deposit_account_name VARCHAR(120) NULL AFTER deposit_bank_name,
  ADD COLUMN deposit_account_number VARCHAR(60) NULL AFTER deposit_account_name,
  ADD COLUMN deposit_routing VARCHAR(60) NULL AFTER deposit_account_number,
  ADD COLUMN deposit_swift VARCHAR(40) NULL AFTER deposit_routing,
  ADD COLUMN deposit_wallet_address VARCHAR(255) NULL AFTER deposit_swift,
  ADD COLUMN deposit_network VARCHAR(60) NULL AFTER deposit_wallet_address,
  ADD COLUMN deposit_memo VARCHAR(120) NULL AFTER deposit_network,
  ADD COLUMN deposit_instructions TEXT NULL AFTER deposit_memo;
