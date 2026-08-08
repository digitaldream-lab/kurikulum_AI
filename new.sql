-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.30 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for energi_senja
CREATE DATABASE IF NOT EXISTS `energi_senja` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `energi_senja`;

-- Dumping structure for table energi_senja.admins
CREATE TABLE IF NOT EXISTS `admins` (
  `id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `pob` varchar(100) DEFAULT NULL,
  `dob` date NOT NULL,
  `gender` enum('Laki-laki','Perempuan') NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) NOT NULL,
  `bank_name` varchar(50) NOT NULL,
  `bank_acc` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table energi_senja.admins: ~0 rows (approximately)
INSERT INTO `admins` (`id`, `name`, `pob`, `dob`, `gender`, `address`, `phone`, `bank_name`, `bank_acc`) VALUES
	('ESA0726002', 'budi pranata', 'surabaya', '2002-09-16', 'Laki-laki', 'iubdksajd', '098172', 'bca', '8173');

-- Dumping structure for table energi_senja.customers
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `pob` varchar(100) DEFAULT NULL,
  `dob` date NOT NULL,
  `gender` enum('Laki-laki','Perempuan') NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) NOT NULL,
  `history` text,
  `complaint` text,
  `blood_pressure` varchar(50) DEFAULT NULL,
  `kin_name` varchar(100) NOT NULL,
  `kin_phone` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table energi_senja.customers: ~1 rows (approximately)
INSERT INTO `customers` (`id`, `name`, `pob`, `dob`, `gender`, `address`, `phone`, `history`, `complaint`, `blood_pressure`, `kin_name`, `kin_phone`) VALUES
	(1, 'amelia', 'surabaya', '2002-09-05', 'Perempuan', 'surabaya', '456789', '', '', '125/80', 'budi', '0876');

-- Dumping structure for table energi_senja.services
CREATE TABLE IF NOT EXISTS `services` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `services_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table energi_senja.services: ~14 rows (approximately)
INSERT INTO `services` (`id`, `category_id`, `name`, `description`, `price`) VALUES
	(1, 1, 'resistensi ringan', 'tes', 150000.00),
	(3, 1, 'Senam Lansia', 'tes', 100000.00),
	(4, 1, 'Latihan Resistensi Ringan', 'tes', 100000.00),
	(5, 1, 'Latihan Keseimbangan', 'tes', 100000.00),
	(6, 1, 'Terapi Gerak', 'tes', 100000.00),
	(7, 1, 'Pemeriksaan Kebugaran Berkala', 'tes', 100000.00),
	(8, 3, 'Sesi Curhat Individu', 'tes', 100000.00),
	(9, 3, 'Konseling Emosional', 'tes', 100000.00),
	(10, 3, 'Kelompok Diskusi', 'tes', 100000.00),
	(11, 3, 'Terapi Aktivitas', 'tes', 100000.00),
	(12, 3, 'Pendampingan Keluarga', 'tes', 100000.00),
	(13, 4, 'Home Visit', 'tes', 100000.00),
	(14, 4, 'Kegiatan Outing', 'tes', 100000.00),
	(15, 4, 'Edukasi Kesehatan Bulanan', 'tes', 100000.00),
	(16, 4, 'Gathering Keluarga - Lansia', 'tes', 100000.00);

-- Dumping structure for table energi_senja.service_categories
CREATE TABLE IF NOT EXISTS `service_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table energi_senja.service_categories: ~2 rows (approximately)
INSERT INTO `service_categories` (`id`, `name`) VALUES
	(1, 'Kebugaran Lansia'),
	(3, 'Ruang Curhat'),
	(4, 'Program Komunitas & Sisial');

-- Dumping structure for table energi_senja.settings
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table energi_senja.settings: ~0 rows (approximately)

-- Dumping structure for table energi_senja.trainers
CREATE TABLE IF NOT EXISTS `trainers` (
  `id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `pob` varchar(100) DEFAULT NULL,
  `dob` date NOT NULL,
  `gender` enum('Laki-laki','Perempuan') NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) NOT NULL,
  `bank_name` varchar(50) NOT NULL,
  `bank_acc` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table energi_senja.trainers: ~0 rows (approximately)
INSERT INTO `trainers` (`id`, `name`, `pob`, `dob`, `gender`, `address`, `phone`, `bank_name`, `bank_acc`) VALUES
	('EST0726001', 'wandy pradana', 'surabaya', '2002-09-16', 'Laki-laki', 'uabduadb', '0987654', 'bca345', '3456');

-- Dumping structure for table energi_senja.trainer_services
CREATE TABLE IF NOT EXISTS `trainer_services` (
  `trainer_id` varchar(20) NOT NULL,
  `service_id` int NOT NULL,
  KEY `trainer_id` (`trainer_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `trainer_services_ibfk_1` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainer_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table energi_senja.trainer_services: ~0 rows (approximately)
INSERT INTO `trainer_services` (`trainer_id`, `service_id`) VALUES
	('EST0726001', 1);

-- Dumping structure for table energi_senja.transactions
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` varchar(20) NOT NULL,
  `date` date NOT NULL,
  `admin_id` varchar(20) NOT NULL,
  `trainer_id` varchar(20) NOT NULL,
  `customer_id` int NOT NULL,
  `service_id` int NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `trainer_id` (`trainer_id`),
  KEY `customer_id` (`customer_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`),
  CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `transactions_ibfk_4` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table energi_senja.transactions: ~2 rows (approximately)
INSERT INTO `transactions` (`id`, `date`, `admin_id`, `trainer_id`, `customer_id`, `service_id`, `total_amount`, `payment_method`) VALUES
	('TRX0726001', '2026-07-24', 'ESA0726002', 'EST0726001', 1, 1, 100000.00, 'Tunai'),
	('TRX0726002', '2026-07-30', 'ESA0726001', 'EST0726001', 1, 1, 150000.00, 'Tunai'),
	('TRX0726003', '2026-07-30', 'ESA0726001', 'EST0726001', 1, 1, 150000.00, 'Tunai');

-- Dumping structure for table energi_senja.transaction_schedules
CREATE TABLE IF NOT EXISTS `transaction_schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(20) NOT NULL,
  `schedule_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('pending','ongoing','completed','cancelled') DEFAULT 'pending',
  `bp_before` varchar(50) DEFAULT NULL,
  `bp_after` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `transaction_schedules_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table energi_senja.transaction_schedules: ~2 rows (approximately)
INSERT INTO `transaction_schedules` (`id`, `transaction_id`, `schedule_date`, `start_time`, `end_time`, `status`, `bp_before`, `bp_after`) VALUES
	(1, 'TRX0726001', '2026-07-24', '16:00:00', '17:00:00', 'completed', NULL, NULL),
	(2, 'TRX0726002', '2026-07-31', '09:58:00', '11:58:00', 'completed', NULL, NULL),
	(3, 'TRX0726003', '2026-07-30', '14:00:00', '15:00:00', 'completed', '120/70', '125/80');

-- Dumping structure for table energi_senja.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` varchar(20) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','admin') NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table energi_senja.users: ~2 rows (approximately)
INSERT INTO `users` (`id`, `username`, `password`, `role`, `name`) VALUES
	('ESA0726001', 'budi', '202cb962ac59075b964b07152d234b70', 'admin', 'budi pranata'),
	('ESA0726002', 'budi1', '202cb962ac59075b964b07152d234b70', 'admin', 'budi pranata'),
	('SUPERADMIN', 'superadmin', '0192023a7bbd73250516f069df18b500', 'superadmin', 'Super Administrator');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
