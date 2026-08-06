-- Migration: 001_create_notifications_table.sql
-- Description: Create notifications table for in-app notifications and alerts
-- Created At: 2026-08-06

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `type` VARCHAR(50) DEFAULT 'info',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
