<?php
declare(strict_types=1);

namespace DefMan\Repository;

use DefMan\Domain\Enum\DefectPriority;
use DefMan\Domain\Enum\DefectSeverity;
use DefMan\Domain\Enum\PersonType;
use PDO;

/**
 * Defect Report Repository (`raport` + joined `analyse` telemetry)
 * Refactored from: form_raport.php, form_raportpub.php, form_raportlist.php,
 *                  form_list.php, form_defrap.php, db.php (deleteraport)
 */
final readonly class DefectRepository
{
    public function __construct(private PDO $pdo) {}

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
            $conditions[]        = 'r.`Severity` = :severity';
            $params[':severity'] = $severity;
        }

        if ($priority !== null && $priority !== '') {
            $normalizedPriority  = DefectPriority::fromInput($priority)->value;
            $conditions[]        = 'r.`Priority` = :priority';
            $params[':priority'] = $normalizedPriority;
        }

        if ($status !== null && $status !== '') {
            if ($status === 'unassigned') {
                $conditions[] = 'a.`status` IS NULL';
            } else {
                $conditions[]      = 'a.`status` = :status';
                $params[':status'] = $status;
            }
        }

        if ($product !== null && $product !== '') {
            $conditions[]       = 'r.`Product` = :product';
            $params[':product'] = $product;
        }

        if ($customerId !== null) {
            $conditions[]           = 'r.`CustomerID` = :customer_id';
            $params[':customer_id'] = $customerId;
        }

        $whereSql = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $orderBySql = match ($sortBy) {
            'product'  => 'r.`Product` ASC, r.`ID` DESC',
            'contact'  => 'r.`Who` DESC, r.`ID` DESC',
            'severity' => "FIELD(r.`Severity`, 'critical', 'major', 'minor') ASC, r.`Submitted` DESC",
            'priority', 'all' => "FIELD(r.`Priority`, 'm_urgent', 'average', 'l_urgent') ASC, r.`Submitted` DESC",
            default    => 'r.`Submitted` DESC, r.`ID` DESC',
        };

        $sql = "
            SELECT
                r.`ID`,
                r.`Product`,
                r.`product_id`,
                r.`Environment`,
                r.`CustomerID`,
                r.`Who`,
                r.`Contactemail`,
                r.`Whenreported`,
                r.`Title`,
                r.`Description`,
                r.`Discoveredby`,
                r.`Persontype`,
                r.`Occured`,
                r.`Location`,
                r.`Severity`,
                r.`Priority`,
                CASE r.`Priority`
                    WHEN 'm_urgent' THEN 'most urgent'
                    WHEN 'l_urgent' THEN 'less urgent'
                    ELSE 'average'
                END AS `PriorityLabel`,
                r.`Submitted`,
                DATEDIFF(CURDATE(), r.`Submitted`) AS `days_since_submitted`,
                r.`Addinfo`,
                CASE WHEN r.`CustomerID` IS NOT NULL AND u.`id` IS NULL THEN 1 ELSE 0 END AS `is_deleted_user`,
                a.`classification`,
                COALESCE(a.`status`, 'submitted') AS `analysis_status`,
                a.`severity` AS `developer_severity`,
                a.`priority` AS `developer_priority`,
                a.`fixeffort`,
                a.`fixcost`,
                a.`costofdefect`,
                a.`assigned`,
                a.`fixcomplete`,
                a.`resocomplete`,
                a.`resoclosed`
            FROM `raport` r
            LEFT JOIN `users` u ON u.`id` = r.`CustomerID`
            LEFT JOIN `analyse` a ON a.`id` = r.`ID`
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
                r.*,
                CASE r.`Priority`
                    WHEN 'm_urgent' THEN 'most urgent'
                    WHEN 'l_urgent' THEN 'less urgent'
                    ELSE 'average'
                END AS `PriorityLabel`,
                DATEDIFF(CURDATE(), r.`Submitted`) AS `days_since_submitted`
             FROM `raport` r
             WHERE r.`ID` = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $report = $stmt->fetch();
        if (!$report) {
            return null;
        }

        $analysisStmt = $this->pdo->prepare('SELECT * FROM `analyse` WHERE `id` = :id LIMIT 1');
        $analysisStmt->execute([':id' => $id]);
        $report['analysis'] = $analysisStmt->fetch() ?: null;

        return $report;
    }

    /**
     * Creates a new defect report (form_raport.php & form_raportpub.php)
     */
    public function create(array $data): int
    {
        $severity   = DefectSeverity::from($data['severity'] ?? 'minor');
        $priority   = DefectPriority::fromInput($data['priority'] ?? 'average');
        $personType = PersonType::from($data['persontype'] ?? 'Customer');

        $productStmt = $this->pdo->prepare('SELECT `id` FROM `product` WHERE `name` = :name LIMIT 1');
        $productStmt->execute([':name' => $data['product']]);
        $productId = $productStmt->fetchColumn() ?: null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO `raport`
             (`Product`, `product_id`, `Environment`, `CustomerID`, `Who`, `Contactemail`, `Whenreported`,
              `Title`, `Description`, `Discoveredby`, `Persontype`, `Occured`, `Location`,
              `Severity`, `Priority`, `Submitted`, `Addinfo`)
             VALUES
             (:Product, :product_id, :Environment, :CustomerID, :Who, :Contactemail, :Whenreported,
              :Title, :Description, :Discoveredby, :Persontype, :Occured, :Location,
              :Severity, :Priority, :Submitted, :Addinfo)'
        );

        $nowDate = date('Y-m-d');
        $nowTime = date('Y-m-d G:i');

        $stmt->execute([
            ':Product'      => trim(strip_tags((string) $data['product'])),
            ':product_id'   => $productId ? (int) $productId : null,
            ':Environment'  => trim(strip_tags((string) ($data['environment'] ?? ''))),
            ':CustomerID'   => isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            ':Who'          => substr(trim(strip_tags((string) $data['who'])), 0, 64),
            ':Contactemail' => trim(strip_tags((string) $data['contact_email'])),
            ':Whenreported' => $nowTime,
            ':Title'        => trim(strip_tags((string) $data['title'])),
            ':Description'  => trim(strip_tags((string) $data['description'])),
            ':Discoveredby' => trim(strip_tags((string) ($data['discovered_by'] ?? ''))),
            ':Persontype'   => $personType->value,
            ':Occured'      => $data['occured'],
            ':Location'     => trim(strip_tags((string) ($data['location'] ?? ''))),
            ':Severity'     => $severity->value,
            ':Priority'     => $priority->value,
            ':Submitted'    => $nowDate,
            ':Addinfo'      => trim(strip_tags((string) ($data['addinfo'] ?? ''))),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Updates an existing defect report (form_defrap.php)
     */
    public function update(int $id, array $data): bool
    {
        $severity   = DefectSeverity::from($data['severity']);
        $priority   = DefectPriority::fromInput($data['priority']);
        $personType = PersonType::from($data['persontype']);

        $stmt = $this->pdo->prepare(
            'UPDATE `raport` SET
                `Product`      = :Product,
                `Environment`  = :Environment,
                `Title`        = :Title,
                `Description`  = :Description,
                `Discoveredby` = :Discoveredby,
                `Persontype`   = :Persontype,
                `Occured`      = :Occured,
                `Location`     = :Location,
                `Severity`     = :Severity,
                `Priority`     = :Priority,
                `Submitted`    = :Submitted,
                `Addinfo`      = :Addinfo
             WHERE `ID` = :id'
        );

        $stmt->execute([
            ':Product'      => trim(strip_tags((string) $data['product'])),
            ':Environment'  => trim(strip_tags((string) ($data['environment'] ?? ''))),
            ':Title'        => trim(strip_tags((string) $data['title'])),
            ':Description'  => trim(strip_tags((string) $data['description'])),
            ':Discoveredby' => trim(strip_tags((string) ($data['discovered_by'] ?? ''))),
            ':Persontype'   => $personType->value,
            ':Occured'      => $data['occured'],
            ':Location'     => trim(strip_tags((string) ($data['location'] ?? ''))),
            ':Severity'     => $severity->value,
            ':Priority'     => $priority->value,
            ':Submitted'    => $data['submitted'],
            ':Addinfo'      => trim(strip_tags((string) ($data['addinfo'] ?? ''))),
            ':id'           => $id,
        ]);

        return $stmt->rowCount() >= 0;
    }

    /**
     * Deletes a defect report and cascades to `analyse` (db.php?function=deleteraport)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `raport` WHERE `ID` = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
