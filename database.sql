-- Database Schema for Peirano SaaS
-- Run this in your MySQL/MariaDB database (e.g., in Ferozo/phpMyAdmin)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table: `branches` (Sucursales)
--

CREATE TABLE IF NOT EXISTS `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `capacity_per_slot` int(11) DEFAULT 2,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Insert Data: `branches`
--

INSERT INTO `branches` (`id`, `name`, `address`, `capacity_per_slot`, `active`) VALUES
(1, 'Planta Principal', 'Calle Ficticia 123', 3, 1),
(2, 'Depósito Secundario', 'Av. Industrial 456', 2, 1)
ON DUPLICATE KEY UPDATE name=name;

-- --------------------------------------------------------

--
-- Table: `users` (Usuarios)
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cuit` varchar(20) NOT NULL COMMENT 'Used as Username',
  `password_hash` varchar(255) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `role` enum('client','provider','operator','admin') NOT NULL DEFAULT 'client',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `branch_id` int(11) DEFAULT NULL COMMENT 'For operators',
  `default_duration` int(11) DEFAULT 60,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cuit` (`cuit`),
  KEY `branch_id` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Insert Data: Admin Default (Pass: Admin123)
--

INSERT INTO `users` (`cuit`, `password_hash`, `company_name`, `role`, `status`) VALUES
('20111111112', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Peirano Admin', 'admin', 'approved')
ON DUPLICATE KEY UPDATE company_name=company_name;

-- --------------------------------------------------------

--
-- Table: `appointments` (Turnos/Citas)
--

CREATE TABLE IF NOT EXISTS `appointments` (
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
  `external_id` varchar(255) DEFAULT NULL COMMENT 'ID for Google/Outlook Sync',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `branch_id` (`branch_id`),
  KEY `start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table: `system_settings` (Configuraciones)
--

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
