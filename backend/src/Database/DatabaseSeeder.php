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
 * PDO Database Seeder
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
            // 1. Seed Products (including historical MMF 1.5 from helpanalyze.html)
            $products = [
                'MMF 1.5',
                'DefMan 2004 Core',
                'Katiska Auth Gateway 2.0',
                'Telemetry Engine v4',
            ];

            $productStmt = $this->pdo->prepare(
                'INSERT INTO `product` (`name`) VALUES (:name)
                 ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)'
            );
            foreach ($products as $productName) {
                $productStmt->execute([':name' => $productName]);
            }

            // Map product names to IDs
            $productMap = [];
            $rows = $this->pdo->query('SELECT `id`, `name` FROM `product`')->fetchAll();
            foreach ($rows as $row) {
                $productMap[$row['name']] = (int) $row['id'];
            }

            // 2. Seed Users with Argon2id hashes
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
                    'logged'    => 1,
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
                    'logged'    => 0,
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
                    'logged'    => 1,
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
                    'logged'    => 0,
                ],
            ];

            $userStmt = $this->pdo->prepare(
                'INSERT INTO `users` (`login`, `password`, `email`, `userlevel`, `company`, `phone`, `name`, `activate`, `logged`)
                 VALUES (:login, :password, :email, :userlevel, :company, :phone, :name, :activate, :logged)
                 ON DUPLICATE KEY UPDATE
                   `userlevel` = VALUES(`userlevel`),
                   `company`   = VALUES(`company`),
                   `name`      = VALUES(`name`),
                   `activate`  = VALUES(`activate`)'
            );

            foreach ($users as $u) {
                $userStmt->execute([
                    ':login'     => $u['login'],
                    ':password'  => $u['password'],
                    ':email'     => $u['email'],
                    ':userlevel' => $u['userlevel'],
                    ':company'   => $u['company'],
                    ':phone'     => $u['phone'],
                    ':name'      => $u['name'],
                    ':activate'  => $u['activate'],
                    ':logged'    => $u['logged'],
                ]);
            }

            $sabrinaId = (int) $this->pdo->query(
                "SELECT `id` FROM `users` WHERE `email` = 'sabrina@hotmail.com' LIMIT 1"
            )->fetchColumn();

            // 3. Seed Sample Defects & Analyses (preserving helpanalyze.html & helpreport.html canon)
            $sampleDefects = [
                [
                    'Product'      => 'MMF 1.5',
                    'product_id'   => $productMap['MMF 1.5'] ?? null,
                    'Environment'  => 'AMD Athlon XP 2000, WinXP',
                    'CustomerID'   => $sabrinaId,
                    'Who'          => 'sabrin',
                    'Contactemail' => 'sabrina@hotmail.com',
                    'Whenreported' => '2004-04-14 14:20',
                    'Title'        => 'PopUp Menu Crash',
                    'Description'  => 'When adding entries into the popup menu in a certain way, the application will close without any error messages.',
                    'Discoveredby' => 'Sabrina',
                    'Persontype'   => PersonType::CUSTOMER->value,
                    'Occured'      => '2004-04-14',
                    'Location'     => 'settings form',
                    'Severity'     => DefectSeverity::CRITICAL->value,
                    'Priority'     => DefectPriority::MOST_URGENT->value,
                    'Submitted'    => '2004-04-14',
                    'Addinfo'      => 'please fix this soon',
                    'analysis'     => [
                        'devdesc'        => 'It seems to be happening when you drag a parent item and drop it to its children.',
                        'classification' => IeeeFaultType::LOGIC->value,
                        'dateoriginated' => '2004-04-16',
                        'phase'          => implode(',', [OriginPhase::IMPLEMENTATION->value, OriginPhase::TESTING->value]),
                        'actoriginated'  => 'Testing',
                        'deliverable'    => 'the pop-up menu settings form',
                        'whattodo'       => 'Prevent cyclic parent-to-child drag-and-drop in popup tree.',
                        'submitted'      => '2004-04-14',
                        'assigned'       => '2004-04-17',
                        'uniqueness'     => Uniqueness::CRITICAL->value,
                        'priority'       => DefectPriority::MOST_URGENT->analysisLabel(),
                        'severity'       => DefectSeverity::CRITICAL->value,
                        'workaround'     => 'Do not drag and drop.',
                        'proposedfix'    => "Let's fix the pop-up menu module.",
                        'status'         => DefectStatus::RESOLVED->value,
                        'fixdesc'        => 'Fixed the pop-up menu function call.',
                        'fixstarted'     => '2004-04-19',
                        'fixcomplete'    => '2004-04-20',
                        'fiximpact'      => 'MMF 1.5 settings.cpp popup.cpp',
                        'fixeffort'      => 2.00,
                        'fixcost'        => 60.00,
                        'resodesc'       => 'The pop-up menu is fixed.',
                        'resocomplete'   => '2004-04-22',
                        'resoclosed'     => '2004-04-23',
                        'costofdefect'   => 240.00,
                        'actfound'       => 'sequential testing',
                    ],
                ],
                [
                    'Product'      => 'DefMan 2004 Core',
                    'product_id'   => $productMap['DefMan 2004 Core'] ?? null,
                    'Environment'  => 'Linux 2.4, Apache 1.3, PHP 4.3, MySQL 4.0',
                    'CustomerID'   => $sabrinaId,
                    'Who'          => 'sabrin',
                    'Contactemail' => 'sabrina@hotmail.com',
                    'Whenreported' => '2004-04-18 09:15',
                    'Title'        => 'Precision Loss in EUR Effort Multiplier',
                    'Description'  => 'Fractional effort hours get truncated when stored in smallint(6) fixeffort and fixcost columns.',
                    'Discoveredby' => 'Julia',
                    'Persontype'   => PersonType::DEVELOPER->value,
                    'Occured'      => '2004-04-18',
                    'Location'     => 'form_deffix.php',
                    'Severity'     => DefectSeverity::MAJOR->value,
                    'Priority'     => DefectPriority::MOST_URGENT->value,
                    'Submitted'    => '2004-04-18',
                    'Addinfo'      => 'Affects IEEE cost reporting accuracy.',
                    'analysis'     => [
                        'devdesc'        => 'Integer truncation in legacy MyISAM schema causes rounding fault.',
                        'classification' => IeeeFaultType::COMPUTATIONAL->value,
                        'dateoriginated' => '2004-04-10',
                        'phase'          => OriginPhase::DESIGN->value,
                        'actoriginated'  => 'Database schema definition',
                        'deliverable'    => 'install/installdb.php',
                        'whattodo'       => 'Migrate fixeffort and fixcost to DECIMAL(8,2) / DECIMAL(10,2).',
                        'submitted'      => '2004-04-18',
                        'assigned'       => '2004-04-19',
                        'uniqueness'     => Uniqueness::NEW_DEFECT->value,
                        'priority'       => DefectPriority::MOST_URGENT->analysisLabel(),
                        'severity'       => DefectSeverity::MAJOR->value,
                        'workaround'     => 'Round effort to nearest whole hour.',
                        'proposedfix'    => 'Alter table columns to DECIMAL in InnoDB.',
                        'status'         => DefectStatus::OPENED->value,
                        'fixdesc'        => 'Migrated schema to MySQL 8 InnoDB DECIMAL columns.',
                        'fixstarted'     => '2004-04-20',
                        'fixcomplete'    => null,
                        'fiximpact'      => 'schema.sql, form_deffix.php',
                        'fixeffort'      => 6.50,
                        'fixcost'        => 195.00,
                        'resodesc'       => null,
                        'resocomplete'   => null,
                        'resoclosed'     => null,
                        'costofdefect'   => 420.00,
                        'actfound'       => 'schema review',
                    ],
                ],
                [
                    'Product'      => 'Katiska Auth Gateway 2.0',
                    'product_id'   => $productMap['Katiska Auth Gateway 2.0'] ?? null,
                    'Environment'  => 'Netscape 7.1 / Solaris X-Session',
                    'CustomerID'   => $sabrinaId,
                    'Who'          => 'sabrin',
                    'Contactemail' => 'sabrina@hotmail.com',
                    'Whenreported' => '2004-04-21 11:40',
                    'Title'        => 'Subroutine Mismatch in Session Logout Redirect',
                    'Description'  => 'Session state flag logged=1 remains active if user closes browser tab without hitting logout.php.',
                    'Discoveredby' => 'Sabrina',
                    'Persontype'   => PersonType::TESTER->value,
                    'Occured'      => '2004-04-21',
                    'Location'     => 'login.php / logout.php',
                    'Severity'     => DefectSeverity::MINOR->value,
                    'Priority'     => DefectPriority::AVERAGE->value,
                    'Submitted'    => '2004-04-21',
                    'Addinfo'      => 'User management shows stale Online badge.',
                    'analysis'     => [
                        'devdesc'        => 'Missing session expiration heartbeat in authentication interface.',
                        'classification' => IeeeFaultType::INTERFACE->value,
                        'dateoriginated' => '2004-04-12',
                        'phase'          => implode(',', [OriginPhase::DESIGN->value, OriginPhase::IMPLEMENTATION->value]),
                        'actoriginated'  => 'Session handling implementation',
                        'deliverable'    => 'login.php, logout.php',
                        'whattodo'       => 'Introduce expiring bearer sessions in user_sessions table.',
                        'submitted'      => '2004-04-21',
                        'assigned'       => '2004-04-22',
                        'uniqueness'     => Uniqueness::NEW_DEFECT->value,
                        'priority'       => DefectPriority::AVERAGE->analysisLabel(),
                        'severity'       => DefectSeverity::MINOR->value,
                        'workaround'     => 'Manually log out via menubar.',
                        'proposedfix'    => 'Use token expiration and session TTL.',
                        'status'         => DefectStatus::FIXED->value,
                        'fixdesc'        => 'Implemented token TTL and automatic online presence check.',
                        'fixstarted'     => '2004-04-23',
                        'fixcomplete'    => '2004-04-24',
                        'fiximpact'      => 'AuthController.php, user_sessions',
                        'fixeffort'      => 4.00,
                        'fixcost'        => 120.00,
                        'resodesc'       => 'Awaiting final QA sign-off.',
                        'resocomplete'   => null,
                        'resoclosed'     => null,
                        'costofdefect'   => 180.00,
                        'actfound'       => 'integration testing',
                    ],
                ],
            ];

            $raportStmt = $this->pdo->prepare(
                'INSERT INTO `raport`
                 (`Product`, `product_id`, `Environment`, `CustomerID`, `Who`, `Contactemail`, `Whenreported`,
                  `Title`, `Description`, `Discoveredby`, `Persontype`, `Occured`, `Location`,
                  `Severity`, `Priority`, `Submitted`, `Addinfo`)
                 VALUES
                 (:Product, :product_id, :Environment, :CustomerID, :Who, :Contactemail, :Whenreported,
                  :Title, :Description, :Discoveredby, :Persontype, :Occured, :Location,
                  :Severity, :Priority, :Submitted, :Addinfo)'
            );

            $analyseStmt = $this->pdo->prepare(
                'INSERT INTO `analyse`
                 (`id`, `devdesc`, `classification`, `dateoriginated`, `phase`, `actoriginated`,
                  `deliverable`, `whattodo`, `submitted`, `assigned`, `uniqueness`, `priority`,
                  `severity`, `workaround`, `proposedfix`, `status`, `fixdesc`, `fixstarted`,
                  `fixcomplete`, `fiximpact`, `fixeffort`, `fixcost`, `resodesc`, `resocomplete`,
                  `resoclosed`, `costofdefect`, `actfound`)
                 VALUES
                 (:id, :devdesc, :classification, :dateoriginated, :phase, :actoriginated,
                  :deliverable, :whattodo, :submitted, :assigned, :uniqueness, :priority,
                  :severity, :workaround, :proposedfix, :status, :fixdesc, :fixstarted,
                  :fixcomplete, :fiximpact, :fixeffort, :fixcost, :resodesc, :resocomplete,
                  :resoclosed, :costofdefect, :actfound)'
            );

            foreach ($sampleDefects as $defect) {
                $analysis = $defect['analysis'];
                unset($defect['analysis']);

                $raportStmt->execute([
                    ':Product'      => $defect['Product'],
                    ':product_id'   => $defect['product_id'],
                    ':Environment'  => $defect['Environment'],
                    ':CustomerID'   => $defect['CustomerID'],
                    ':Who'          => $defect['Who'],
                    ':Contactemail' => $defect['Contactemail'],
                    ':Whenreported' => $defect['Whenreported'],
                    ':Title'        => $defect['Title'],
                    ':Description'  => $defect['Description'],
                    ':Discoveredby' => $defect['Discoveredby'],
                    ':Persontype'   => $defect['Persontype'],
                    ':Occured'      => $defect['Occured'],
                    ':Location'     => $defect['Location'],
                    ':Severity'     => $defect['Severity'],
                    ':Priority'     => $defect['Priority'],
                    ':Submitted'    => $defect['Submitted'],
                    ':Addinfo'      => $defect['Addinfo'],
                ]);

                $defectId = (int) $this->pdo->lastInsertId();

                $analyseStmt->execute([
                    ':id'             => $defectId,
                    ':devdesc'        => $analysis['devdesc'],
                    ':classification' => $analysis['classification'],
                    ':dateoriginated' => $analysis['dateoriginated'],
                    ':phase'          => $analysis['phase'],
                    ':actoriginated'  => $analysis['actoriginated'],
                    ':deliverable'    => $analysis['deliverable'],
                    ':whattodo'       => $analysis['whattodo'],
                    ':submitted'      => $analysis['submitted'],
                    ':assigned'       => $analysis['assigned'],
                    ':uniqueness'     => $analysis['uniqueness'],
                    ':priority'       => $analysis['priority'],
                    ':severity'       => $analysis['severity'],
                    ':workaround'     => $analysis['workaround'],
                    ':proposedfix'    => $analysis['proposedfix'],
                    ':status'         => $analysis['status'],
                    ':fixdesc'        => $analysis['fixdesc'],
                    ':fixstarted'     => $analysis['fixstarted'],
                    ':fixcomplete'    => $analysis['fixcomplete'],
                    ':fiximpact'      => $analysis['fiximpact'],
                    ':fixeffort'      => $analysis['fixeffort'],
                    ':fixcost'        => $analysis['fixcost'],
                    ':resodesc'       => $analysis['resodesc'],
                    ':resocomplete'   => $analysis['resocomplete'],
                    ':resoclosed'     => $analysis['resoclosed'],
                    ':costofdefect'   => $analysis['costofdefect'],
                    ':actfound'       => $analysis['actfound'],
                ]);
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
