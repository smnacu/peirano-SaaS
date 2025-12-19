-- UPDATE V3 - Phase 2: Security & Advanced Features

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- 1. Security: 2FA Support
ALTER TABLE `users` 
ADD COLUMN `two_factor_secret` VARCHAR(255) DEFAULT NULL AFTER `password_hash`,
ADD COLUMN `two_factor_enabled` TINYINT(1) DEFAULT 0 AFTER `two_factor_secret`;

-- 2. Connectivity: API Token
ALTER TABLE `users`
ADD COLUMN `api_token` VARCHAR(64) DEFAULT NULL AFTER `remember_token`,
ADD UNIQUE KEY `api_token` (`api_token`);

-- 3. Reports: Metrics support (Indexes for performance)
ALTER TABLE `appointments` ADD INDEX `idx_start_time` (`start_time`);
ALTER TABLE `appointments` ADD INDEX `idx_user_attendance` (`user_id`, `attendance_status`);

COMMIT;
