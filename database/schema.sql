-- =============================================================================
-- DefMan (Defect Manager) — Modernized MySQL 8.0+ DDL Schema
-- Refactored from: install/installdb.php, install/install-part1.php, install/install-part2.php
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------------------------------
-- 1. Table: `product` (Refactored from install/installdb.php lines 106-119)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `analyse`;
DROP TABLE IF EXISTS `raport`;
DROP TABLE IF EXISTS `user_sessions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `product`;

CREATE TABLE `product` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Table: `users` (Refactored from install/installdb.php lines 55-82)
-- User Levels:
--   0 = Applying (Pending Admin Approval - form_apply.php)
--   1 = Public   (Unauthenticated / Public reporter - form_raportpub.php)
--   2 = Customer (Authenticated defect reporter - form_raport.php)
--   3 = Analyzer (Developer / QA triage & IEEE fault cost tracking - form_analyze.php)
--   4 = Admin    (Full system access, user approval & product CRUD - form_usermanagement.php)
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` VARCHAR(64) NOT NULL COMMENT 'Legacy max 6 chars in 2004 UI; widened for modern auth',
  `password` VARCHAR(255) NOT NULL COMMENT 'Argon2id password hash (PASSWORD_ARGON2ID)',
  `email` VARCHAR(255) NOT NULL,
  `userlevel` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Applying, 1=Public, 2=Customer, 3=Analyzer, 4=Admin',
  `company` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `name` VARCHAR(100) DEFAULT NULL,
  `activate` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=Pending/Inactive, 1=Active',
  `logged` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=Offline, 1=Online',
  `reset_token_hash` CHAR(64) DEFAULT NULL COMMENT 'SHA-256 hash of random_bytes(32) recovery token',
  `reset_token_expires_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_login` (`login`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_level_activate` (`userlevel`, `activate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table: `user_sessions` (Cryptographically secure bearer/session tokens)
-- -----------------------------------------------------------------------------
CREATE TABLE `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` SMALLINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 hash of 256-bit random_bytes token',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_sessions_token_hash` (`token_hash`),
  KEY `idx_user_sessions_user_id` (`user_id`),
  KEY `idx_user_sessions_expires_at` (`expires_at`),
  CONSTRAINT `fk_user_sessions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Table: `raport` (Refactored from install/installdb.php lines 84-104)
-- Preserves 2004 table & column names while enforcing foreign keys & indexes.
-- Note: `CustomerID` is nullable with ON DELETE SET NULL so deleted users
-- still render as "[DELETED USER]" per form_raportlist.php lines 1002-1012.
-- -----------------------------------------------------------------------------
CREATE TABLE `raport` (
  `ID` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Product` VARCHAR(100) NOT NULL,
  `product_id` SMALLINT UNSIGNED DEFAULT NULL,
  `Environment` TEXT DEFAULT NULL,
  `CustomerID` SMALLINT UNSIGNED DEFAULT NULL,
  `Who` VARCHAR(64) NOT NULL,
  `Contactemail` VARCHAR(255) NOT NULL,
  `Whenreported` VARCHAR(32) NOT NULL COMMENT 'Format: YYYY-MM-DD HH:mm',
  `Title` VARCHAR(255) NOT NULL,
  `Description` TEXT NOT NULL,
  `Discoveredby` VARCHAR(100) DEFAULT NULL,
  `Persontype` ENUM('Customer', 'Developer', 'Tester') NOT NULL DEFAULT 'Customer',
  `Occured` DATE NOT NULL,
  `Location` VARCHAR(255) DEFAULT NULL,
  `Severity` ENUM('minor', 'major', 'critical') NOT NULL DEFAULT 'minor',
  `Priority` ENUM('m_urgent', 'average', 'l_urgent') NOT NULL DEFAULT 'average',
  `Submitted` DATE NOT NULL,
  `Addinfo` TEXT DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `idx_raport_product` (`Product`),
  KEY `idx_raport_product_id` (`product_id`),
  KEY `idx_raport_customer` (`CustomerID`),
  KEY `idx_raport_severity_submitted` (`Severity`, `Submitted` DESC),
  KEY `idx_raport_priority_submitted` (`Priority`, `Submitted` DESC),
  KEY `idx_raport_submitted` (`Submitted` DESC),
  CONSTRAINT `fk_raport_product`
    FOREIGN KEY (`product_id`) REFERENCES `product` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_raport_customer`
    FOREIGN KEY (`CustomerID`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Table: `analyse` (Refactored from install/installdb.php lines 21-53)
-- 1:1 extension of `raport` tracking IEEE (1992) fault classification,
-- origin phase, fix effort (hours), fix cost (EUR), and total defect cost (EUR).
-- -----------------------------------------------------------------------------
CREATE TABLE `analyse` (
  `id` SMALLINT UNSIGNED NOT NULL COMMENT '1:1 Primary & Foreign Key to raport.ID',
  `devdesc` TEXT DEFAULT NULL COMMENT 'Defect description by developer',
  `classification` VARCHAR(100) DEFAULT NULL COMMENT 'IEEE 1992 Fault Classification (IeeeFaultType or custom Other)',
  `dateoriginated` DATE DEFAULT NULL,
  `phase` VARCHAR(255) DEFAULT NULL COMMENT 'Comma-delimited phases: Requirements,Design,Implementation,Testing,Deployment,Maintenance',
  `actoriginated` TEXT DEFAULT NULL COMMENT 'Activity which originated the defect',
  `deliverable` VARCHAR(255) DEFAULT NULL COMMENT 'Deliverable originated',
  `whattodo` TEXT DEFAULT NULL,
  `submitted` DATE DEFAULT NULL,
  `assigned` DATE DEFAULT NULL,
  `uniqueness` ENUM('New defect', 'Already submitted', 'Critical') NOT NULL DEFAULT 'New defect',
  `priority` ENUM('most urgent', 'average', 'less urgent') NOT NULL DEFAULT 'average',
  `severity` ENUM('minor', 'major', 'critical') NOT NULL DEFAULT 'minor',
  `workaround` TEXT DEFAULT NULL,
  `proposedfix` TEXT DEFAULT NULL,
  `status` ENUM('submitted', 'assigned', 'postponed', 'opened', 'fixed', 'resolved', 'closed') NOT NULL DEFAULT 'submitted',
  `fixdesc` TEXT DEFAULT NULL COMMENT 'Description of fix (form_deffix.php)',
  `fixstarted` DATE DEFAULT NULL COMMENT 'Date work started on fix',
  `fixcomplete` DATE DEFAULT NULL COMMENT 'Date work completed on fix',
  `fiximpact` TEXT DEFAULT NULL COMMENT 'Deliverables impacted by fix',
  `fixeffort` DECIMAL(8,2) UNSIGNED DEFAULT NULL COMMENT 'Effort expended on fix in hours',
  `fixcost` DECIMAL(10,2) UNSIGNED DEFAULT NULL COMMENT 'Monetary cost of fix in EUR',
  `resodesc` TEXT DEFAULT NULL COMMENT 'Description of resolution (form_defreso.php)',
  `resocomplete` DATE DEFAULT NULL COMMENT 'Date of resolution',
  `resoclosed` DATE DEFAULT NULL COMMENT 'Date closed',
  `costofdefect` DECIMAL(10,2) UNSIGNED DEFAULT NULL COMMENT 'Total cost of defect in EUR',
  `actfound` TEXT DEFAULT NULL COMMENT 'Activity that should have found defect',
  PRIMARY KEY (`id`),
  KEY `idx_analyse_status_submitted` (`status`, `submitted` DESC),
  KEY `idx_analyse_severity_submitted` (`severity`, `submitted` DESC),
  KEY `idx_analyse_priority_submitted` (`priority`, `submitted` DESC),
  KEY `idx_analyse_classification` (`classification`),
  CONSTRAINT `fk_analyse_raport`
    FOREIGN KEY (`id`) REFERENCES `raport` (`ID`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
