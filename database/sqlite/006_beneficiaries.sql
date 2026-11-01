-- Saved beneficiaries: administrator-defined (scope 'global') and customer-saved
-- (scope 'user'). The account identifier lives only inside the encrypted details;
-- a keyed hash supports exact lookup and last four digits support display.
CREATE TABLE saved_beneficiaries (
  id TEXT PRIMARY KEY,
  owner_key TEXT NOT NULL,
  scope TEXT NOT NULL CHECK (scope IN ('global','user')),
  owner_user_id TEXT REFERENCES users(id) ON DELETE RESTRICT,
  route TEXT NOT NULL CHECK (route IN ('iban','local')),
  nickname TEXT NOT NULL,
  country_code TEXT NOT NULL,
  beneficiary_name TEXT NOT NULL,
  bank_name TEXT NOT NULL,
  identifier_hash TEXT NOT NULL,
  identifier_last4 TEXT NOT NULL,
  details_ciphertext TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','archived')),
  created_by TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
) STRICT;
CREATE UNIQUE INDEX saved_beneficiaries_active_identifier_idx ON saved_beneficiaries(owner_key, identifier_hash) WHERE status = 'active';
CREATE INDEX saved_beneficiaries_owner_idx ON saved_beneficiaries(owner_key, status);
