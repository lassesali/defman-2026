-- =============================================================================
-- File: 02_migrate_data.sql
-- Description: Idempotent, Zero-Data-Loss DML Migration from Legacy Tables
--              (`product`, `users`, `user_sessions`, `raport`, `analyse`)
--              into the 3NF Schema (`products`, `companies`, `users`,
--              `user_sessions`, `password_resets`, `sdlc_phases`,
--              `fault_classifications`, `defect_reports`, `defect_analyses`,
--              `analysis_phases`).
-- =============================================================================

SET NAMES utf8mb4;

-- =============================================================================
-- PRE-FLIGHT DDL PREPARATION (for in-place migration on an existing database)
-- Note: MySQL implicitly commits on DDL (CREATE/ALTER/DROP/RENAME), so schema
-- preparation runs prior to the atomic DML transaction block below.
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `products` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `companies` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_companies_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add `company_id` to `users` if migrating in-place from legacy `users`
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'company_id'
);
SET @sql_add_company_id := IF(
  @col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `company_id` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `userlevel`, ADD KEY `idx_users_company_id` (`company_id`), ADD CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_company_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` SMALLINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_password_resets_token_hash` (`token_hash`),
  KEY `idx_password_resets_user_expires` (`user_id`, `expires_at`),
  CONSTRAINT `fk_password_resets_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sdlc_phases` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sdlc_phases_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fault_classifications` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fault_classifications_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `defect_analyses` (
  `report_id` INT UNSIGNED NOT NULL,
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

-- =============================================================================
-- ATOMIC DML DATA MIGRATION (START TRANSACTION ... COMMIT)
-- =============================================================================
START TRANSACTION;

-- -----------------------------------------------------------------------------
-- 1. Migrate `product` -> `products` and backfill uncataloged `raport.Product`
-- -----------------------------------------------------------------------------
INSERT INTO `products` (`id`, `name`, `created_at`)
SELECT
  p.`id`,
  TRIM(p.`name`),
  p.`created_at`
FROM `product` p
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`);

INSERT IGNORE INTO `products` (`name`)
SELECT DISTINCT
  COALESCE(NULLIF(TRIM(r.`Product`), ''), 'Unknown Product')
FROM `raport` r
LEFT JOIN `products` p
  ON p.`name` = COALESCE(NULLIF(TRIM(r.`Product`), ''), 'Unknown Product')
WHERE p.`id` IS NULL;

-- Backfill NULL `raport.product_id` by matching `raport.Product = products.name`
UPDATE `raport` r
INNER JOIN `products` p
  ON p.`name` = COALESCE(NULLIF(TRIM(r.`Product`), ''), 'Unknown Product')
SET r.`product_id` = p.`id`
WHERE r.`product_id` IS NULL;

-- -----------------------------------------------------------------------------
-- 2. Extract distinct non-null `users.company` into `companies` & link `company_id`
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `companies` (`name`)
SELECT DISTINCT
  TRIM(u.`company`)
FROM `users` u
WHERE u.`company` IS NOT NULL
  AND TRIM(u.`company`) <> '';

UPDATE `users` u
INNER JOIN `companies` c
  ON c.`name` = TRIM(u.`company`)
SET u.`company_id` = c.`id`
WHERE u.`company` IS NOT NULL
  AND TRIM(u.`company`) <> '';

-- -----------------------------------------------------------------------------
-- 3. Migrate active non-null `reset_token_hash` rows from `users` to `password_resets`
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `password_resets` (`user_id`, `token_hash`, `expires_at`, `created_at`)
SELECT
  u.`id`,
  u.`reset_token_hash`,
  COALESCE(u.`reset_token_expires_at`, DATE_ADD(NOW(), INTERVAL 1 HOUR)),
  u.`updated_at`
FROM `users` u
WHERE u.`reset_token_hash` IS NOT NULL
  AND TRIM(u.`reset_token_hash`) <> ''
  AND (u.`reset_token_expires_at` IS NULL OR u.`reset_token_expires_at` > NOW());

-- -----------------------------------------------------------------------------
-- 4. Migrate `raport` -> `defect_reports`
--    - Converts `Whenreported` VARCHAR(32) to DATETIME `reported_at` via STR_TO_DATE()
--    - Normalizes reporter identity (`reporter_user_id` vs `guest_name`/`guest_email`)
--    - Standardizes `Priority` ('m_urgent' -> 'most_urgent', 'l_urgent' -> 'less_urgent')
-- -----------------------------------------------------------------------------
INSERT INTO `defect_reports` (
  `id`,
  `product_id`,
  `environment`,
  `reporter_user_id`,
  `guest_name`,
  `guest_email`,
  `reported_at`,
  `title`,
  `description`,
  `discovered_by`,
  `person_type`,
  `occurred_on`,
  `location`,
  `initial_severity`,
  `initial_priority`,
  `additional_info`
)
SELECT
  r.`ID` AS `id`,
  COALESCE(p_id.`id`, p_name.`id`) AS `product_id`,
  r.`Environment` AS `environment`,
  u.`id` AS `reporter_user_id`,
  CASE
    WHEN u.`id` IS NOT NULL THEN NULL
    ELSE COALESCE(NULLIF(TRIM(r.`Who`), ''), 'Anonymous Guest')
  END AS `guest_name`,
  CASE
    WHEN u.`id` IS NOT NULL THEN NULL
    ELSE COALESCE(NULLIF(TRIM(r.`Contactemail`), ''), 'unknown@example.invalid')
  END AS `guest_email`,
  COALESCE(
    STR_TO_DATE(TRIM(r.`Whenreported`), '%Y-%m-%d %H:%i:%s'),
    STR_TO_DATE(TRIM(r.`Whenreported`), '%Y-%m-%d %H:%i'),
    STR_TO_DATE(TRIM(r.`Whenreported`), '%Y-%m-%d'),
    CAST(r.`Submitted` AS DATETIME),
    NOW()
  ) AS `reported_at`,
  r.`Title` AS `title`,
  r.`Description` AS `description`,
  r.`Discoveredby` AS `discovered_by`,
  r.`Persontype` AS `person_type`,
  r.`Occured` AS `occurred_on`,
  r.`Location` AS `location`,
  r.`Severity` AS `initial_severity`,
  CASE r.`Priority`
    WHEN 'm_urgent' THEN 'most_urgent'
    WHEN 'average'  THEN 'average'
    WHEN 'l_urgent' THEN 'less_urgent'
    ELSE 'average'
  END AS `initial_priority`,
  r.`Addinfo` AS `additional_info`
FROM `raport` r
LEFT JOIN `products` p_id
  ON p_id.`id` = r.`product_id`
LEFT JOIN `products` p_name
  ON p_name.`name` = COALESCE(NULLIF(TRIM(r.`Product`), ''), 'Unknown Product')
LEFT JOIN `users` u
  ON u.`id` = r.`CustomerID`
ON DUPLICATE KEY UPDATE
  `product_id`       = VALUES(`product_id`),
  `reporter_user_id` = VALUES(`reporter_user_id`),
  `guest_name`       = VALUES(`guest_name`),
  `guest_email`      = VALUES(`guest_email`),
  `reported_at`      = VALUES(`reported_at`),
  `initial_priority` = VALUES(`initial_priority`);

-- -----------------------------------------------------------------------------
-- 5. Migrate `analyse.classification` -> `fault_classifications`
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `fault_classifications` (`code`)
SELECT DISTINCT
  TRIM(a.`classification`)
FROM `analyse` a
WHERE a.`classification` IS NOT NULL
  AND TRIM(a.`classification`) <> '';

-- -----------------------------------------------------------------------------
-- 6. Migrate `analyse` -> `defect_analyses`
--    - Standardizes `priority` ('most urgent' -> 'most_urgent', 'less urgent' -> 'less_urgent')
--    - Standardizes `uniqueness` ('New defect' -> 'new', 'Already submitted' -> 'duplicate')
--    - Preserves legacy `uniqueness = 'Critical'` by setting `severity = 'critical'` and `uniqueness = 'new'`
-- -----------------------------------------------------------------------------
INSERT INTO `defect_analyses` (
  `report_id`,
  `developer_description`,
  `classification_id`,
  `date_originated`,
  `activity_originated`,
  `deliverable`,
  `what_to_do`,
  `assigned_on`,
  `uniqueness`,
  `duplicate_of_report_id`,
  `priority`,
  `severity`,
  `workaround`,
  `proposed_fix`,
  `status`,
  `fix_description`,
  `fix_started_on`,
  `fix_completed_on`,
  `fix_impact`,
  `fix_effort`,
  `fix_cost`,
  `resolution_description`,
  `resolution_completed_on`,
  `resolution_closed_on`,
  `cost_of_defect`,
  `activity_found`
)
SELECT
  a.`id` AS `report_id`,
  a.`devdesc` AS `developer_description`,
  fc.`id` AS `classification_id`,
  a.`dateoriginated` AS `date_originated`,
  a.`actoriginated` AS `activity_originated`,
  a.`deliverable` AS `deliverable`,
  a.`whattodo` AS `what_to_do`,
  a.`assigned` AS `assigned_on`,
  CASE a.`uniqueness`
    WHEN 'Already submitted' THEN 'duplicate'
    WHEN 'New defect'        THEN 'new'
    WHEN 'Critical'          THEN 'new'
    ELSE 'new'
  END AS `uniqueness`,
  NULL AS `duplicate_of_report_id`,
  CASE a.`priority`
    WHEN 'most urgent' THEN 'most_urgent'
    WHEN 'average'     THEN 'average'
    WHEN 'less urgent' THEN 'less_urgent'
    ELSE 'average'
  END AS `priority`,
  CASE
    WHEN a.`uniqueness` = 'Critical' THEN 'critical'
    ELSE a.`severity`
  END AS `severity`,
  a.`workaround` AS `workaround`,
  a.`proposedfix` AS `proposed_fix`,
  a.`status` AS `status`,
  a.`fixdesc` AS `fix_description`,
  a.`fixstarted` AS `fix_started_on`,
  a.`fixcomplete` AS `fix_completed_on`,
  a.`fiximpact` AS `fix_impact`,
  a.`fixeffort` AS `fix_effort`,
  a.`fixcost` AS `fix_cost`,
  a.`resodesc` AS `resolution_description`,
  a.`resocomplete` AS `resolution_completed_on`,
  a.`resoclosed` AS `resolution_closed_on`,
  a.`costofdefect` AS `cost_of_defect`,
  a.`actfound` AS `activity_found`
FROM `analyse` a
INNER JOIN `defect_reports` dr
  ON dr.`id` = a.`id`
LEFT JOIN `fault_classifications` fc
  ON fc.`code` = TRIM(a.`classification`)
ON DUPLICATE KEY UPDATE
  `classification_id` = VALUES(`classification_id`),
  `uniqueness`        = VALUES(`uniqueness`),
  `priority`          = VALUES(`priority`),
  `severity`          = VALUES(`severity`);

-- -----------------------------------------------------------------------------
-- 7. Split comma-delimited `analyse.phase` into `sdlc_phases` and `analysis_phases`
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `sdlc_phases` (`name`)
WITH RECURSIVE `phase_splitter` (`report_id`, `phase_token`, `remainder`) AS (
  SELECT
    a.`id` AS `report_id`,
    TRIM(SUBSTRING_INDEX(a.`phase`, ',', 1)) AS `phase_token`,
    SUBSTRING(a.`phase`, CHAR_LENGTH(SUBSTRING_INDEX(a.`phase`, ',', 1)) + 2) AS `remainder`
  FROM `analyse` a
  WHERE a.`phase` IS NOT NULL
    AND TRIM(a.`phase`) <> ''
  UNION ALL
  SELECT
    ps.`report_id`,
    TRIM(SUBSTRING_INDEX(ps.`remainder`, ',', 1)) AS `phase_token`,
    SUBSTRING(ps.`remainder`, CHAR_LENGTH(SUBSTRING_INDEX(ps.`remainder`, ',', 1)) + 2) AS `remainder`
  FROM `phase_splitter` ps
  WHERE ps.`remainder` <> ''
)
SELECT DISTINCT
  ps.`phase_token`
FROM `phase_splitter` ps
WHERE ps.`phase_token` <> '';

INSERT IGNORE INTO `analysis_phases` (`report_id`, `phase_id`)
WITH RECURSIVE `phase_splitter` (`report_id`, `phase_token`, `remainder`) AS (
  SELECT
    a.`id` AS `report_id`,
    TRIM(SUBSTRING_INDEX(a.`phase`, ',', 1)) AS `phase_token`,
    SUBSTRING(a.`phase`, CHAR_LENGTH(SUBSTRING_INDEX(a.`phase`, ',', 1)) + 2) AS `remainder`
  FROM `analyse` a
  INNER JOIN `defect_analyses` da
    ON da.`report_id` = a.`id`
  WHERE a.`phase` IS NOT NULL
    AND TRIM(a.`phase`) <> ''
  UNION ALL
  SELECT
    ps.`report_id`,
    TRIM(SUBSTRING_INDEX(ps.`remainder`, ',', 1)) AS `phase_token`,
    SUBSTRING(ps.`remainder`, CHAR_LENGTH(SUBSTRING_INDEX(ps.`remainder`, ',', 1)) + 2) AS `remainder`
  FROM `phase_splitter` ps
  WHERE ps.`remainder` <> ''
)
SELECT
  ps.`report_id`,
  sp.`id` AS `phase_id`
FROM `phase_splitter` ps
INNER JOIN `sdlc_phases` sp
  ON sp.`name` = ps.`phase_token`
WHERE ps.`phase_token` <> '';

COMMIT;

-- =============================================================================
-- POST-MIGRATION CLEANUP (Drop denormalized columns & legacy tables)
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `users`
  DROP COLUMN IF EXISTS `company`,
  DROP COLUMN IF EXISTS `logged`,
  DROP COLUMN IF EXISTS `reset_token_hash`,
  DROP COLUMN IF EXISTS `reset_token_expires_at`;

DROP TABLE IF EXISTS `analyse`;
DROP TABLE IF EXISTS `raport`;
DROP TABLE IF EXISTS `product`;

SET FOREIGN_KEY_CHECKS = 1;
