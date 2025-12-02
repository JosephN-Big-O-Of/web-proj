-- Schema for Event Finder (MySQL) - Enhanced with Roles, Genres & Favorites

-- ============================================
-- USERS TABLE (Enhanced)
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `firebase_uid` VARCHAR(128) UNIQUE,
  `age` INT,
  `role` ENUM('owner', 'admin', 'user') DEFAULT 'user',
  `is_active` TINYINT(1) DEFAULT 1,
  `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_role` (`role`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EVENTS TABLE (Enhanced)
-- ============================================
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
  `image_url` VARCHAR(500),
  `status` ENUM('draft', 'published', 'archived') DEFAULT 'published',
  `created_by` INT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_date` (`date`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- GENRES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `genres` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `icon` VARCHAR(50),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EVENT GENRE MAPPING (Many-to-Many)
-- ============================================
CREATE TABLE IF NOT EXISTS `event_genres` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `genre_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_event_genre` (`event_id`, `genre_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`genre_id`) REFERENCES `genres`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- USER FAVORITES
-- ============================================
CREATE TABLE IF NOT EXISTS `user_favorites` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `event_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user_favorite` (`user_id`, `event_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_event` (`event_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN PERMISSIONS (Optional granular control)
-- ============================================
CREATE TABLE IF NOT EXISTS `admin_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `permission` VARCHAR(100) NOT NULL,
  `granted_by` INT,
  `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user_permission` (`user_id`, `permission`),
  INDEX `idx_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN ACTION LOG (Audit Trail)
-- ============================================
CREATE TABLE IF NOT EXISTS `admin_actions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(50),
  `target_id` INT,
  `details` TEXT,
  `ip_address` VARCHAR(45),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_admin` (`admin_id`),
  INDEX `idx_created` (`created_at`),
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT DEFAULT GENRES
-- ============================================
INSERT IGNORE INTO `genres` (`name`, `slug`, `description`, `icon`) VALUES
('Music', 'music', 'Concerts, festivals, and live performances', '🎵'),
('Sports', 'sports', 'Sporting events and competitions', '⚽'),
('Food & Drink', 'food-drink', 'Food festivals, tastings, and culinary events', '🍔'),
('Arts & Culture', 'arts-culture', 'Art exhibitions, theater, and cultural events', '🎨'),
('Business', 'business', 'Conferences, networking, and professional events', '💼'),
('Technology', 'technology', 'Tech meetups, hackathons, and workshops', '💻'),
('Family', 'family', 'Family-friendly activities and events', '👨‍👩‍👧‍👦'),
('Nightlife', 'nightlife', 'Clubs, bars, and evening entertainment', '🌙'),
('Education', 'education', 'Workshops, classes, and learning opportunities', '📚'),
('Outdoor', 'outdoor', 'Outdoor activities and adventures', '🏕️'),
('Comedy', 'comedy', 'Stand-up comedy and humor shows', '😂'),
('Film', 'film', 'Movie screenings and film festivals', '🎬');