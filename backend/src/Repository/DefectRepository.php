<?php
declare(strict_types=1);

namespace DefMan\Repository;

use DefMan\Domain\Enum\DefectPriority;
use DefMan\Domain\Enum\DefectSeverity;
use DefMan\Domain\Enum\PersonType;
use PDO;

/**
 * Defect Report Repository (`defect_reports` + 3NF joins)
 * Refactored from: form_raport.php, form_raportpub.php, form_raportlist.php,
 *                  form_list.php, form_defrap.php, db.php (deleteraport)
 */
final readonly class DefectRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Resolves a product name to `products.id` (inserting if not yet cataloged)
     */
    private function resolveProductId(string $productName): int
    {
        $trimmed = trim(strip_tags($productName));
        if ($trimmed === '') {
            $trimmed = 'Unknown Product';
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `products` (`name`) VALUES (:name)
             ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)'
        );
        $stmt->execute([':name' => $trimmed]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Multi-column sorting & filtering by severity, priority, status, product, and customer.
     * Also computes `days_since_submitted` and deleted-user badge per form_raportlist.php.
     */
    public function findFiltered(
        ?string $severity = null,
        ?string $priority = null,
        ?string $status = null,
        ?string $product = null,
        ?int $customerId = null,
        string $sortBy = 'date'
    ): array {
        $conditions = [];
        $params     = [];

        if ($severity !== null && $severity !== '') {
            $conditions[]        = 'dr.`initial_severity` = :severity';
            $params[':severity'] = $severity;
        }

        if ($priority !== null && $priority !== '') {
            $normalizedPriority  = DefectPriority::fromInput($priority)->dbValue();
            $conditions[]        = 'dr.`initial_priority` = :priority';
            $params[':priority'] = $normalizedPriority;
        }

        if ($status !== null && $status !== '') {
            if ($status === 'unassigned') {
                $conditions[] = 'da.`status` IS NULL';
            } else {
                $conditions[]      = 'da.`status` = :status';
                $params[':status'] = $status;
            }
        }

        if ($product !== null && $product !== '') {
            $conditions[]       = 'p.`name` = :product';
            $params[':product'] = $product;
        }

        if ($customerId !== null) {
            $conditions[]           = 'dr.`reporter_user_id` = :customer_id';
            $params[':customer_id'] = $customerId;
        }

        $whereSql = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $orderBySql = match ($sortBy) {
            'product'  => 'p.`name` ASC, dr.`id` DESC',
            'contact'  => "COALESCE(u.`login`, dr.`guest_name`, '') DESC, dr.`id` DESC",
            'severity' => "FIELD(dr.`initial_severity`, 'critical', 'major', 'minor') ASC, dr.`reported_at` DESC",
            'priority', 'all' => "FIELD(dr.`initial_priority`, 'most_urgent', 'average', 'less_urgent') ASC, dr.`reported_at` DESC",
            default    => 'dr.`reported_at` DESC, dr.`id` DESC',
        };

        $sql = "
            SELECT
                dr.`id` AS `ID`,
                p.`name` AS `Product`,
                dr.`product_id`,
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
                    WHEN 'less_urgent' THEN 'l_urgent'
                    ELSE 'average'
                END AS `Priority`,
                CASE dr.`initial_priority`
                    WHEN 'most_urgent' THEN 'most urgent'
                    WHEN 'less_urgent' THEN 'less urgent'
                    ELSE 'average'
                END AS `PriorityLabel`,
                DATE(dr.`reported_at`) AS `Submitted`,
                DATEDIFF(CURDATE(), DATE(dr.`reported_at`)) AS `days_since_submitted`,
                dr.`additional_info` AS `Addinfo`,
                CASE
                    WHEN dr.`reporter_user_id` IS NULL AND dr.`person_type` = 'Customer' AND dr.`guest_name` IS NOT NULL
                         AND NOT EXISTS (SELECT 1 FROM `users` u2 WHERE u2.`login` = dr.`guest_name`)
                    THEN 0
                    WHEN dr.`reporter_user_id` IS NOT NULL AND u.`id` IS NULL
                    THEN 1
                    ELSE 0
                END AS `is_deleted_user`,
                fc.`code` AS `classification`,
                COALESCE(da.`status`, 'submitted') AS `analysis_status`,
                da.`severity` AS `developer_severity`,
                CASE da.`priority`
                    WHEN 'most_urgent' THEN 'most urgent'
                    WHEN 'less_urgent' THEN 'less urgent'
                    WHEN 'average'     THEN 'average'
                    ELSE NULL
                END AS `developer_priority`,
                da.`fix_effort` AS `fixeffort`,
                da.`fix_cost` AS `fixcost`,
                da.`cost_of_defect` AS `costofdefect`,
                da.`assigned_on` AS `assigned`,
                da.`fix_completed_on` AS `fixcomplete`,
                da.`resolution_completed_on` AS `resocomplete`,
                da.`resolution_closed_on` AS `resoclosed`
            FROM `defect_reports` dr
            INNER JOIN `products` p ON p.`id` = dr.`product_id`
            LEFT JOIN `users` u ON u.`id` = dr.`reporter_user_id`
            LEFT JOIN `defect_analyses` da ON da.`report_id` = dr.`id`
            LEFT JOIN `fault_classifications` fc ON fc.`id` = da.`classification_id`
            {$whereSql}
            ORDER BY {$orderBySql}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                dr.`id` AS `ID`,
                p.`name` AS `Product`,
                dr.`product_id`,
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
                    WHEN 'less_urgent' THEN 'l_urgent'
                    ELSE 'average'
                END AS `Priority`,
                CASE dr.`initial_priority`
                    WHEN 'most_urgent' THEN 'most urgent'
                    WHEN 'less_urgent' THEN 'less urgent'
                    ELSE 'average'
                END AS `PriorityLabel`,
                DATE(dr.`reported_at`) AS `Submitted`,
                DATEDIFF(CURDATE(), DATE(dr.`reported_at`)) AS `days_since_submitted`,
                dr.`additional_info` AS `Addinfo`
             FROM `defect_reports` dr
             INNER JOIN `products` p ON p.`id` = dr.`product_id`
             LEFT JOIN `users` u ON u.`id` = dr.`reporter_user_id`
             WHERE dr.`id` = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $report = $stmt->fetch();
        if (!$report) {
            return null;
        }

        $analysisStmt = $this->pdo->prepare(
            "SELECT
                da.`report_id` AS `id`,
                da.`developer_description` AS `devdesc`,
                fc.`code` AS `classification`,
                da.`date_originated` AS `dateoriginated`,
                ph.`phases_csv` AS `phase`,
                da.`activity_originated` AS `actoriginated`,
                da.`deliverable`,
                da.`what_to_do` AS `whattodo`,
                DATE(dr.`reported_at`) AS `submitted`,
                da.`assigned_on` AS `assigned`,
                CASE da.`uniqueness`
                    WHEN 'duplicate' THEN 'Already submitted'
                    ELSE 'New defect'
                END AS `uniqueness`,
                da.`duplicate_of_report_id`,
                CASE da.`priority`
                    WHEN 'most_urgent' THEN 'most urgent'
                    WHEN 'less_urgent' THEN 'less urgent'
                    ELSE 'average'
                END AS `priority`,
                da.`severity`,
                da.`workaround`,
                da.`proposed_fix` AS `proposedfix`,
                da.`status`,
                da.`fix_description` AS `fixdesc`,
                da.`fix_started_on` AS `fixstarted`,
                da.`fix_completed_on` AS `fixcomplete`,
                da.`fix_impact` AS `fiximpact`,
                da.`fix_effort` AS `fixeffort`,
                da.`fix_cost` AS `fixcost`,
                da.`resolution_description` AS `resodesc`,
                da.`resolution_completed_on` AS `resocomplete`,
                da.`resolution_closed_on` AS `resoclosed`,
                da.`cost_of_defect` AS `costofdefect`,
                da.`activity_found` AS `actfound`
             FROM `defect_analyses` da
             INNER JOIN `defect_reports` dr ON dr.`id` = da.`report_id`
             LEFT JOIN `fault_classifications` fc ON fc.`id` = da.`classification_id`
             LEFT JOIN (
                 SELECT
                     ap.`report_id`,
                     GROUP_CONCAT(sp.`name` ORDER BY sp.`id` SEPARATOR ',') AS `phases_csv`
                 FROM `analysis_phases` ap
                 INNER JOIN `sdlc_phases` sp ON sp.`id` = ap.`phase_id`
                 WHERE ap.`report_id` = :report_id
                 GROUP BY ap.`report_id`
             ) ph ON ph.`report_id` = da.`report_id`
             WHERE da.`report_id` = :id
             LIMIT 1"
        );
        $analysisStmt->execute([':id' => $id, ':report_id' => $id]);
        $analysis = $analysisStmt->fetch();
        if ($analysis) {
            $analysis['phases'] = !empty($analysis['phase'])
                ? explode(',', (string) $analysis['phase'])
                : [];
            $report['analysis'] = $analysis;
        } else {
            $report['analysis'] = null;
        }

        return $report;
    }

    /**
     * Creates a new normalized defect report (`defect_reports`)
     */
    public function create(array $data): int
    {
        $severity   = DefectSeverity::from($data['severity'] ?? 'minor');
        $priority   = DefectPriority::fromInput($data['priority'] ?? 'average');
        $personType = PersonType::from($data['persontype'] ?? 'Customer');
        $productId  = $this->resolveProductId((string) $data['product']);

        $reporterUserId = isset($data['customer_id']) && $data['customer_id'] !== null
            ? (int) $data['customer_id']
            : null;

        $guestName  = $reporterUserId === null
            ? substr(trim(strip_tags((string) ($data['who'] ?? 'Anonymous Guest'))), 0, 100)
            : null;
        $guestEmail = $reporterUserId === null
            ? trim(strip_tags((string) ($data['contact_email'] ?? '')))
            : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO `defect_reports`
             (`product_id`, `environment`, `reporter_user_id`, `guest_name`, `guest_email`, `reported_at`,
              `title`, `description`, `discovered_by`, `person_type`, `occurred_on`, `location`,
              `initial_severity`, `initial_priority`, `additional_info`)
             VALUES
             (:product_id, :environment, :reporter_user_id, :guest_name, :guest_email, :reported_at,
              :title, :description, :discovered_by, :person_type, :occurred_on, :location,
              :initial_severity, :initial_priority, :additional_info)'
        );

        $stmt->execute([
            ':product_id'       => $productId,
            ':environment'      => trim(strip_tags((string) ($data['environment'] ?? ''))),
            ':reporter_user_id' => $reporterUserId,
            ':guest_name'       => $guestName,
            ':guest_email'      => $guestEmail,
            ':reported_at'      => date('Y-m-d H:i:s'),
            ':title'            => trim(strip_tags((string) $data['title'])),
            ':description'      => trim(strip_tags((string) $data['description'])),
            ':discovered_by'    => trim(strip_tags((string) ($data['discovered_by'] ?? ''))),
            ':person_type'      => $personType->value,
            ':occurred_on'      => $data['occured'],
            ':location'         => trim(strip_tags((string) ($data['location'] ?? ''))),
            ':initial_severity' => $severity->value,
            ':initial_priority' => $priority->dbValue(),
            ':additional_info'  => trim(strip_tags((string) ($data['addinfo'] ?? ''))),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Updates an existing defect report (`defect_reports`)
     */
    public function update(int $id, array $data): bool
    {
        $severity   = DefectSeverity::from($data['severity']);
        $priority   = DefectPriority::fromInput($data['priority']);
        $personType = PersonType::from($data['persontype']);
        $productId  = $this->resolveProductId((string) $data['product']);

        $stmt = $this->pdo->prepare(
            'UPDATE `defect_reports` SET
                `product_id`       = :product_id,
                `environment`      = :environment,
                `title`            = :title,
                `description`      = :description,
                `discovered_by`    = :discovered_by,
                `person_type`      = :person_type,
                `occurred_on`      = :occurred_on,
                `location`         = :location,
                `initial_severity` = :initial_severity,
                `initial_priority` = :initial_priority,
                `additional_info`  = :additional_info
             WHERE `id` = :id'
        );

        $stmt->execute([
            ':product_id'       => $productId,
            ':environment'      => trim(strip_tags((string) ($data['environment'] ?? ''))),
            ':title'            => trim(strip_tags((string) $data['title'])),
            ':description'      => trim(strip_tags((string) $data['description'])),
            ':discovered_by'    => trim(strip_tags((string) ($data['discovered_by'] ?? ''))),
            ':person_type'      => $personType->value,
            ':occurred_on'      => $data['occured'],
            ':location'         => trim(strip_tags((string) ($data['location'] ?? ''))),
            ':initial_severity' => $severity->value,
            ':initial_priority' => $priority->dbValue(),
            ':additional_info'  => trim(strip_tags((string) ($data['addinfo'] ?? ''))),
            ':id'               => $id,
        ]);

        return $stmt->rowCount() >= 0;
    }

    /**
     * Deletes a defect report and cascades to `defect_analyses` & `analysis_phases`
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `defect_reports` WHERE `id` = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
