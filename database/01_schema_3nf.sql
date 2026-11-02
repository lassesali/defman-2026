-- =============================================================================
-- File: 01_schema_3nf.sql
-- Description: Target 3NF Relational Schema for DefMan-2026 (MySQL 8.0+ / MariaDB 10.6+)
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------------------------------
-- 1. Table: `products` (standardized from `product`)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Table: `companies` (extracted from `users.company` — 3NF)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `companies` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_companies_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table: `users` (normalized: `company_id` FK; removed `logged` & reset cols)
-- User Levels:
--   0 = Applying (Pending Admin Approval)
--   1 = Public   (Unauthenticated / Public reporter)
--   2 = Customer (Authenticated defect reporter)
--   3 = Analyzer (Developer / QA triage & IEEE fault cost tracking)
--   4 = Admin    (Full system access, user approval & product CRUD)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` VARCHAR(64) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'Argon2id password hash (PASSWORD_ARGON2ID)',
  `email` VARCHAR(255) NOT NULL,
  `userlevel` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Applying..4=Admin',
  `company_id` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `phone` VARCHAR(50) NULL DEFAULT NULL,
  `name` VARCHAR(100) NULL DEFAULT NULL,
  `activate` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=Pending/Inactive, 1=Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_login` (`login`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_company_id` (`company_id`),
  KEY `idx_users_level_activate` (`userlevel`, `activate`),
  CONSTRAINT `fk_users_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Table: `user_sessions` (active rows derive user online status)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` SMALLINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 hash of 256-bit random_bytes token',
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_sessions_token_hash` (`token_hash`),
  KEY `idx_user_sessions_user_expires` (`user_id`, `expires_at`),
  KEY `idx_user_sessions_expires_at` (`expires_at`),
  CONSTRAINT `fk_user_sessions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Table: `password_resets` (decoupled from `users` — 3NF)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` SMALLINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 hash of recovery token',
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_password_resets_token_hash` (`token_hash`),
  KEY `idx_password_resets_user_expires` (`user_id`, `expires_at`),
  CONSTRAINT `fk_password_resets_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Table: `sdlc_phases` (lookup table for SDLC origin phases — 1NF)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sdlc_phases` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sdlc_phases_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Table: `fault_classifications` (IEEE 1044-1992 fault classification — 3NF)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fault_classifications` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fault_classifications_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Table: `defect_reports` (standardized & normalized from `raport`)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `defect_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` SMALLINT UNSIGNED NOT NULL,
  `environment` TEXT NULL DEFAULT NULL,
  `reporter_user_id` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `guest_name` VARCHAR(100) NULL DEFAULT NULL,
  `guest_email` VARCHAR(255) NULL DEFAULT NULL,
  `reported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `discovered_by` VARCHAR(100) NULL DEFAULT NULL,
  `person_type` ENUM('Customer', 'Developer', 'Tester') NOT NULL DEFAULT 'Customer',
  `occurred_on` DATE NOT NULL,
  `location` VARCHAR(255) NULL DEFAULT NULL,
  `initial_severity` ENUM('minor', 'major', 'critical') NOT NULL DEFAULT 'minor',
  `initial_priority` ENUM('most_urgent', 'average', 'less_urgent') NOT NULL DEFAULT 'average',
  `additional_info` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_defect_reports_product_id` (`product_id`),
  KEY `idx_defect_reports_reporter_user_id` (`reporter_user_id`),
  KEY `idx_defect_reports_reported_at` (`reported_at` DESC),
  KEY `idx_defect_reports_severity_reported` (`initial_severity`, `reported_at` DESC),
  KEY `idx_defect_reports_priority_reported` (`initial_priority`, `reported_at` DESC),
  CONSTRAINT `fk_defect_reports_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_defect_reports_reporter`
    FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_defect_reports_reporter_identity`
    CHECK (
      `reporter_user_id` IS NOT NULL
      OR (`guest_name` IS NOT NULL AND `guest_email` IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Table: `defect_analyses` (standardized & normalized from `analyse`)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `defect_analyses` (
  `report_id` INT UNSIGNED NOT NULL COMMENT '1:1 Primary & Foreign Key to defect_reports.id',
  `developer_description` TEXT NULL DEFAULT NULL,
  `classification_id` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `date_originated` DATE NULL DEFAULT NULL,
  `activity_originated` TEXT NULL DEFAULT NULL,
  `deliverable` VARCHAR(255) NULL DEFAULT NULL,
  `what_to_do` TEXT NULL DEFAULT NULL,
  `assigned_on` DATE NULL DEFAULT NULL,
  `uniqueness` ENUM('new', 'duplicate') NOT NULL DEFAULT 'new',
  `duplicate_of_report_id` INT UNSIGNED NULL DEFAULT NULL,
  `priority` ENUM('most_urgent', 'average', 'less_urgent') NOT NULL DEFAULT 'average',
  `severity` ENUM('minor', 'major', 'critical') NOT NULL DEFAULT 'minor',
  `workaround` TEXT NULL DEFAULT NULL,
  `proposed_fix` TEXT NULL DEFAULT NULL,
  `status` ENUM('submitted', 'assigned', 'postponed', 'opened', 'fixed', 'resolved', 'closed') NOT NULL DEFAULT 'submitted',
  `fix_description` TEXT NULL DEFAULT NULL,
  `fix_started_on` DATE NULL DEFAULT NULL,
  `fix_completed_on` DATE NULL DEFAULT NULL,
  `fix_impact` TEXT NULL DEFAULT NULL,
  `fix_effort` DECIMAL(8,2) UNSIGNED NULL DEFAULT NULL,
  `fix_cost` DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,
  `resolution_description` TEXT NULL DEFAULT NULL,
  `resolution_completed_on` DATE NULL DEFAULT NULL,
  `resolution_closed_on` DATE NULL DEFAULT NULL,
  `cost_of_defect` DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,
  `activity_found` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`report_id`),
  KEY `idx_defect_analyses_classification_id` (`classification_id`),
  KEY `idx_defect_analyses_duplicate_of` (`duplicate_of_report_id`),
  KEY `idx_defect_analyses_status_priority` (`status`, `priority`, `severity`),
  CONSTRAINT `fk_defect_analyses_report`
    FOREIGN KEY (`report_id`) REFERENCES `defect_reports` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_defect_analyses_classification`
    FOREIGN KEY (`classification_id`) REFERENCES `fault_classifications` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_defect_analyses_duplicate_of`
    FOREIGN KEY (`duplicate_of_report_id`) REFERENCES `defect_reports` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Table: `analysis_phases` (junction table for defect_analyses <-> sdlc_phases — 1NF)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `analysis_phases` (
  `report_id` INT UNSIGNED NOT NULL,
  `phase_id` TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (`report_id`, `phase_id`),
  KEY `idx_analysis_phases_phase_id` (`phase_id`),
  CONSTRAINT `fk_analysis_phases_analysis`
    FOREIGN KEY (`report_id`) REFERENCES `defect_analyses` (`report_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_analysis_phases_phase`
    FOREIGN KEY (`phase_id`) REFERENCES `sdlc_phases` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
