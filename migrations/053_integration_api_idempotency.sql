-- Idempotent mutation claims for the legacy administrator integration API.
-- The credential hash isolates keys across secret rotations without persisting
-- the bearer secret itself.
CREATE TABLE IF NOT EXISTS `integration_api_idempotency` (
  `credential_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `idempotency_key` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `endpoint` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `request_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status_code` SMALLINT UNSIGNED NULL,
  `response_body` TEXT NULL,
  `created_at` BIGINT UNSIGNED NOT NULL,
  `completed_at` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`credential_hash`, `idempotency_key`),
  KEY `idx_integration_api_idem_completed` (`completed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
