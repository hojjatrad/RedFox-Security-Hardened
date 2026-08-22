-- Representative deposits money to admin; admin verifies and credits representative wallet.
CREATE TABLE IF NOT EXISTS reseller_deposit_requests(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 request_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 reseller_id VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
 amount BIGINT UNSIGNED NOT NULL,
 payment_reference VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
 note VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
 status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
 created_at BIGINT UNSIGNED NOT NULL,
 reviewed_at BIGINT UNSIGNED NULL,
 reviewed_by VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
 admin_note VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
 UNIQUE KEY uq_rdr_request(request_id),
 KEY idx_rdr_status_time(status,created_at),
 KEY idx_rdr_owner_time(reseller_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
