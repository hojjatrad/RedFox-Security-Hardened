-- Security remediation: durable, exactly-once payment fulfillment.
-- Apply before accepting payment callbacks from the remediated release.

ALTER TABLE `Payment_report`
  ADD COLUMN `provider_name` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `Payment_Method`,
  ADD COLUMN `provider_invoice_id` VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `provider_name`,
  ADD COLUMN `provider_payment_id` VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `provider_invoice_id`,
  ADD COLUMN `provider_amount` DECIMAL(24,8) NULL AFTER `provider_payment_id`,
  ADD COLUMN `provider_currency` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `provider_amount`,
  ADD UNIQUE INDEX `uq_payment_provider_invoice` (`provider_name`,`provider_invoice_id`),
  ADD UNIQUE INDEX `uq_payment_provider_payment` (`provider_name`,`provider_payment_id`);

ALTER TABLE `payment_effects`
  ADD COLUMN `cashback_key` VARCHAR(100) NOT NULL DEFAULT '' AFTER `source`,
  ADD COLUMN `report_context` MEDIUMTEXT NULL AFTER `cashback_key`,
  ADD COLUMN `claimed_at` BIGINT UNSIGNED NULL AFTER `report_context`,
  ADD COLUMN `fulfillment_started_at` BIGINT UNSIGNED NULL AFTER `claimed_at`,
  ADD COLUMN `fulfillment_done_at` BIGINT UNSIGNED NULL AFTER `fulfillment_started_at`,
  ADD COLUMN `completed_at` BIGINT UNSIGNED NULL AFTER `fulfillment_done_at`;

CREATE TABLE IF NOT EXISTS `payment_cashback_ledger` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` VARCHAR(191) NOT NULL,
  `user_id` VARCHAR(200) NOT NULL,
  `amount` BIGINT UNSIGNED NOT NULL,
  `percent` DECIMAL(8,4) NOT NULL DEFAULT 0,
  `cashback_key` VARCHAR(100) NOT NULL DEFAULT '',
  `created_at` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_cashback_order` (`order_id`),
  KEY `idx_payment_cashback_user_time` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
