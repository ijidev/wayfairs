-- Add deposit destination details per asset so admins can publish wallet/bank
-- instructions that reflect on the customer deposit page.
ALTER TABLE assets ADD COLUMN deposit_bank_name TEXT;
ALTER TABLE assets ADD COLUMN deposit_account_name TEXT;
ALTER TABLE assets ADD COLUMN deposit_account_number TEXT;
ALTER TABLE assets ADD COLUMN deposit_routing TEXT;
ALTER TABLE assets ADD COLUMN deposit_swift TEXT;
ALTER TABLE assets ADD COLUMN deposit_wallet_address TEXT;
ALTER TABLE assets ADD COLUMN deposit_network TEXT;
ALTER TABLE assets ADD COLUMN deposit_memo TEXT;
ALTER TABLE assets ADD COLUMN deposit_instructions TEXT;
