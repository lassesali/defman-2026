<?php
declare(strict_types=1);

namespace DefMan\Database;

use DefMan\Domain\Enum\DefectPriority;
use DefMan\Domain\Enum\DefectSeverity;
use DefMan\Domain\Enum\DefectStatus;
use DefMan\Domain\Enum\DetectedEnvironment;
use DefMan\Domain\Enum\IeeeFaultType;
use DefMan\Domain\Enum\OriginPhase;
use DefMan\Domain\Enum\PersonType;
use DefMan\Domain\Enum\SymptomCategory;
use DefMan\Domain\Enum\Uniqueness;
use DefMan\Domain\Enum\UserLevel;
use PDO;

/**
 * PDO Database Seeder (3NF Normalized Schema + IEEE Std 1044-2009 & DORA Telemetry)
 *
 * Test/Seed Account Rule:
 * - Admin / System placeholders: zuckerberg@fb.com, billg@microsoft.com
 * - Customer / Analyzer accounts: Sabrina (sabrina@hotmail.com), Julia (julia@hotmail.com)
 */
final readonly class DatabaseSeeder
{
    public function __construct(private PDO $pdo) {}

    /**
     * Ensures all 3NF + IEEE Std 1044-2009 + DORA tables and columns exist
     * even if an existing Docker volume was initialized with an earlier schema.
     */
    public function ensureSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `products` (
              `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(100) NOT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_products_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `companies` (
              `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(100) NOT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_companies_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
              `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `login` VARCHAR(64) NOT NULL,
              `password` VARCHAR(255) NOT NULL,
              `email` VARCHAR(255) NOT NULL,
              `userlevel` TINYINT UNSIGNED NOT NULL DEFAULT 0,
              `company_id` SMALLINT UNSIGNED NULL DEFAULT NULL,
              `phone` VARCHAR(50) NULL DEFAULT NULL,
              `name` VARCHAR(100) NULL DEFAULT NULL,
              `activate` TINYINT(1) NOT NULL DEFAULT 0,
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->ensureColumn(
            'users',
            'company_id',
            'ALTER TABLE `users` ADD COLUMN `company_id` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `userlevel`, ADD KEY `idx_users_company_id` (`company_id`), ADD CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
        );

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_sessions` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id` SMALLINT UNSIGNED NOT NULL,
              `token_hash` CHAR(64) NOT NULL,
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `sdlc_phases` (
              `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(64) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_sdlc_phases_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `fault_classifications` (
              `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `code` VARCHAR(100) NOT NULL,
              `description` VARCHAR(255) NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_fault_classifications_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `defect_reports` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `product_id` SMALLINT UNSIGNED NOT NULL,
              `environment` TEXT NULL DEFAULT NULL,
              `detected_environment` ENUM('DEV', 'CI', 'STAGING', 'PROD') NOT NULL DEFAULT 'PROD',
              `symptom_category` VARCHAR(100) NULL DEFAULT NULL,
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
                ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->ensureColumn(
            'defect_reports',
            'detected_environment',
            "ALTER TABLE `defect_reports` ADD COLUMN `detected_environment` ENUM('DEV', 'CI', 'STAGING', 'PROD') NOT NULL DEFAULT 'PROD' AFTER `environment`"
        );
        $this->ensureColumn(
            'defect_reports',
            'symptom_category',
            'ALTER TABLE `defect_reports` ADD COLUMN `symptom_category` VARCHAR(100) NULL DEFAULT NULL AFTER `detected_environment`'
        );
        $this->ensureColumn(
            'defect_reports',
            'is_regression',
            'ALTER TABLE `defect_reports` ADD COLUMN `is_regression` BOOLEAN NOT NULL DEFAULT FALSE AFTER `symptom_category`'
        );

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `defect_analyses` (
              `report_id` INT UNSIGNED NOT NULL,
              `developer_description` TEXT NULL DEFAULT NULL,
              `classification_id` SMALLINT UNSIGNED NULL DEFAULT NULL,
              `symptom_category` VARCHAR(100) NULL DEFAULT NULL,
              `root_cause_type` VARCHAR(100) NULL DEFAULT NULL,
              `detected_environment` ENUM('DEV', 'CI', 'STAGING', 'PROD') NOT NULL DEFAULT 'PROD',
              `is_regression` BOOLEAN NOT NULL DEFAULT FALSE,
              `introducing_ref` VARCHAR(128) NULL DEFAULT NULL,
              `resolution_ref` VARCHAR(128) NULL DEFAULT NULL,
              `actual_effort_hours` DECIMAL(8,2) UNSIGNED NULL DEFAULT NULL,
              `triaged_at` DATETIME NULL DEFAULT NULL,
              `resolved_at` DATETIME NULL DEFAULT NULL,
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->ensureColumn('defect_analyses', 'symptom_category', 'ALTER TABLE `defect_analyses` ADD COLUMN `symptom_category` VARCHAR(100) NULL DEFAULT NULL AFTER `classification_id`');
        $this->ensureColumn('defect_analyses', 'root_cause_type', 'ALTER TABLE `defect_analyses` ADD COLUMN `root_cause_type` VARCHAR(100) NULL DEFAULT NULL AFTER `symptom_category`');
        $this->ensureColumn('defect_analyses', 'detected_environment', "ALTER TABLE `defect_analyses` ADD COLUMN `detected_environment` ENUM('DEV', 'CI', 'STAGING', 'PROD') NOT NULL DEFAULT 'PROD' AFTER `root_cause_type`");
        $this->ensureColumn('defect_analyses', 'is_regression', 'ALTER TABLE `defect_analyses` ADD COLUMN `is_regression` BOOLEAN NOT NULL DEFAULT FALSE AFTER `detected_environment`');
        $this->ensureColumn('defect_analyses', 'introducing_ref', 'ALTER TABLE `defect_analyses` ADD COLUMN `introducing_ref` VARCHAR(128) NULL DEFAULT NULL AFTER `is_regression`');
        $this->ensureColumn('defect_analyses', 'resolution_ref', 'ALTER TABLE `defect_analyses` ADD COLUMN `resolution_ref` VARCHAR(128) NULL DEFAULT NULL AFTER `introducing_ref`');
        $this->ensureColumn('defect_analyses', 'actual_effort_hours', 'ALTER TABLE `defect_analyses` ADD COLUMN `actual_effort_hours` DECIMAL(8,2) UNSIGNED NULL DEFAULT NULL AFTER `resolution_ref`');
        $this->ensureColumn('defect_analyses', 'triaged_at', 'ALTER TABLE `defect_analyses` ADD COLUMN `triaged_at` DATETIME NULL DEFAULT NULL AFTER `actual_effort_hours`');
        $this->ensureColumn('defect_analyses', 'resolved_at', 'ALTER TABLE `defect_analyses` ADD COLUMN `resolved_at` DATETIME NULL DEFAULT NULL AFTER `triaged_at`');

        $this->pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function ensureColumn(string $table, string $column, string $alterSql): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute([':table' => $table, ':column' => $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->pdo->exec($alterSql);
        }
    }

    public function run(): void
    {
        $this->ensureSchema();
        $this->pdo->beginTransaction();

        try {
            // 1. Seed Products (`products`)
            $products = [
                'MMF 1.5',
                'DefMan 2004 Core',
                'Katiska Auth Gateway 2.0',
                'Telemetry Engine v4',
            ];

            $productStmt = $this->pdo->prepare(
                'INSERT INTO `products` (`name`) VALUES (:name)
                 ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)'
            );
            foreach ($products as $productName) {
                $productStmt->execute([':name' => $productName]);
            }

            $productMap = [];
            $rows = $this->pdo->query('SELECT `id`, `name` FROM `products`')->fetchAll();
            foreach ($rows as $row) {
                $productMap[$row['name']] = (int) $row['id'];
            }

            // 2. Seed SDLC Phases (`sdlc_phases` — 1NF lookup table)
            $phaseStmt = $this->pdo->prepare(
                'INSERT INTO `sdlc_phases` (`name`) VALUES (:name)
                 ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)'
            );
            foreach (OriginPhase::cases() as $phaseCase) {
                $phaseStmt->execute([':name' => $phaseCase->value]);
            }

            $phaseMap = [];
            $phaseRows = $this->pdo->query('SELECT `id`, `name` FROM `sdlc_phases`')->fetchAll();
            foreach ($phaseRows as $row) {
                $phaseMap[$row['name']] = (int) $row['id'];
            }

            // 3. Seed IEEE Std 1044-2009 Anomaly & Root Cause Classifications (`fault_classifications`)
            $classificationStmt = $this->pdo->prepare(
                'INSERT INTO `fault_classifications` (`code`, `description`)
                 VALUES (:code, :description)
                 ON DUPLICATE KEY UPDATE `description` = VALUES(`description`)'
            );
            foreach (IeeeFaultType::cases() as $faultCase) {
                $subcategories = $faultCase->subcategories();
                $classificationStmt->execute([
                    ':code'        => $faultCase->value,
                    ':description' => $subcategories[0] ?? null,
                ]);
            }

            $classificationMap = [];
            $classRows = $this->pdo->query('SELECT `id`, `code` FROM `fault_classifications`')->fetchAll();
            foreach ($classRows as $row) {
                $classificationMap[$row['code']] = (int) $row['id'];
            }

            // 4. Seed Companies (`companies` — 3NF lookup table) & Users (`users`)
            $users = [
                [
                    'login'     => 'zuck',
                    'password'  => password_hash('Admin#2026!Zuck', PASSWORD_ARGON2ID),
                    'email'     => 'zuckerberg@fb.com',
                    'userlevel' => UserLevel::ADMIN->value,
                    'company'   => 'Meta Platforms',
                    'phone'     => '+1-650-543-4800',
                    'name'      => 'Mark Zuckerberg',
                    'activate'  => 1,
                    'online'    => true,
                ],
                [
                    'login'     => 'billg',
                    'password'  => password_hash('System#2026!Bill', PASSWORD_ARGON2ID),
                    'email'     => 'billg@microsoft.com',
                    'userlevel' => UserLevel::ADMIN->value,
                    'company'   => 'Microsoft Corp',
                    'phone'     => '+1-425-882-8080',
                    'name'      => 'Bill Gates',
                    'activate'  => 1,
                    'online'    => false,
                ],
                [
                    'login'     => 'julia',
                    'password'  => password_hash('Analyze#2026!Julia', PASSWORD_ARGON2ID),
                    'email'     => 'julia@hotmail.com',
                    'userlevel' => UserLevel::ANALYZER->value,
                    'company'   => 'DefMan QA Labs',
                    'phone'     => '+358-40-555-0103',
                    'name'      => 'Julia',
                    'activate'  => 1,
                    'online'    => true,
                ],
                [
                    'login'     => 'sabrin',
                    'password'  => password_hash('Cust#2026!Sabrina', PASSWORD_ARGON2ID),
                    'email'     => 'sabrina@hotmail.com',
                    'userlevel' => UserLevel::CUSTOMER->value,
                    'company'   => 'Nordic Enterprise Oy',
                    'phone'     => '+358-40-555-0102',
                    'name'      => 'Sabrina',
                    'activate'  => 1,
                    'online'    => false,
                ],
            ];

            $companyStmt = $this->pdo->prepare(
                'INSERT INTO `companies` (`name`) VALUES (:name)
                 ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)'
            );

            $userStmt = $this->pdo->prepare(
                'INSERT INTO `users` (`login`, `password`, `email`, `userlevel`, `company_id`, `phone`, `name`, `activate`)
                 VALUES (:login, :password, :email, :userlevel, :company_id, :phone, :name, :activate)
                 ON DUPLICATE KEY UPDATE
                   `id`         = LAST_INSERT_ID(`id`),
                   `password`   = VALUES(`password`),
                   `userlevel`  = VALUES(`userlevel`),
                   `company_id` = VALUES(`company_id`),
                   `name`       = VALUES(`name`),
                   `activate`   = VALUES(`activate`)'
            );

            $sessionStmt = $this->pdo->prepare(
                'INSERT IGNORE INTO `user_sessions` (`user_id`, `token_hash`, `ip_address`, `user_agent`, `expires_at`)
                 VALUES (:user_id, :token_hash, :ip_address, :user_agent, :expires_at)'
            );

            foreach ($users as $u) {
                $companyStmt->execute([':name' => $u['company']]);
                $companyId = (int) $this->pdo->lastInsertId();

                $userStmt->execute([
                    ':login'      => $u['login'],
                    ':password'   => $u['password'],
                    ':email'      => $u['email'],
                    ':userlevel'  => $u['userlevel'],
                    ':company_id' => $companyId,
                    ':phone'      => $u['phone'],
                    ':name'       => $u['name'],
                    ':activate'   => $u['activate'],
                ]);

                $userId = (int) $this->pdo->lastInsertId();

                if ($u['online']) {
                    $sessionStmt->execute([
                        ':user_id'    => $userId,
                        ':token_hash' => hash('sha256', 'seed-session-' . $u['login']),
                        ':ip_address' => '127.0.0.1',
                        ':user_agent' => 'DefMan-2026-Seed-Session/1.0',
                        ':expires_at' => (new \DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s'),
                    ]);
                }
            }

            $sabrinaId = (int) $this->pdo->query(
                "SELECT `id` FROM `users` WHERE `email` = 'sabrina@hotmail.com' LIMIT 1"
            )->fetchColumn();

            // 5. Seed Sample Defects & Analyses with IEEE Std 1044-2009 & DORA Telemetry
            $sampleDefects = [
                [
                    'product_id'           => $productMap['MMF 1.5'],
                    'environment'          => 'AMD Athlon XP 2000, WinXP / Production Desktop Runtime',
                    'detected_environment' => DetectedEnvironment::PROD->value,
                    'symptom_category'     => SymptomCategory::CRASH_HANG->value,
                    'is_regression'        => 1,
                    'reporter_user_id'     => $sabrinaId,
                    'guest_name'           => null,
                    'guest_email'          => null,
                    'reported_at'          => '2026-10-14 08:20:00',
                    'title'                => 'PopUp Menu Cyclic Drag-and-Drop Crash',
                    'description'          => 'When dragging a parent node onto its own child entry in the popup menu tree, the client terminates abruptly without an error dialog.',
                    'discovered_by'        => 'Sabrina',
                    'person_type'          => PersonType::CUSTOMER->value,
                    'occurred_on'          => '2026-10-14',
                    'location'             => 'settings form / popup.cpp',
                    'initial_severity'     => DefectSeverity::CRITICAL->value,
                    'initial_priority'     => DefectPriority::MOST_URGENT->dbValue(),
                    'additional_info'      => 'Regression introduced after tree-reorder patch in v1.5.2.',
                    'analysis'             => [
                        'developer_description'   => 'Cyclic parent-to-child pointer assignment triggers unbounded recursion in popup tree traversal.',
                        'classification_id'       => $classificationMap[IeeeFaultType::CODE_LOGIC->value],
                        'symptom_category'        => SymptomCategory::CRASH_HANG->value,
                        'root_cause_type'         => IeeeFaultType::CODE_LOGIC->value,
                        'detected_environment'    => DetectedEnvironment::PROD->value,
                        'is_regression'           => 1,
                        'introducing_ref'         => 'git:a4f9c21 (build-1502)',
                        'resolution_ref'          => 'git:e83a11f (pr-412 / v1.5.3)',
                        'actual_effort_hours'     => 3.50,
                        'triaged_at'              => '2026-10-14 09:35:00',
                        'resolved_at'             => '2026-10-14 15:50:00',
                        'date_originated'         => '2026-10-10',
                        'phases'                  => [OriginPhase::IMPLEMENTATION->value, OriginPhase::TESTING->value],
                        'activity_originated'     => 'Tree node drag-and-drop refactoring',
                        'deliverable'             => 'settings.cpp, popup.cpp',
                        'what_to_do'              => 'Add ancestor cycle check before committing node reparenting.',
                        'assigned_on'             => '2026-10-14',
                        'uniqueness'              => Uniqueness::CRITICAL->dbValue(),
                        'priority'                => DefectPriority::MOST_URGENT->dbValue(),
                        'severity'                => DefectSeverity::CRITICAL->value,
                        'workaround'              => 'Avoid dragging top-level menu groups into nested submenus.',
                        'proposed_fix'            => 'Guard reparenting call with isDescendantOf() check and unit test.',
                        'status'                  => DefectStatus::RESOLVED->value,
                        'fix_description'         => 'Added ancestor cycle guard and regression test suite for popup tree.',
                        'fix_started_on'          => '2026-10-14',
                        'fix_completed_on'        => '2026-10-14',
                        'fix_impact'              => 'MMF 1.5 settings.cpp, popup.cpp',
                        'fix_effort'              => 3.50,
                        'fix_cost'                => null,
                        'resolution_description'  => 'Verified in staging and deployed hotfix v1.5.3.',
                        'resolution_completed_on' => '2026-10-14',
                        'resolution_closed_on'    => '2026-10-15',
                        'cost_of_defect'          => null,
                        'activity_found'          => 'Customer production telemetry',
                    ],
                ],
                [
                    'product_id'           => $productMap['DefMan 2004 Core'],
                    'environment'          => 'MySQL 8.0 / PHP 8.3 Container Pipeline',
                    'detected_environment' => DetectedEnvironment::CI->value,
                    'symptom_category'     => SymptomCategory::DATA_CORRUPTION->value,
                    'is_regression'        => 0,
                    'reporter_user_id'     => $sabrinaId,
                    'guest_name'           => null,
                    'guest_email'          => null,
                    'reported_at'          => '2026-10-18 09:15:00',
                    'title'                => 'Fractional Effort Hours Truncated in Legacy MyISAM Column',
                    'description'          => 'Fractional engineering effort hours get truncated when stored in legacy integer columns during data migration.',
                    'discovered_by'        => 'Julia',
                    'person_type'          => PersonType::DEVELOPER->value,
                    'occurred_on'          => '2026-10-18',
                    'location'             => 'database/02_migrate_data.sql',
                    'initial_severity'     => DefectSeverity::MAJOR->value,
                    'initial_priority'     => DefectPriority::MOST_URGENT->dbValue(),
                    'additional_info'      => 'Detected by automated migration schema assertion.',
                    'analysis'             => [
                        'developer_description'   => 'Legacy SMALLINT column definition truncates sub-hour DORA effort telemetry.',
                        'classification_id'       => $classificationMap[IeeeFaultType::DATA_SCHEMA->value],
                        'symptom_category'        => SymptomCategory::DATA_CORRUPTION->value,
                        'root_cause_type'         => IeeeFaultType::DATA_SCHEMA->value,
                        'detected_environment'    => DetectedEnvironment::CI->value,
                        'is_regression'           => 0,
                        'introducing_ref'         => 'git:3c08b12 (legacy-schema)',
                        'resolution_ref'          => 'git:7f19d04 (pr-419 / schema-3nf)',
                        'actual_effort_hours'     => 4.25,
                        'triaged_at'              => '2026-10-18 10:00:00',
                        'resolved_at'             => '2026-10-18 18:30:00',
                        'date_originated'         => '2026-10-15',
                        'phases'                  => [OriginPhase::DESIGN->value, OriginPhase::IMPLEMENTATION->value],
                        'activity_originated'     => 'Database schema definition',
                        'deliverable'             => 'database/01_schema_3nf.sql',
                        'what_to_do'              => 'Migrate effort tracking to DECIMAL(8,2) actual_effort_hours.',
                        'assigned_on'             => '2026-10-18',
                        'uniqueness'              => Uniqueness::NEW_DEFECT->dbValue(),
                        'priority'                => DefectPriority::MOST_URGENT->dbValue(),
                        'severity'                => DefectSeverity::MAJOR->value,
                        'workaround'              => 'None required after 3NF DDL migration.',
                        'proposed_fix'            => 'Use DECIMAL(8,2) UNSIGNED for actual_effort_hours in defect_analyses.',
                        'status'                  => DefectStatus::RESOLVED->value,
                        'fix_description'         => 'Migrated schema to InnoDB DECIMAL(8,2) actual_effort_hours.',
                        'fix_started_on'          => '2026-10-18',
                        'fix_completed_on'        => '2026-10-18',
                        'fix_impact'              => '01_schema_3nf.sql, AnalysisRepository.php',
                        'fix_effort'              => 4.25,
                        'fix_cost'                => null,
                        'resolution_description'  => 'CI migration suite verified zero precision loss.',
                        'resolution_completed_on' => '2026-10-18',
                        'resolution_closed_on'    => '2026-10-19',
                        'cost_of_defect'          => null,
                        'activity_found'          => 'CI schema integration pipeline',
                    ],
                ],
                [
                    'product_id'           => $productMap['Katiska Auth Gateway 2.0'],
                    'environment'          => 'Kubernetes Staging Cluster / Envoy 1.31',
                    'detected_environment' => DetectedEnvironment::STAGING->value,
                    'symptom_category'     => SymptomCategory::AUTH_SECURITY->value,
                    'is_regression'        => 0,
                    'reporter_user_id'     => $sabrinaId,
                    'guest_name'           => null,
                    'guest_email'          => null,
                    'reported_at'          => '2026-10-21 11:40:00',
                    'title'                => 'Stale Bearer Session Presence After Tab Closure',
                    'description'          => 'User session remains marked active if browser tab closes without invoking explicit logout endpoint.',
                    'discovered_by'        => 'Sabrina',
                    'person_type'          => PersonType::TESTER->value,
                    'occurred_on'          => '2026-10-21',
                    'location'             => 'AuthController.php / user_sessions',
                    'initial_severity'     => DefectSeverity::MINOR->value,
                    'initial_priority'     => DefectPriority::AVERAGE->dbValue(),
                    'additional_info'      => 'Observed during staging RBAC soak test.',
                    'analysis'             => [
                        'developer_description'   => 'Session query lacked strict expires_at window filter when computing online status.',
                        'classification_id'       => $classificationMap[IeeeFaultType::INTERFACE_INTEGRATION->value],
                        'symptom_category'        => SymptomCategory::AUTH_SECURITY->value,
                        'root_cause_type'         => IeeeFaultType::INTERFACE_INTEGRATION->value,
                        'detected_environment'    => DetectedEnvironment::STAGING->value,
                        'is_regression'           => 0,
                        'introducing_ref'         => 'git:5b82e10 (build-1810)',
                        'resolution_ref'          => 'git:9d04b7c (pr-427 / auth-ttl)',
                        'actual_effort_hours'     => 2.75,
                        'triaged_at'              => '2026-10-21 13:10:00',
                        'resolved_at'             => null,
                        'date_originated'         => '2026-10-12',
                        'phases'                  => [OriginPhase::DESIGN->value, OriginPhase::IMPLEMENTATION->value],
                        'activity_originated'     => 'Session lifecycle implementation',
                        'deliverable'             => 'AuthController.php, UserRepository.php',
                        'what_to_do'              => 'Enforce expires_at > NOW() check and sliding TTL cleanup.',
                        'assigned_on'             => '2026-10-21',
                        'uniqueness'              => Uniqueness::NEW_DEFECT->dbValue(),
                        'priority'                => DefectPriority::AVERAGE->dbValue(),
                        'severity'                => DefectSeverity::MINOR->value,
                        'workaround'              => 'Use explicit Logout action in top menubar.',
                        'proposed_fix'            => 'Prune expired rows in user_sessions and enforce short bearer TTL.',
                        'status'                  => DefectStatus::FIXED->value,
                        'fix_description'         => 'Added indexed expires_at filter and automatic token pruning.',
                        'fix_started_on'          => '2026-10-22',
                        'fix_completed_on'        => '2026-10-22',
                        'fix_impact'              => 'AuthController.php, user_sessions',
                        'fix_effort'              => 2.75,
                        'fix_cost'                => null,
                        'resolution_description'  => 'Awaiting final staging verification.',
                        'resolution_completed_on' => null,
                        'resolution_closed_on'    => null,
                        'cost_of_defect'          => null,
                        'activity_found'          => 'Staging integration testing',
                    ],
                ],
                [
                    'product_id'           => $productMap['Telemetry Engine v4'],
                    'environment'          => 'WebGPU Chrome 130 / Linux Collector Node',
                    'detected_environment' => DetectedEnvironment::PROD->value,
                    'symptom_category'     => SymptomCategory::PERFORMANCE_DEGRADATION->value,
                    'is_regression'        => 1,
                    'reporter_user_id'     => $sabrinaId,
                    'guest_name'           => null,
                    'guest_email'          => null,
                    'reported_at'          => '2026-10-24 14:00:00',
                    'title'                => 'Lock Contention Spike in Concurrent DORA Event Ingestion',
                    'description'          => 'Simultaneous deployment webhook bursts cause 450ms lock wait spikes on telemetry rollup rows.',
                    'discovered_by'        => 'Julia',
                    'person_type'          => PersonType::DEVELOPER->value,
                    'occurred_on'          => '2026-10-24',
                    'location'             => 'TelemetryIngestor.php',
                    'initial_severity'     => DefectSeverity::MAJOR->value,
                    'initial_priority'     => DefectPriority::MOST_URGENT->dbValue(),
                    'additional_info'      => 'Triggered during multi-service release train.',
                    'analysis'             => [
                        'developer_description'   => 'Unbatched row-level upserts contend on shared product counter index.',
                        'classification_id'       => $classificationMap[IeeeFaultType::CONCURRENCY_TIMING->value],
                        'symptom_category'        => SymptomCategory::PERFORMANCE_DEGRADATION->value,
                        'root_cause_type'         => IeeeFaultType::CONCURRENCY_TIMING->value,
                        'detected_environment'    => DetectedEnvironment::PROD->value,
                        'is_regression'           => 1,
                        'introducing_ref'         => 'git:c91d4e8 (v4.1.0-rc2)',
                        'resolution_ref'          => 'git:f042a8b (pr-435 / v4.1.1)',
                        'actual_effort_hours'     => 5.50,
                        'triaged_at'              => '2026-10-24 14:45:00',
                        'resolved_at'             => '2026-10-25 02:15:00',
                        'date_originated'         => '2026-10-20',
                        'phases'                  => [OriginPhase::IMPLEMENTATION->value, OriginPhase::DEPLOYMENT->value],
                        'activity_originated'     => 'Webhook batch consumer refactor',
                        'deliverable'             => 'AnalysisRepository.php',
                        'what_to_do'              => 'Eliminate mutable rollup counters and compute DORA aggregates directly from indexed timestamps.',
                        'assigned_on'             => '2026-10-24',
                        'uniqueness'              => Uniqueness::NEW_DEFECT->dbValue(),
                        'priority'                => DefectPriority::MOST_URGENT->dbValue(),
                        'severity'                => DefectSeverity::MAJOR->value,
                        'workaround'              => 'Serialize webhook workers to concurrency=2.',
                        'proposed_fix'            => 'Use lock-free append-only timestamps with indexed TIMESTAMPDIFF aggregation.',
                        'status'                  => DefectStatus::OPENED->value,
                        'fix_description'         => 'Replaced counter table lock with indexed read aggregation.',
                        'fix_started_on'          => '2026-10-24',
                        'fix_completed_on'        => null,
                        'fix_impact'              => 'AnalysisRepository.php, 01_schema_3nf.sql',
                        'fix_effort'              => 5.50,
                        'fix_cost'                => null,
                        'resolution_description'  => null,
                        'resolution_completed_on' => null,
                        'resolution_closed_on'    => null,
                        'cost_of_defect'          => null,
                        'activity_found'          => 'Production APM latency alert',
                    ],
                ],
            ];

            $reportStmt = $this->pdo->prepare(
                'INSERT INTO `defect_reports`
                 (`product_id`, `environment`, `detected_environment`, `symptom_category`, `is_regression`,
                  `reporter_user_id`, `guest_name`, `guest_email`, `reported_at`,
                  `title`, `description`, `discovered_by`, `person_type`, `occurred_on`, `location`,
                  `initial_severity`, `initial_priority`, `additional_info`)
                 VALUES
                 (:product_id, :environment, :detected_environment, :symptom_category, :is_regression,
                  :reporter_user_id, :guest_name, :guest_email, :reported_at,
                  :title, :description, :discovered_by, :person_type, :occurred_on, :location,
                  :initial_severity, :initial_priority, :additional_info)'
            );

            $analysisStmt = $this->pdo->prepare(
                'INSERT INTO `defect_analyses`
                 (`report_id`, `developer_description`, `classification_id`, `symptom_category`,
                  `root_cause_type`, `detected_environment`, `is_regression`, `introducing_ref`,
                  `resolution_ref`, `actual_effort_hours`, `triaged_at`, `resolved_at`,
                  `date_originated`, `activity_originated`, `deliverable`, `what_to_do`,
                  `assigned_on`, `uniqueness`, `priority`, `severity`, `workaround`,
                  `proposed_fix`, `status`, `fix_description`, `fix_started_on`, `fix_completed_on`,
                  `fix_impact`, `fix_effort`, `fix_cost`, `resolution_description`,
                  `resolution_completed_on`, `resolution_closed_on`, `cost_of_defect`, `activity_found`)
                 VALUES
                 (:report_id, :developer_description, :classification_id, :symptom_category,
                  :root_cause_type, :detected_environment, :is_regression, :introducing_ref,
                  :resolution_ref, :actual_effort_hours, :triaged_at, :resolved_at,
                  :date_originated, :activity_originated, :deliverable, :what_to_do,
                  :assigned_on, :uniqueness, :priority, :severity, :workaround,
                  :proposed_fix, :status, :fix_description, :fix_started_on, :fix_completed_on,
                  :fix_impact, :fix_effort, :fix_cost, :resolution_description,
                  :resolution_completed_on, :resolution_closed_on, :cost_of_defect, :activity_found)'
            );

            $analysisPhaseStmt = $this->pdo->prepare(
                'INSERT IGNORE INTO `analysis_phases` (`report_id`, `phase_id`)
                 VALUES (:report_id, :phase_id)'
            );

            $existingReports = (int) $this->pdo->query('SELECT COUNT(*) FROM `defect_reports`')->fetchColumn();

            if ($existingReports === 0) {
                foreach ($sampleDefects as $defect) {
                    $analysis = $defect['analysis'];
                    unset($defect['analysis']);

                    $reportStmt->execute([
                        ':product_id'           => $defect['product_id'],
                        ':environment'          => $defect['environment'],
                        ':detected_environment' => $defect['detected_environment'],
                        ':symptom_category'     => $defect['symptom_category'],
                        ':is_regression'        => $defect['is_regression'],
                        ':reporter_user_id'     => $defect['reporter_user_id'],
                        ':guest_name'           => $defect['guest_name'],
                        ':guest_email'          => $defect['guest_email'],
                        ':reported_at'          => $defect['reported_at'],
                        ':title'                => $defect['title'],
                        ':description'          => $defect['description'],
                        ':discovered_by'        => $defect['discovered_by'],
                        ':person_type'          => $defect['person_type'],
                        ':occurred_on'          => $defect['occurred_on'],
                        ':location'             => $defect['location'],
                        ':initial_severity'     => $defect['initial_severity'],
                        ':initial_priority'     => $defect['initial_priority'],
                        ':additional_info'      => $defect['additional_info'],
                    ]);

                    $reportId = (int) $this->pdo->lastInsertId();

                    $analysisStmt->execute([
                        ':report_id'               => $reportId,
                        ':developer_description'   => $analysis['developer_description'],
                        ':classification_id'       => $analysis['classification_id'],
                        ':symptom_category'        => $analysis['symptom_category'],
                        ':root_cause_type'         => $analysis['root_cause_type'],
                        ':detected_environment'    => $analysis['detected_environment'],
                        ':is_regression'           => $analysis['is_regression'],
                        ':introducing_ref'         => $analysis['introducing_ref'],
                        ':resolution_ref'          => $analysis['resolution_ref'],
                        ':actual_effort_hours'     => $analysis['actual_effort_hours'],
                        ':triaged_at'              => $analysis['triaged_at'],
                        ':resolved_at'             => $analysis['resolved_at'],
                        ':date_originated'         => $analysis['date_originated'],
                        ':activity_originated'     => $analysis['activity_originated'],
                        ':deliverable'             => $analysis['deliverable'],
                        ':what_to_do'              => $analysis['what_to_do'],
                        ':assigned_on'             => $analysis['assigned_on'],
                        ':uniqueness'              => $analysis['uniqueness'],
                        ':priority'                => $analysis['priority'],
                        ':severity'                => $analysis['severity'],
                        ':workaround'              => $analysis['workaround'],
                        ':proposed_fix'            => $analysis['proposed_fix'],
                        ':status'                  => $analysis['status'],
                        ':fix_description'         => $analysis['fix_description'],
                        ':fix_started_on'          => $analysis['fix_started_on'],
                        ':fix_completed_on'        => $analysis['fix_completed_on'],
                        ':fix_impact'              => $analysis['fix_impact'],
                        ':fix_effort'              => $analysis['fix_effort'],
                        ':fix_cost'                => $analysis['fix_cost'],
                        ':resolution_description'  => $analysis['resolution_description'],
                        ':resolution_completed_on' => $analysis['resolution_completed_on'],
                        ':resolution_closed_on'    => $analysis['resolution_closed_on'],
                        ':cost_of_defect'          => $analysis['cost_of_defect'],
                        ':activity_found'          => $analysis['activity_found'],
                    ]);

                    foreach ($analysis['phases'] as $phaseName) {
                        if (isset($phaseMap[$phaseName])) {
                            $analysisPhaseStmt->execute([
                                ':report_id' => $reportId,
                                ':phase_id'  => $phaseMap[$phaseName],
                            ]);
                        }
                    }
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
