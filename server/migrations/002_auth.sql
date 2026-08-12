-- Failed-login tracking for /leads and /admin. Not a user table — there are only
-- two shared passwords (desk, admin), both in config.php, never in the database.
-- This table exists purely so failed attempts can be rate-limited per IP hash.

CREATE TABLE login_attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  ip_hash      CHAR(64)  NOT NULL,
  attempted_at DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
