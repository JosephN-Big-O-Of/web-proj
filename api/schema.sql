-- Schema for Event Finder (MySQL)

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `age` INT,
  `role` VARCHAR(50) DEFAULT 'user',
  `joined_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `location` VARCHAR(255),
  `lat` DOUBLE,
  `lng` DOUBLE,
  `date` DATE,
  `time` TIME,
  `age_restriction` INT,
  `price` DECIMAL(10,2),
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
