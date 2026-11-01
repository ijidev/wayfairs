-- Flight booking is a request workflow: customers describe the trip, and
-- operations confirms availability and price before anything is booked.
CREATE TABLE flight_requests (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  public_reference TEXT NOT NULL UNIQUE,
  idempotency_key TEXT NOT NULL UNIQUE,
  origin TEXT NOT NULL,
  destination TEXT NOT NULL,
  departure_date TEXT NOT NULL,
  return_date TEXT,
  travellers INTEGER NOT NULL CHECK (travellers BETWEEN 1 AND 9),
  cabin TEXT NOT NULL CHECK (cabin IN ('economy','premium_economy','business','first')),
  notes TEXT,
  status TEXT NOT NULL DEFAULT 'requested' CHECK (status IN ('requested','contacted','booked','declined','cancelled')),
  admin_note TEXT,
  reviewed_by TEXT REFERENCES users(id) ON DELETE RESTRICT,
  reviewed_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
) STRICT;
CREATE INDEX flight_requests_user_idx ON flight_requests(user_id, created_at);
CREATE INDEX flight_requests_status_idx ON flight_requests(status, created_at);
