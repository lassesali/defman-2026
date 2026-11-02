<?php
declare(strict_types=1);

namespace DefMan\Repository;

use DefMan\Domain\Enum\DefectPriority;
use DefMan\Domain\Enum\DefectSeverity;
use DefMan\Domain\Enum\DefectStatus;
use DefMan\Domain\Enum\IeeeFaultType;
use DefMan\Domain\Enum\Uniqueness;
use PDO;

/**
 * Defect Analysis, Fix & Resolution Repository (`defect_analyses`, `fault_classifications`, `sdlc_phases`, `analysis_phases` — 3NF)
 * Refactored from: form_alist.php, form_analyze.php, form_analyzed.php,
 *                  form_deffix.php, form_defreso.php, db.php (deleteanalyze)
 */
final readonly class AnalysisRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Resolves an IEEE 1044-1992 fault classification code to `fault_classifications.id`
     */
    private function resolveClassificationId(string $code): int
    {
        $trimmed = trim(strip_tags($code));
        if ($trimmed === '') {
            $trimmed = IeeeFaultType::OTHER->value;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `fault_classifications` (`code`) VALUES (:code)
             ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)'
        );
        $stmt->execute([':code' => $trimmed]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Syncs the 1NF `analysis_phases` junction table for a given `report_id`
     *
     * @param list<string> $phases
     */
    private function syncAnalysisPhases(int $reportId, array $phases): void
    {
        $deleteStmt = $this->pdo->prepare('DELETE FROM `analysis_phases` WHERE `report_id` = :report_id');
        $deleteStmt->execute([':report_id' => $reportId]);

        $phaseUpsertStmt = $this->pdo->prepare(
            'INSERT INTO `sdlc_phases` (`name`) VALUES (:name)
             ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)'
        );
        $junctionStmt = $this->pdo->prepare(
            'INSERT IGNORE INTO `analysis_phases` (`report_id`, `phase_id`) VALUES (:report_id, :phase_id)'
        );

        foreach ($phases as $rawPhase) {
            $phaseName = trim(strip_tags((string) $rawPhase));
            if ($phaseName === '') {
                continue;
            }
            $phaseUpsertStmt->execute([':name' => $phaseName]);
            $phaseId = (int) $this->pdo->lastInsertId();
            $junctionStmt->execute([
                ':report_id' => $reportId,
                ':phase_id'  => $phaseId,
            ]);
        }
    }

    /**
     * Returns the full Analyzer Queue with lead-time metrics:
     * - submitted_to_assigned_days
     * - submitted_to_fixed_days
     * - submitted_to_resolved_days
     * - assigned_to_fixed_days
     * - assigned_to_resolved_days
     */
    public function getAnalyzerQueue(?string $sort = 'all'): array
    {
        $whereSql = '';
        $params   = [];

        if (in_array($sort, ['critical', 'major', 'minor'], true)) {
            $whereSql        = 'WHERE COALESCE(da.`severity`, dr.`initial_severity`) = :sort';
            $params[':sort'] = $sort;
        } elseif (in_array($sort, ['most urgent', 'average', 'less urgent', 'most_urgent', 'less_urgent'], true)) {
            $whereSql        = 'WHERE COALESCE(da.`priority`, dr.`initial_priority`) = :sort';
            $params[':sort'] = DefectPriority::fromInput($sort)->dbValue();
        } elseif (in_array($sort, ['submitted', 'assigned', 'postponed', 'opened', 'fixed', 'resolved', 'closed'], true)) {
            $whereSql        = "WHERE COALESCE(da.`status`, 'submitted') = :sort";
            $params[':sort'] = $sort;
        }

        $orderBySql = match ($sort) {
            'date'  => 'dr.`reported_at` DESC, dr.`id` DESC',
            default => "FIELD(COALESCE(da.`priority`, dr.`initial_priority`, 'average'), 'most_urgent', 'average', 'less_urgent') ASC, dr.`reported_at` DESC",
        };

        $sql = "
            SELECT
                dr.`id` AS `id`,
                p.`name` AS `product`,
                dr.`title` AS `title`,
                dr.`description` AS `report_description`,
                dr.`environment` AS `environment`,
                dr.`occurred_on` AS `occured`,
                COALESCE(u.`email`, dr.`guest_email`, '') AS `contact_email`,
                COALESCE(u.`login`, dr.`guest_name`, '') AS `who`,
                dr.`initial_severity` AS `report_severity`,
                CASE dr.`initial_priority`
                    WHEN 'most_urgent' THEN 'm_urgent'
                    WHEN 'less_urgent' THEN 'l_urgent'
                    ELSE 'average'
                END AS `report_priority`,
                DATE(dr.`reported_at`) AS `report_submitted`,
                da.`developer_description` AS `devdesc`,
                COALESCE(fc.`code`, 'Other') AS `classification`,
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
                COALESCE(da.`severity`, dr.`initial_severity`) AS `severity`,
                CASE COALESCE(da.`priority`, dr.`initial_priority`)
                    WHEN 'most_urgent' THEN 'most urgent'
                    WHEN 'less_urgent' THEN 'less urgent'
                    ELSE 'average'
                END AS `priority`,
                da.`workaround`,
                da.`proposed_fix` AS `proposedfix`,
                COALESCE(da.`status`, 'submitted') AS `status`,
                da.`fix_description` AS `fixdesc`,
                da.`fix_started_on` AS `fixstarted`,
                da.`fix_completed_on` AS `fixcomplete`,
                da.`fix_impact` AS `fiximpact`,
                COALESCE(da.`fix_effort`, 0.00) AS `fixeffort`,
                COALESCE(da.`fix_cost`, 0.00) AS `fixcost`,
                da.`resolution_description` AS `resodesc`,
                da.`resolution_completed_on` AS `resocomplete`,
                da.`resolution_closed_on` AS `resoclosed`,
                COALESCE(da.`cost_of_defect`, 0.00) AS `costofdefect`,
                da.`activity_found` AS `actfound`,
                DATEDIFF(CURDATE(), DATE(dr.`reported_at`)) AS `days_open`,
                CASE WHEN da.`assigned_on` IS NOT NULL
                     THEN DATEDIFF(da.`assigned_on`, DATE(dr.`reported_at`))
                     ELSE NULL END AS `submitted_to_assigned_days`,
                CASE WHEN da.`fix_completed_on` IS NOT NULL
                     THEN DATEDIFF(da.`fix_completed_on`, DATE(dr.`reported_at`))
                     ELSE NULL END AS `submitted_to_fixed_days`,
                CASE WHEN da.`resolution_completed_on` IS NOT NULL
                     THEN DATEDIFF(da.`resolution_completed_on`, DATE(dr.`reported_at`))
                     ELSE NULL END AS `submitted_to_resolved_days`,
                CASE WHEN da.`fix_completed_on` IS NOT NULL AND da.`assigned_on` IS NOT NULL
                     THEN DATEDIFF(da.`fix_completed_on`, da.`assigned_on`)
                     ELSE NULL END AS `assigned_to_fixed_days`,
                CASE WHEN da.`resolution_completed_on` IS NOT NULL AND da.`assigned_on` IS NOT NULL
                     THEN DATEDIFF(da.`resolution_completed_on`, da.`assigned_on`)
                     ELSE NULL END AS `assigned_to_resolved_days`
            FROM `defect_reports` dr
            INNER JOIN `products` p ON p.`id` = dr.`product_id`
            LEFT JOIN `users` u ON u.`id` = dr.`reporter_user_id`
            LEFT JOIN `defect_analyses` da ON da.`report_id` = dr.`id`
            LEFT JOIN `fault_classifications` fc ON fc.`id` = da.`classification_id`
            LEFT JOIN (
                SELECT
                    ap.`report_id`,
                    GROUP_CONCAT(sp.`name` ORDER BY sp.`id` SEPARATOR ',') AS `phases_csv`
                FROM `analysis_phases` ap
                INNER JOIN `sdlc_phases` sp ON sp.`id` = ap.`phase_id`
                GROUP BY ap.`report_id`
            ) ph ON ph.`report_id` = dr.`id`
            {$whereSql}
            ORDER BY {$orderBySql}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $faultEnum = IeeeFaultType::fromLegacyString((string) $row['classification']);
            $row['classification_color'] = sprintf('#%06X', $faultEnum->hexColor());
            $row['phases'] = !empty($row['phase']) ? explode(',', (string) $row['phase']) : [];
        }

        return $rows;
    }

    /**
     * Upserts initial developer analysis & IEEE (1992) classification (`defect_analyses` + `analysis_phases`)
     */
    public function upsertAnalysis(int $defectId, array $data): void
    {
        $phaseList = [];
        if (is_array($data['phases'] ?? null)) {
            $phaseList = array_values(array_filter(array_map('strval', $data['phases'])));
        } elseif (!empty($data['phase'])) {
            $phaseList = array_values(array_filter(array_map('trim', explode(',', (string) $data['phase']))));
        }

        $classificationCode = !empty($data['classification_other'])
            ? trim(strip_tags((string) $data['classification_other']))
            : IeeeFaultType::fromLegacyString((string) ($data['classification'] ?? 'Logic'))->value;

        $uniquenessEnum = Uniqueness::fromInput((string) ($data['uniqueness'] ?? 'New defect'));
        $uniquenessDb   = $uniquenessEnum->dbValue();

        $severity = $uniquenessEnum === Uniqueness::CRITICAL
            ? DefectSeverity::CRITICAL->value
            : DefectSeverity::from($data['severity'] ?? 'minor')->value;

        $priorityDb = DefectPriority::fromInput((string) ($data['priority'] ?? 'average'))->dbValue();
        $status     = DefectStatus::from($data['status'] ?? 'opened')->value;

        $duplicateOfReportId = !empty($data['duplicate_of_report_id'])
            ? (int) $data['duplicate_of_report_id']
            : null;

        $this->pdo->beginTransaction();
        try {
            $classificationId = $this->resolveClassificationId($classificationCode);

            $stmt = $this->pdo->prepare(
                'INSERT INTO `defect_analyses`
                 (`report_id`, `developer_description`, `classification_id`, `date_originated`,
                  `activity_originated`, `deliverable`, `what_to_do`, `assigned_on`, `uniqueness`,
                  `duplicate_of_report_id`, `priority`, `severity`, `workaround`, `proposed_fix`,
                  `status`, `activity_found`)
                 VALUES
                 (:report_id, :developer_description, :classification_id, :date_originated,
                  :activity_originated, :deliverable, :what_to_do, :assigned_on, :uniqueness,
                  :duplicate_of_report_id, :priority, :severity, :workaround, :proposed_fix,
                  :status, :activity_found)
                 ON DUPLICATE KEY UPDATE
                  `developer_description`  = VALUES(`developer_description`),
                  `classification_id`      = VALUES(`classification_id`),
                  `date_originated`        = VALUES(`date_originated`),
                  `activity_originated`    = VALUES(`activity_originated`),
                  `deliverable`            = VALUES(`deliverable`),
                  `what_to_do`             = VALUES(`what_to_do`),
                  `assigned_on`            = VALUES(`assigned_on`),
                  `uniqueness`             = VALUES(`uniqueness`),
                  `duplicate_of_report_id` = VALUES(`duplicate_of_report_id`),
                  `priority`               = VALUES(`priority`),
                  `severity`               = VALUES(`severity`),
                  `workaround`             = VALUES(`workaround`),
                  `proposed_fix`           = VALUES(`proposed_fix`),
                  `status`                 = VALUES(`status`),
                  `activity_found`         = VALUES(`activity_found`)'
            );

            $stmt->execute([
                ':report_id'              => $defectId,
                ':developer_description'  => trim(strip_tags((string) $data['devdesc'])),
                ':classification_id'      => $classificationId,
                ':date_originated'        => $data['dateoriginated'],
                ':activity_originated'    => trim(strip_tags((string) $data['actoriginated'])),
                ':deliverable'            => trim(strip_tags((string) $data['deliverable'])),
                ':what_to_do'             => trim(strip_tags((string) ($data['whattodo'] ?? ''))),
                ':assigned_on'            => $data['assigned'],
                ':uniqueness'             => $uniquenessDb,
                ':duplicate_of_report_id' => $duplicateOfReportId,
                ':priority'               => $priorityDb,
                ':severity'               => $severity,
                ':workaround'             => trim(strip_tags((string) ($data['workaround'] ?? ''))),
                ':proposed_fix'           => trim(strip_tags((string) $data['proposedfix'])),
                ':status'                 => $status,
                ':activity_found'         => trim(strip_tags((string) $data['actfound'])),
            ]);

            $this->syncAnalysisPhases($defectId, $phaseList);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Upserts defect fix effort, monetary cost & resolution details (`defect_analyses`)
     * Refactored from: form_deffix.php & form_defreso.php
     */
    public function upsertResolutionAndCost(int $defectId, array $data): void
    {
        $status = isset($data['status'])
            ? DefectStatus::from((string) $data['status'])->value
            : DefectStatus::RESOLVED->value;

        $stmt = $this->pdo->prepare(
            'INSERT INTO `defect_analyses`
             (`report_id`, `status`, `fix_description`, `fix_started_on`, `fix_completed_on`, `fix_impact`,
              `fix_effort`, `fix_cost`, `resolution_description`, `resolution_completed_on`, `resolution_closed_on`, `cost_of_defect`)
             VALUES
             (:report_id, :status, :fix_description, :fix_started_on, :fix_completed_on, :fix_impact,
              :fix_effort, :fix_cost, :resolution_description, :resolution_completed_on, :resolution_closed_on, :cost_of_defect)
             ON DUPLICATE KEY UPDATE
              `status`                  = VALUES(`status`),
              `fix_description`         = COALESCE(VALUES(`fix_description`), `fix_description`),
              `fix_started_on`          = COALESCE(VALUES(`fix_started_on`), `fix_started_on`),
              `fix_completed_on`        = COALESCE(VALUES(`fix_completed_on`), `fix_completed_on`),
              `fix_impact`              = COALESCE(VALUES(`fix_impact`), `fix_impact`),
              `fix_effort`              = COALESCE(VALUES(`fix_effort`), `fix_effort`),
              `fix_cost`                = COALESCE(VALUES(`fix_cost`), `fix_cost`),
              `resolution_description`  = COALESCE(VALUES(`resolution_description`), `resolution_description`),
              `resolution_completed_on` = COALESCE(VALUES(`resolution_completed_on`), `resolution_completed_on`),
              `resolution_closed_on`    = COALESCE(VALUES(`resolution_closed_on`), `resolution_closed_on`),
              `cost_of_defect`          = COALESCE(VALUES(`cost_of_defect`), `cost_of_defect`)'
        );

        $stmt->execute([
            ':report_id'               => $defectId,
            ':status'                  => $status,
            ':fix_description'         => isset($data['fixdesc']) ? trim(strip_tags((string) $data['fixdesc'])) : null,
            ':fix_started_on'          => !empty($data['fixstarted']) ? $data['fixstarted'] : null,
            ':fix_completed_on'        => !empty($data['fixcomplete']) ? $data['fixcomplete'] : null,
            ':fix_impact'              => isset($data['fiximpact']) ? trim(strip_tags((string) $data['fiximpact'])) : null,
            ':fix_effort'              => isset($data['fixeffort']) ? (float) $data['fixeffort'] : null,
            ':fix_cost'                => isset($data['fixcost']) ? (float) $data['fixcost'] : null,
            ':resolution_description'  => isset($data['resodesc']) ? trim(strip_tags((string) $data['resodesc'])) : null,
            ':resolution_completed_on' => !empty($data['resocomplete']) ? $data['resocomplete'] : null,
            ':resolution_closed_on'    => !empty($data['resoclosed']) ? $data['resoclosed'] : null,
            ':cost_of_defect'          => isset($data['costofdefect']) ? (float) $data['costofdefect'] : null,
        ]);
    }

    /**
     * Deletes analysis entry only (db.php?function=deleteanalyze)
     */
    public function delete(int $defectId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `defect_analyses` WHERE `report_id` = :report_id');
        $stmt->execute([':report_id' => $defectId]);
        return $stmt->rowCount() > 0;
    }
}
