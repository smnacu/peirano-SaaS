-- Update Script v2
-- Applies changes for Auth fixes and new features

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- 1. Fix Auth: Add email and email_verified cols
ALTER TABLE `users` 
ADD COLUMN `email` varchar(255) DEFAULT NULL AFTER `cuit`,
ADD COLUMN `email_verified` tinyint(1) DEFAULT 1 AFTER `role`;

-- 2. New Feature: Vehicle Types (Dynamic)
CREATE TABLE IF NOT EXISTS `vehicle_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `block_minutes` int(11) NOT NULL DEFAULT 60 COMMENT 'Minutes reserved in calendar',
  `real_minutes` int(11) NOT NULL DEFAULT 55 COMMENT 'Actual duration',
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert Defaults
INSERT INTO `vehicle_types` (`name`, `block_minutes`, `real_minutes`) VALUES
('Utilitario / Camioneta', 30, 25),
('Chasis', 60, 55),
('Balancín', 60, 55),
('Semi / Acoplado', 60, 55)
ON DUPLICATE KEY UPDATE name=name;

-- 3. Update Appointments to link to vehicle_types (Optional but good for future reference)
-- For now we keep storing string/varchar in appointments to avoid breaking history if types change,
-- but we could add vehicle_type_id later.

-- 3. Make default_duration nullable in users
ALTER TABLE `users` MODIFY `default_duration` int(11) DEFAULT NULL;

-- 4. Attendance & Reminders
ALTER TABLE `appointments` 
ADD COLUMN `attendance_status` ENUM('pending', 'present', 'absent') DEFAULT 'pending',
ADD COLUMN `reminder_sent` TINYINT(1) DEFAULT 0;

COMMIT;
