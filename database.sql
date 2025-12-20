-- Database Schema for Peirano SaaS
-- Consolidated Version (Includes v2 and v3 updates)

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table: `branches` (Sucursales)
--

DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `capacity_per_slot` int(11) DEFAULT 2,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `branches` (`id`, `name`, `address`, `capacity_per_slot`, `active`) VALUES
(1, 'Planta Principal', 'Calle Ficticia 123', 3, 1),
(2, 'Depósito Secundario', 'Av. Industrial 456', 2, 1);

-- --------------------------------------------------------

--
-- Table: `users` (Usuarios)
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cuit` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `company_name` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `role` enum('client','provider','operator','admin') NOT NULL DEFAULT 'client',
  `email_verified` tinyint(1) DEFAULT 1,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `branch_id` int(11) DEFAULT NULL,
  `default_duration` int(11) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `api_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cuit` (`cuit`),
  UNIQUE KEY `api_token` (`api_token`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`cuit`, `password_hash`, `company_name`, `role`, `status`, `email_verified`) VALUES
('20111111112', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Peirano Admin', 'admin', 'approved', 1);

-- --------------------------------------------------------

--
-- Table: `vehicle_types`
--

DROP TABLE IF EXISTS `vehicle_types`;
CREATE TABLE `vehicle_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `block_minutes` int(11) NOT NULL DEFAULT 60,
  `real_minutes` int(11) NOT NULL DEFAULT 55,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `vehicle_types` (`name`, `block_minutes`, `real_minutes`) VALUES
('Utilitario / Camioneta', 30, 25),
('Chasis', 60, 55),
('Balancín', 60, 55),
('Semi / Acoplado', 60, 55);

-- --------------------------------------------------------

--
-- Table: `appointments` (Turnos/Citas)
--

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `driver_name` varchar(100) DEFAULT NULL,
  `driver_dni` varchar(20) DEFAULT NULL,
  `helper_name` varchar(100) DEFAULT NULL,
  `helper_dni` varchar(20) DEFAULT NULL,
  `needs_forklift` tinyint(1) DEFAULT 0,
  `needs_helper` tinyint(1) DEFAULT 0,
  `observations` text DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','no_show') DEFAULT 'scheduled',
  `attendance_status` enum('pending','present','absent') DEFAULT 'pending',
  `reminder_sent` tinyint(1) DEFAULT 0,
  `external_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `branch_id` (`branch_id`),
  KEY `start_time` (`start_time`),
  KEY `idx_user_attendance` (`user_id`, `attendance_status`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table: `system_settings` (Configuraciones)
--

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
