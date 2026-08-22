-- Phase 4: scoped messaging queue and reseller API keys
CREATE TABLE IF NOT EXISTS `reseller_broadcasts` (
 `broadcast_id` CHAR(32) NOT NULL,
 `actor_reseller_id` VARCHAR(64) NOT NULL,
 `title` VARCHAR(191) NOT NULL DEFAULT '',
 `message` TEXT NOT NULL,
 `kind` VARCHAR(20) NOT NULL DEFAULT 'broadcast',
 `status` VARCHAR(20) NOT NULL DEFAULT 'queued',
 `total_count` INT UNSIGNED NOT NULL DEFAULT 0,
 `sent_count` INT UNSIGNED NOT NULL DEFAULT 0,
 `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
 `created_at` BIGINT UNSIGNED NOT NULL,
 `completed_at` BIGINT UNSIGNED NULL,
 PRIMARY KEY (`broadcast_id`), KEY `idx_rb_actor_time` (`actor_reseller_id`,`created_at`), KEY `idx_rb_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reseller_message_queue` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 `broadcast_id` CHAR(32) NOT NULL,
 `recipient_user_id` VARCHAR(64) NOT NULL,
 `bot_owner_id` VARCHAR(64) NULL,
 `status` VARCHAR(20) NOT NULL DEFAULT 'queued',
 `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
 `next_attempt_at` BIGINT UNSIGNED NOT NULL DEFAULT 0,
 `claim_token` CHAR(32) NULL,
 `claimed_at` BIGINT UNSIGNED NULL,
 `last_error` VARCHAR(1000) NULL,
 `sent_at` BIGINT UNSIGNED NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uq_rmq_broadcast_user` (`broadcast_id`,`recipient_user_id`), KEY `idx_rmq_dispatch` (`status`,`next_attempt_at`,`id`), KEY `idx_rmq_claim` (`claim_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reseller_api_keys` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 `reseller_id` VARCHAR(64) NOT NULL,
 `name` VARCHAR(100) NOT NULL,
 `key_prefix` VARCHAR(20) NOT NULL,
 `key_hash` CHAR(64) NOT NULL,
 `permissions` TEXT NOT NULL,
 `expires_at` BIGINT UNSIGNED NOT NULL,
 `last_used_at` BIGINT UNSIGNED NULL,
 `revoked_at` BIGINT UNSIGNED NULL,
 `created_at` BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uq_rak_hash` (`key_hash`), KEY `idx_rak_owner` (`reseller_id`,`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
