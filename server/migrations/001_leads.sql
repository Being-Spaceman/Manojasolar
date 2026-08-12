-- Manoja Agencies — lead system schema.
-- Run once, by hand, in hPanel's phpMyAdmin (or `mysql < 001_leads.sql` if shell
-- access is ever available). No migration runner — this is the whole schema.

CREATE TABLE leads (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  lead_type     ENUM('business','individual') NOT NULL,
  firm_name     VARCHAR(160)  NULL,
  contact_name  VARCHAR(120)  NOT NULL,
  mobile        VARCHAR(15)   NOT NULL,
  city          VARCHAR(80)   NULL,
  gstin         VARCHAR(20)   NULL,
  products      VARCHAR(200)  NULL,
  bill_amount   INT           NULL,
  usage_units   INT           NULL,
  roof_type     VARCHAR(40)   NULL,
  message       TEXT          NULL,
  locale        CHAR(2)       NOT NULL DEFAULT 'mr',
  status        ENUM('new','exported','contacted') NOT NULL DEFAULT 'new',
  consent_at    DATETIME      NOT NULL,
  source_page   VARCHAR(120)  NULL,
  ip_hash       CHAR(64)      NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at),
  INDEX idx_status  (status),
  INDEX idx_type    (lead_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE export_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  exported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  format      VARCHAR(8) NOT NULL,
  row_count   INT NOT NULL,
  range_from  DATE NULL,
  range_to    DATE NULL,
  ip_hash     CHAR(64) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
