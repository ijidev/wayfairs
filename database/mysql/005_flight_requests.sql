-- Flight booking is a request workflow: customers describe the trip, and
-- operations confirms availability and price before anything is booked.
CREATE TABLE flight_requests (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  idempotency_key VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  origin VARCHAR(120) NOT NULL,
  destination VARCHAR(120) NOT NULL,
  departure_date DATE NOT NULL,
  return_date DATE NULL,
  travellers TINYINT UNSIGNED NOT NULL,
  cabin ENUM('economy','premium_economy','business','first') NOT NULL,
  notes TEXT NULL,
  status ENUM('requested','contacted','booked','declined','cancelled') NOT NULL DEFAULT 'requested',
  admin_note TEXT NULL,
  reviewed_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  reviewed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT flight_requests_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT flight_requests_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT,
  KEY flight_requests_user_idx (user_id, created_at),
  KEY flight_requests_status_idx (status, created_at),
  CHECK (travellers BETWEEN 1 AND 9)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
