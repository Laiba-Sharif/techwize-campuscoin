-- ==========================================================
-- Campus Coin (NextGen BudgetBee) - Complete MySQL Database Schema
-- Version: 1.0 (Core PHP & MySQL PDO compliant)
-- Target RDBMS: MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
-- Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `campus_coin` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `campus_coin`;

-- Disable foreign key checks during schema creation
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- 1. USERS TABLE (Students & Administrators)
-- Justification: A unified users table with a 'role' column avoids
-- redundant authentication logic, password resets, and session code,
-- while allowing role-based access control (RBAC).
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    `academic_year` VARCHAR(50) DEFAULT '1st Year (Freshman)',
    `monthly_allowance` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `monthly_savings_goal` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `reset_token` VARCHAR(100) DEFAULT NULL,
    `reset_expires` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uq_users_email` (`email`),
    INDEX `idx_users_role_active` (`role`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. CATEGORIES TABLE (System Defaults + User-Created Categories)
-- SRS 1.6 Page 7: Default categories available to all students +
-- registered students can create, edit, delete personal categories.
-- If user_id IS NULL, category is a system default!
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `icon` VARCHAR(50) DEFAULT '₨',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`category_id`),
    KEY `fk_categories_user` (`user_id`),
    INDEX `idx_categories_type_default` (`type`, `is_default`),
    CONSTRAINT `fk_categories_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. TRANSACTIONS TABLE (Income & Expense Ledger)
-- SRS 1.6 Page 7 & 13: Core financial entries
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
    `transaction_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `ai_suggested_category` INT UNSIGNED DEFAULT NULL,
    `is_recurring` TINYINT(1) NOT NULL DEFAULT 0,
    `date` DATE NOT NULL,
    `time` TIME NOT NULL DEFAULT '12:00:00',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`transaction_id`),
    KEY `fk_trans_user` (`user_id`),
    KEY `fk_trans_category` (`category_id`),
    KEY `fk_trans_ai_cat` (`ai_suggested_category`),
    INDEX `idx_trans_user_date` (`user_id`, `date`),
    INDEX `idx_trans_user_type_date` (`user_id`, `type`, `date`),
    INDEX `idx_trans_user_cat` (`user_id`, `category_id`),
    CONSTRAINT `fk_trans_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_trans_category` FOREIGN KEY (`category_id`) 
        REFERENCES `categories` (`category_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_trans_ai_cat` FOREIGN KEY (`ai_suggested_category`) 
        REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. RECURRING TRANSACTIONS TABLE
-- SRS 1.6 Page 7: Supports recurring entries (allowance, subscriptions)
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `recurring_transactions`;
CREATE TABLE `recurring_transactions` (
    `recurring_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL,
    `frequency` VARCHAR(50) NOT NULL DEFAULT 'monthly',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_processed` DATE DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`recurring_id`),
    KEY `fk_recurring_user` (`user_id`),
    KEY `fk_recurring_category` (`category_id`),
    CONSTRAINT `fk_recurring_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_recurring_category` FOREIGN KEY (`category_id`) 
        REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. BUDGETS TABLE (Category Spending Caps per Month)
-- SRS 1.6 Page 8 & 14: Month budget per category
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `budgets`;
CREATE TABLE `budgets` (
    `budget_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `month` DATE NOT NULL,
    `limit_amount` DECIMAL(12, 2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`budget_id`),
    UNIQUE KEY `uq_user_category_month` (`user_id`, `category_id`, `month`),
    KEY `fk_budgets_user` (`user_id`),
    KEY `fk_budgets_category` (`category_id`),
    INDEX `idx_budgets_user_month` (`user_id`, `month`),
    CONSTRAINT `fk_budgets_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_budgets_category` FOREIGN KEY (`category_id`) 
        REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. SAVINGS GOALS TABLE
-- SRS 1.6 Page 6: Student Target Savings tracking
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `savings_goals`;
CREATE TABLE `savings_goals` (
    `goal_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `target_amount` DECIMAL(12, 2) NOT NULL,
    `saved_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `target_date` DATE DEFAULT NULL,
    `status` ENUM('in_progress', 'completed') NOT NULL DEFAULT 'in_progress',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`goal_id`),
    KEY `fk_goals_user` (`user_id`),
    INDEX `idx_goals_user_status` (`user_id`, `status`),
    CONSTRAINT `fk_goals_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. INSIGHTS TABLE (AI Monthly Narrative Summaries)
-- SRS 1.6 Page 8 & 14: Month narrative summaries and flagged patterns
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `insights`;
CREATE TABLE `insights` (
    `insight_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `month` DATE NOT NULL,
    `growth_flag_text` VARCHAR(255) DEFAULT NULL,
    `summary_text` TEXT NOT NULL,
    `tip_text` TEXT NOT NULL,
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
    `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`insight_id`),
    KEY `fk_insights_user` (`user_id`),
    INDEX `idx_insights_user_month` (`user_id`, `month`),
    CONSTRAINT `fk_insights_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 8. BOOKMARKS & NOTES TABLE
-- SRS 1.6 Page 8: Bookmarks a saving tip or monthly insight, custom notes
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `bookmarks`;
CREATE TABLE `bookmarks` (
    `bookmark_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `item_type` ENUM('tip', 'insight') NOT NULL DEFAULT 'tip',
    `item_id` INT UNSIGNED DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `student_note` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`bookmark_id`),
    KEY `fk_bookmarks_user` (`user_id`),
    INDEX `idx_bookmarks_user_type` (`user_id`, `item_type`),
    CONSTRAINT `fk_bookmarks_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 9. ANNOUNCEMENTS & TIP TEMPLATES TABLE
-- SRS 1.6 Page 9: Admin control panel broadcast templates
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
    `announcement_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `type` ENUM('announcement', 'tip_template') NOT NULL DEFAULT 'announcement',
    `body` TEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`announcement_id`),
    INDEX `idx_announcements_type_active` (`type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================
-- SEED DATA: Mandatory Default Categories (SRS 1.6 Page 7)
-- ==========================================================
INSERT INTO `categories` (`user_id`, `name`, `type`, `is_default`, `icon`) VALUES
-- Default Income Categories (SRS Page 7)
(NULL, 'Allowance', 'income', 1, '💰'),
(NULL, 'Part-time Job', 'income', 1, '💼'),
(NULL, 'Scholarship', 'income', 1, '🎓'),
(NULL, 'Gift', 'income', 1, '🎁'),
(NULL, 'Other Income', 'income', 1, '➕'),

-- Default Expense Categories (SRS Page 7)
(NULL, 'Food', 'expense', 1, '🍔'),
(NULL, 'Transport', 'expense', 1, '🚌'),
(NULL, 'Hostel/Rent', 'expense', 1, '🏠'),
(NULL, 'Academics', 'expense', 1, '📚'),
(NULL, 'Subscriptions', 'expense', 1, '📱'),
(NULL, 'Entertainment', 'expense', 1, '🎬'),
(NULL, 'Miscellaneous', 'expense', 1, '📦');

-- ==========================================================
-- SEED DATA: Default Admin & Demo Student Accounts
-- Passwords hashed via PHP password_hash(..., PASSWORD_BCRYPT)
-- Demo Admin: admin@campuscoin.com / admin123
-- Demo Student: student@campuscoin.com / 123456
-- ==========================================================
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `academic_year`, `monthly_allowance`, `monthly_savings_goal`, `is_active`) VALUES
('Administrator', 'admin@campuscoin.com', '$2y$10$Fq4S8pIUq4mJLV0FtPBoZe/kVz7eLKQCTEM82ifPgPqYpG9oJVwYe', 'admin', 'Staff', 0.00, 0.00, 1),
('Ayesha Khan', 'student@campuscoin.com', '$2y$10$N7n6SLQ3VC7hCtu1j5aKVO4BcdzLYmUHVcV1Ge4rHbhtAV1tYLmIi', 'student', '2nd Year (Sophomore)', 15000.00, 3000.00, 1);

-- Default Announcements
INSERT INTO `announcements` (`title`, `type`, `body`, `is_active`) VALUES
('Welcome to Campus Coin!', 'announcement', 'Track every rupee of your allowance, set monthly budgets, and build healthy student savings habits.', 1),
('Weekly Food Cap Tip', 'tip_template', 'Establishing a weekly dining and canteen ceiling prevents unexpected shortfalls before month-end.', 1);
