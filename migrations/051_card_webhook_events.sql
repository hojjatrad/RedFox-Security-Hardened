-- Replay protection and audit trail for the automatic bank-message integration.
CREATE TABLE IF NOT EXISTS `card_webhook_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_digest` CHAR(64) NOT NULL,
  `bank` VARCHAR(32) NOT NULL,
  `amount` DECIMAL(24,8) NULL,
  `matched_order_id` VARCHAR(191) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'received',
  `error_message` VARCHAR(500) NOT NULL DEFAULT '',
  `received_at` BIGINT UNSIGNED NOT NULL,
  `completed_at` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_card_webhook_digest` (`event_digest`),
  KEY `idx_card_webhook_status_time` (`status`,`received_at`),
  KEY `idx_card_webhook_order` (`matched_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
