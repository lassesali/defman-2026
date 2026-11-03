-- =============================================================================
-- DefMan (Defect Manager) — Modernized MySQL 8.0+ 3NF DDL Schema
-- Normalized to Third Normal Form (3NF) with IEEE Std 1044-2009 Anomaly
-- Classification, DORA Flow Telemetry, and Backward-Compatibility Views
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- Drop legacy views/tables if re-initializing
DROP VIEW IF EXISTS `analyse`;
DROP VIEW IF EXISTS `raport`;
DROP VIEW IF EXISTS `product`;

DROP TABLE IF EXISTS `analysis_phases`;
DROP TABLE IF EXISTS `defect_analyses`;
DROP TABLE IF EXISTS `defect_reports`;
DROP TABLE IF EXISTS `fault_classifications`;
DROP TABLE IF EXISTS `sdlc_phases`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `user_sessions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `companies`;
DROP TABLE IF EXISTS `products`;

-- -----------------------------------------------------------------------------
-- 1. Table: `products`
-- -----------------------------------------------------------------------------
CREATE TABLE `products` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Table: `companies` (3NF lookup table extracted from `users`)
-- -----------------------------------------------------------------------------
CREATE TABLE `companies` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_companies_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table: `users`
-- User Levels:
--   0 = Applying (Pending Admin Approval - form_apply.php)
--   1 = Public   (Unauthenticated / Public reporter - form_raportpub.php)
--   2 = Customer (Authenticated defect reporter - form_raport.php)
--   3 = Analyzer (Developer / QA triage & IEEE 1044-2009 / DORA telemetry - form_analyze.php)
--   4 = Admin    (Full system access, user approval & product CRUD - form_usermanagement.php)
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` VARCHAR(64) NOT NULL COMMENT 'Legacy max 6 chars in 2004 UI; widened for modern auth',
  `password` VARCHAR(255) NOT NULL COMMENT 'Argon2id password hash (PASSWORD_ARGON2ID)',
  `email` VARCHAR(255) NOT NULL,
  `userlevel` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Applying, 1=Public, 2=Customer, 3=Analyzer, 4=Admin',
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
-- 4. Table: `user_sessions` (Cryptographically secure bearer/session tokens)
-- -----------------------------------------------------------------------------
CREATE TABLE `user_sessions` (
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
-- 5. Table: `password_resets` (Decoupled password recovery tokens — 3NF)
-- -----------------------------------------------------------------------------
CREATE TABLE `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` SMALLINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 hash of random_bytes(32) recovery token',
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
-- 6. Table: `sdlc_phases` (1NF lookup table for SDLC origin phases)
-- -----------------------------------------------------------------------------
CREATE TABLE `sdlc_phases` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sdlc_phases_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Table: `fault_classifications` (3NF lookup table for IEEE Std 1044-2009 anomaly types)
-- -----------------------------------------------------------------------------
CREATE TABLE `fault_classifications` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fault_classifications_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Table: `defect_reports` (3NF normalized defect reports + environment telemetry)
-- -----------------------------------------------------------------------------
CREATE TABLE `defect_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` SMALLINT UNSIGNED NOT NULL,
  `environment` TEXT NULL DEFAULT NULL,
  `detected_environment` ENUM('DEV', 'CI', 'STAGING', 'PROD') NOT NULL DEFAULT 'PROD',
  `symptom_category` VARCHAR(100) NULL DEFAULT NULL COMMENT 'IEEE Std 1044-2009 observed symptom classification',
  `is_regression` BOOLEAN NOT NULL DEFAULT FALSE,
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
  KEY `idx_defect_reports_detected_env` (`detected_environment`, `reported_at` DESC),
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
-- 9. Table: `defect_analyses` (3NF normalized 1:1 extension with IEEE 1044-2009 & DORA fields)
-- -----------------------------------------------------------------------------
CREATE TABLE `defect_analyses` (
  `report_id` INT UNSIGNED NOT NULL COMMENT '1:1 Primary & Foreign Key to defect_reports.id',
  `developer_description` TEXT NULL DEFAULT NULL,
  `classification_id` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `symptom_category` VARCHAR(100) NULL DEFAULT NULL COMMENT 'IEEE Std 1044-2009 failure symptom category',
  `root_cause_type` VARCHAR(100) NULL DEFAULT NULL COMMENT 'IEEE Std 1044-2009 root cause classification',
  `detected_environment` ENUM('DEV', 'CI', 'STAGING', 'PROD') NOT NULL DEFAULT 'PROD',
  `is_regression` BOOLEAN NOT NULL DEFAULT FALSE,
  `introducing_ref` VARCHAR(128) NULL DEFAULT NULL COMMENT 'Commit SHA, PR, or build tag that introduced the anomaly',
  `resolution_ref` VARCHAR(128) NULL DEFAULT NULL COMMENT 'Commit SHA, PR, or release tag that resolved the anomaly',
  `actual_effort_hours` DECIMAL(8,2) UNSIGNED NULL DEFAULT NULL COMMENT 'Actual engineering effort expended in hours',
  `triaged_at` DATETIME NULL DEFAULT NULL COMMENT 'Timestamp when anomaly was triaged (DORA Triage Lead Time)',
  `resolved_at` DATETIME NULL DEFAULT NULL COMMENT 'Timestamp when anomaly was resolved (DORA MTTR)',
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
  KEY `idx_defect_analyses_root_cause` (`root_cause_type`),
  KEY `idx_defect_analyses_detected_env` (`detected_environment`),
  KEY `idx_defect_analyses_regression` (`is_regression`),
  KEY `idx_defect_analyses_triaged_resolved` (`triaged_at`, `resolved_at`),
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
-- 10. Table: `analysis_phases` (1NF junction table for defect_analyses <-> sdlc_phases)
-- -----------------------------------------------------------------------------
CREATE TABLE `analysis_phases` (
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

-- -----------------------------------------------------------------------------
-- Backward-Compatibility Views (`product`, `raport`, `analyse`)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW `product` AS
SELECT
  p.`id`,
  p.`name`,
  p.`created_at`
FROM `products` p;

CREATE OR REPLACE VIEW `raport` AS
SELECT
  dr.`id` AS `ID`,
  p.`name` AS `Product`,
  dr.`product_id` AS `product_id`,
  dr.`environment` AS `Environment`,
  dr.`reporter_user_id` AS `CustomerID`,
  COALESCE(u.`login`, dr.`guest_name`, '') AS `Who`,
  COALESCE(u.`email`, dr.`guest_email`, '') AS `Contactemail`,
  DATE_FORMAT(dr.`reported_at`, '%Y-%m-%d %H:%i') AS `Whenreported`,
  dr.`title` AS `Title`,
  dr.`description` AS `Description`,
  dr.`discovered_by` AS `Discoveredby`,
  dr.`person_type` AS `Persontype`,
  dr.`occurred_on` AS `Occured`,
  dr.`location` AS `Location`,
  dr.`initial_severity` AS `Severity`,
  CASE dr.`initial_priority`
    WHEN 'most_urgent' THEN 'm_urgent'
    WHEN 'average'     THEN 'average'
    WHEN 'less_urgent' THEN 'l_urgent'
  END AS `Priority`,
  DATE(dr.`reported_at`) AS `Submitted`,
  dr.`additional_info` AS `Addinfo`
FROM `defect_reports` dr
INNER JOIN `products` p
  ON p.`id` = dr.`product_id`
LEFT JOIN `users` u
  ON u.`id` = dr.`reporter_user_id`;

CREATE OR REPLACE VIEW `analyse` AS
SELECT
  da.`report_id` AS `id`,
  da.`developer_description` AS `devdesc`,
  COALESCE(da.`root_cause_type`, fc.`code`) AS `classification`,
  da.`date_originated` AS `dateoriginated`,
  ph.`phases_csv` AS `phase`,
  da.`activity_originated` AS `actoriginated`,
  da.`deliverable` AS `deliverable`,
  da.`what_to_do` AS `whattodo`,
  DATE(dr.`reported_at`) AS `submitted`,
  COALESCE(da.`assigned_on`, DATE(da.`triaged_at`)) AS `assigned`,
  CASE da.`uniqueness`
    WHEN 'duplicate' THEN 'Already submitted'
    WHEN 'new'       THEN 'New defect'
  END AS `uniqueness`,
  CASE da.`priority`
    WHEN 'most_urgent' THEN 'most urgent'
    WHEN 'average'     THEN 'average'
    WHEN 'less_urgent' THEN 'less urgent'
  END AS `priority`,
  da.`severity` AS `severity`,
  da.`workaround` AS `workaround`,
  da.`proposed_fix` AS `proposedfix`,
  da.`status` AS `status`,
  da.`fix_description` AS `fixdesc`,
  da.`fix_started_on` AS `fixstarted`,
  da.`fix_completed_on` AS `fixcomplete`,
  da.`fix_impact` AS `fiximpact`,
  COALESCE(da.`actual_effort_hours`, da.`fix_effort`) AS `fixeffort`,
  da.`fix_cost` AS `fixcost`,
  da.`resolution_description` AS `resodesc`,
  COALESCE(da.`resolution_completed_on`, DATE(da.`resolved_at`)) AS `resocomplete`,
  da.`resolution_closed_on` AS `resoclosed`,
  da.`cost_of_defect` AS `costofdefect`,
  da.`activity_found` AS `actfound`
FROM `defect_analyses` da
INNER JOIN `defect_reports` dr
  ON dr.`id` = da.`report_id`
LEFT JOIN `fault_classifications` fc
  ON fc.`id` = da.`classification_id`
LEFT JOIN (
  SELECT
    ap.`report_id`,
    GROUP_CONCAT(sp.`name` ORDER BY sp.`id` SEPARATOR ',') AS `phases_csv`
  FROM `analysis_phases` ap
  INNER JOIN `sdlc_phases` sp
    ON sp.`id` = ap.`phase_id`
  GROUP BY ap.`report_id`
) ph
  ON ph.`report_id` = da.`report_id`;

SET FOREIGN_KEY_CHECKS = 1;
