<?php
declare(strict_types=1);

namespace DefMan\Database;

use DefMan\Domain\Enum\DefectPriority;
use DefMan\Domain\Enum\DefectSeverity;
use DefMan\Domain\Enum\DefectStatus;
use DefMan\Domain\Enum\IeeeFaultType;
use DefMan\Domain\Enum\OriginPhase;
use DefMan\Domain\Enum\PersonType;
use DefMan\Domain\Enum\Uniqueness;
use DefMan\Domain\Enum\UserLevel;
use PDO;

/**
 * PDO Database Seeder (3NF Normalized Schema)
 * Refactored from: install/installdb.php, install/install-part2.php, helpanalyze.html, helpreport.html
 *
 * Test/Seed Account Rule:
 * - Admin / System placeholders: zuckerberg@fb.com, billg@microsoft.com
 * - Customer / Analyzer accounts: Sabrina (sabrina@hotmail.com), Julia (julia@hotmail.com)
 */
final readonly class DatabaseSeeder
{
    public function __construct(private PDO $pdo) {}

    public function run(): void
    {
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

            // 3. Seed IEEE 1044-1992 Fault Classifications (`fault_classifications` — 3NF lookup table)
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

            // 5. Seed Sample Defects & Analyses (`defect_reports`, `defect_analyses`, `analysis_phases`)
            $sampleDefects = [
                [
                    'product_id'       => $productMap['MMF 1.5'],
                    'environment'      => 'AMD Athlon XP 2000, WinXP',
                    'reporter_user_id' => $sabrinaId,
                    'guest_name'       => null,
                    'guest_email'      => null,
                    'reported_at'      => '2004-04-14 14:20:00',
                    'title'            => 'PopUp Menu Crash',
                    'description'      => 'When adding entries into the popup menu in a certain way, the application will close without any error messages.',
                    'discovered_by'    => 'Sabrina',
                    'person_type'      => PersonType::CUSTOMER->value,
                    'occurred_on'      => '2004-04-14',
                    'location'         => 'settings form',
                    'initial_severity' => DefectSeverity::CRITICAL->value,
                    'initial_priority' => DefectPriority::MOST_URGENT->dbValue(),
                    'additional_info'  => 'please fix this soon',
                    'analysis'         => [
                        'developer_description'   => 'It seems to be happening when you drag a parent item and drop it to its children.',
                        'classification_id'       => $classificationMap[IeeeFaultType::LOGIC->value],
                        'date_originated'         => '2004-04-16',
                        'phases'                  => [OriginPhase::IMPLEMENTATION->value, OriginPhase::TESTING->value],
                        'activity_originated'     => 'Testing',
                        'deliverable'             => 'the pop-up menu settings form',
                        'what_to_do'              => 'Prevent cyclic parent-to-child drag-and-drop in popup tree.',
                        'assigned_on'             => '2004-04-17',
                        'uniqueness'              => Uniqueness::CRITICAL->dbValue(),
                        'priority'                => DefectPriority::MOST_URGENT->dbValue(),
                        'severity'                => DefectSeverity::CRITICAL->value,
                        'workaround'              => 'Do not drag and drop.',
                        'proposed_fix'            => "Let's fix the pop-up menu module.",
                        'status'                  => DefectStatus::RESOLVED->value,
                        'fix_description'         => 'Fixed the pop-up menu function call.',
                        'fix_started_on'          => '2004-04-19',
                        'fix_completed_on'        => '2004-04-20',
                        'fix_impact'              => 'MMF 1.5 settings.cpp popup.cpp',
                        'fix_effort'              => 2.00,
                        'fix_cost'                => 60.00,
                        'resolution_description'  => 'The pop-up menu is fixed.',
                        'resolution_completed_on' => '2004-04-22',
                        'resolution_closed_on'    => '2004-04-23',
                        'cost_of_defect'          => 240.00,
                        'activity_found'          => 'sequential testing',
                    ],
                ],
                [
                    'product_id'       => $productMap['DefMan 2004 Core'],
                    'environment'      => 'Linux 2.4, Apache 1.3, PHP 4.3, MySQL 4.0',
                    'reporter_user_id' => $sabrinaId,
                    'guest_name'       => null,
                    'guest_email'      => null,
                    'reported_at'      => '2004-04-18 09:15:00',
                    'title'            => 'Precision Loss in EUR Effort Multiplier',
                    'description'      => 'Fractional effort hours get truncated when stored in smallint(6) fixeffort and fixcost columns.',
                    'discovered_by'    => 'Julia',
                    'person_type'      => PersonType::DEVELOPER->value,
                    'occurred_on'      => '2004-04-18',
                    'location'         => 'form_deffix.php',
                    'initial_severity' => DefectSeverity::MAJOR->value,
                    'initial_priority' => DefectPriority::MOST_URGENT->dbValue(),
                    'additional_info'  => 'Affects IEEE cost reporting accuracy.',
                    'analysis'         => [
                        'developer_description'   => 'Integer truncation in legacy MyISAM schema causes rounding fault.',
                        'classification_id'       => $classificationMap[IeeeFaultType::COMPUTATIONAL->value],
                        'date_originated'         => '2004-04-10',
                        'phases'                  => [OriginPhase::DESIGN->value],
                        'activity_originated'     => 'Database schema definition',
                        'deliverable'             => 'install/installdb.php',
                        'what_to_do'              => 'Migrate fixeffort and fixcost to DECIMAL(8,2) / DECIMAL(10,2).',
                        'assigned_on'             => '2004-04-19',
                        'uniqueness'              => Uniqueness::NEW_DEFECT->dbValue(),
                        'priority'                => DefectPriority::MOST_URGENT->dbValue(),
                        'severity'                => DefectSeverity::MAJOR->value,
                        'workaround'              => 'Round effort to nearest whole hour.',
                        'proposed_fix'            => 'Alter table columns to DECIMAL in InnoDB.',
                        'status'                  => DefectStatus::OPENED->value,
                        'fix_description'         => 'Migrated schema to MySQL 8 InnoDB DECIMAL columns.',
                        'fix_started_on'          => '2004-04-20',
                        'fix_completed_on'        => null,
                        'fix_impact'              => 'schema.sql, form_deffix.php',
                        'fix_effort'              => 6.50,
                        'fix_cost'                => 195.00,
                        'resolution_description'  => null,
                        'resolution_completed_on' => null,
                        'resolution_closed_on'    => null,
                        'cost_of_defect'          => 420.00,
                        'activity_found'          => 'schema review',
                    ],
                ],
                [
                    'product_id'       => $productMap['Katiska Auth Gateway 2.0'],
                    'environment'      => 'Netscape 7.1 / Solaris X-Session',
                    'reporter_user_id' => $sabrinaId,
                    'guest_name'       => null,
                    'guest_email'      => null,
                    'reported_at'      => '2004-04-21 11:40:00',
                    'title'            => 'Subroutine Mismatch in Session Logout Redirect',
                    'description'      => 'Session state flag logged=1 remains active if user closes browser tab without hitting logout.php.',
                    'discovered_by'    => 'Sabrina',
                    'person_type'      => PersonType::TESTER->value,
                    'occurred_on'      => '2004-04-21',
                    'location'         => 'login.php / logout.php',
                    'initial_severity' => DefectSeverity::MINOR->value,
                    'initial_priority' => DefectPriority::AVERAGE->dbValue(),
                    'additional_info'  => 'User management shows stale Online badge.',
                    'analysis'         => [
                        'developer_description'   => 'Missing session expiration heartbeat in authentication interface.',
                        'classification_id'       => $classificationMap[IeeeFaultType::INTERFACE->value],
                        'date_originated'         => '2004-04-12',
                        'phases'                  => [OriginPhase::DESIGN->value, OriginPhase::IMPLEMENTATION->value],
                        'activity_originated'     => 'Session handling implementation',
                        'deliverable'             => 'login.php, logout.php',
                        'what_to_do'              => 'Introduce expiring bearer sessions in user_sessions table.',
                        'assigned_on'             => '2004-04-22',
                        'uniqueness'              => Uniqueness::NEW_DEFECT->dbValue(),
                        'priority'                => DefectPriority::AVERAGE->dbValue(),
                        'severity'                => DefectSeverity::MINOR->value,
                        'workaround'              => 'Manually log out via menubar.',
                        'proposed_fix'            => 'Use token expiration and session TTL.',
                        'status'                  => DefectStatus::FIXED->value,
                        'fix_description'         => 'Implemented token TTL and automatic online presence check.',
                        'fix_started_on'          => '2004-04-23',
                        'fix_completed_on'        => '2004-04-24',
                        'fix_impact'              => 'AuthController.php, user_sessions',
                        'fix_effort'              => 4.00,
                        'fix_cost'                => 120.00,
                        'resolution_description'  => 'Awaiting final QA sign-off.',
                        'resolution_completed_on' => null,
                        'resolution_closed_on'    => null,
                        'cost_of_defect'          => 180.00,
                        'activity_found'          => 'integration testing',
                    ],
                ],
            ];

            $reportStmt = $this->pdo->prepare(
                'INSERT INTO `defect_reports`
                 (`product_id`, `environment`, `reporter_user_id`, `guest_name`, `guest_email`, `reported_at`,
                  `title`, `description`, `discovered_by`, `person_type`, `occurred_on`, `location`,
                  `initial_severity`, `initial_priority`, `additional_info`)
                 VALUES
                 (:product_id, :environment, :reporter_user_id, :guest_name, :guest_email, :reported_at,
                  :title, :description, :discovered_by, :person_type, :occurred_on, :location,
                  :initial_severity, :initial_priority, :additional_info)'
            );

            $analysisStmt = $this->pdo->prepare(
                'INSERT INTO `defect_analyses`
                 (`report_id`, `developer_description`, `classification_id`, `date_originated`,
                  `activity_originated`, `deliverable`, `what_to_do`, `assigned_on`, `uniqueness`,
                  `priority`, `severity`, `workaround`, `proposed_fix`, `status`, `fix_description`,
                  `fix_started_on`, `fix_completed_on`, `fix_impact`, `fix_effort`, `fix_cost`,
                  `resolution_description`, `resolution_completed_on`, `resolution_closed_on`,
                  `cost_of_defect`, `activity_found`)
                 VALUES
                 (:report_id, :developer_description, :classification_id, :date_originated,
                  :activity_originated, :deliverable, :what_to_do, :assigned_on, :uniqueness,
                  :priority, :severity, :workaround, :proposed_fix, :status, :fix_description,
                  :fix_started_on, :fix_completed_on, :fix_impact, :fix_effort, :fix_cost,
                  :resolution_description, :resolution_completed_on, :resolution_closed_on,
                  :cost_of_defect, :activity_found)'
            );

            $analysisPhaseStmt = $this->pdo->prepare(
                'INSERT IGNORE INTO `analysis_phases` (`report_id`, `phase_id`)
                 VALUES (:report_id, :phase_id)'
            );

            foreach ($sampleDefects as $defect) {
                $analysis = $defect['analysis'];
                unset($defect['analysis']);

                $reportStmt->execute([
                    ':product_id'       => $defect['product_id'],
                    ':environment'      => $defect['environment'],
                    ':reporter_user_id' => $defect['reporter_user_id'],
                    ':guest_name'       => $defect['guest_name'],
                    ':guest_email'      => $defect['guest_email'],
                    ':reported_at'      => $defect['reported_at'],
                    ':title'            => $defect['title'],
                    ':description'      => $defect['description'],
                    ':discovered_by'    => $defect['discovered_by'],
                    ':person_type'      => $defect['person_type'],
                    ':occurred_on'      => $defect['occurred_on'],
                    ':location'         => $defect['location'],
                    ':initial_severity' => $defect['initial_severity'],
                    ':initial_priority' => $defect['initial_priority'],
                    ':additional_info'  => $defect['additional_info'],
                ]);

                $reportId = (int) $this->pdo->lastInsertId();

                $analysisStmt->execute([
                    ':report_id'               => $reportId,
                    ':developer_description'   => $analysis['developer_description'],
                    ':classification_id'       => $analysis['classification_id'],
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

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
