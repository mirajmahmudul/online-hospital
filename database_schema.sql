-- Database Schema for Online Hospital Booking System

-- Create appointments table
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `patient_name` varchar(255) NOT NULL,
  `doctor_name` varchar(255) NOT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `status` enum('pending','confirmed','completed','cancelled','paid') DEFAULT 'pending',
  `payment_status` enum('unpaid','paid','refunded') DEFAULT 'unpaid',
  `amount` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `status` (`status`),
  KEY `appointment_date` (`appointment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payments table
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('card','bank_transfer','wallet') DEFAULT 'card',
  `transaction_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `patient_id` (`patient_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraints
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Insert sample appointment data (optional)
INSERT INTO `appointments` (`patient_id`, `doctor_id`, `patient_name`, `doctor_name`, `specialty`, `appointment_date`, `appointment_time`, `status`, `payment_status`, `amount`, `notes`) VALUES
(1, 1, 'John Doe', 'Dr. Sarah Chen', 'Cardiology', '2024-10-24', '10:00:00', 'pending', 'unpaid', 150.00, 'Initial consultation'),
(2, 2, 'Jane Smith', 'Dr. James Wilson', 'Neurology', '2024-10-25', '14:30:00', 'confirmed', 'unpaid', 200.00, 'Follow-up visit');
