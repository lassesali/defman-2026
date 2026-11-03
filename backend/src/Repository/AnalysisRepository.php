<?php
declare(strict_types=1);

namespace DefMan\Repository;

use DefMan\Domain\Enum\DefectPriority;
use DefMan\Domain\Enum\DefectSeverity;
use DefMan\Domain\Enum\DefectStatus;
use DefMan\Domain\Enum\DetectedEnvironment;
use DefMan\Domain\Enum\IeeeFaultType;
use DefMan\Domain\Enum\SymptomCategory;
use DefMan\Domain\Enum\Uniqueness;
use PDO;

/**
 * IEEE Std 1044-2009 Anomaly Classification & DORA Flow Engineering Repository
 * (`defect_analyses`, `fault_classifications`, `sdlc_phases`, `analysis_phases` — 3NF)
 *
 * Replaces obsolete 1992 phase-cost multiplier math with real aggregations for:
 * - MTTR (resolved_at - reported_at)
 * - Triage Lead Time (triaged_at - reported_at)
 * - Production Escape Rate (detected_environment = 'PROD')
 * - Regression Rate (is_regression = TRUE)
 * - Actual Effort Hours by IEEE 1044-2009 Root Cause
 */
final readonly class AnalysisRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Resolves an IEEE Std 1044-2009 root cause / classification code to `fault_classifications.id`
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
     * Normalizes a datetime or date input string to MySQL DATETIME ('Y-m-d H:i:s')
     */
    private function normalizeDateTime(?string $value, ?string $fallbackTime = null): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return $fallbackTime;
        }
        $normalized = str_replace('T', ' ', $trimmed);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) === 1) {
            return $normalized . ' 12:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized) === 1) {
            return $normalized . ':00';
        }
        return $normalized;
    }

    /**
     * Returns the full Analyzer Queue with IEEE 1044-2009 classification and DORA lead-time metrics:
     * - triage_lead_time_hours (triaged_at - reported_at)
     * - mttr_hours (resolved_at - reported_at)
     * - detected_environment, is_regression, symptom_category, root_cause_type,
     *   introducing_ref, resolution_ref, actual_effort_hours
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
        } elseif (in_array($sort, ['DEV', 'CI', 'STAGING', 'PROD'], true)) {
            $whereSql        = "WHERE COALESCE(da.`detected_environment`, dr.`detected_environment`, 'PROD') = :sort";
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
                COALESCE(da.`detected_environment`, dr.`detected_environment`, 'PROD') AS `detected_environment`,
                COALESCE(da.`symptom_category`, dr.`symptom_category`, 'Other Symptom') AS `symptom_category`,
                COALESCE(da.`root_cause_type`, fc.`code`, 'Other') AS `root_cause_type`,
                COALESCE(da.`root_cause_type`, fc.`code`, 'Other') AS `classification`,
                COALESCE(da.`is_regression`, dr.`is_regression`, 0) AS `is_regression`,
                da.`introducing_ref` AS `introducing_ref`,
                da.`resolution_ref` AS `resolution_ref`,
                COALESCE(da.`actual_effort_hours`, da.`fix_effort`, 0.00) AS `actual_effort_hours`,
                dr.`reported_at` AS `reported_at`,
                da.`triaged_at` AS `triaged_at`,
                da.`resolved_at` AS `resolved_at`,
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
                da.`date_originated` AS `dateoriginated`,
                ph.`phases_csv` AS `phase`,
                da.`activity_originated` AS `actoriginated`,
                da.`deliverable`,
                da.`what_to_do` AS `whattodo`,
                DATE(dr.`reported_at`) AS `submitted`,
                COALESCE(da.`assigned_on`, DATE(da.`triaged_at`)) AS `assigned`,
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
                COALESCE(da.`actual_effort_hours`, da.`fix_effort`, 0.00) AS `fixeffort`,
                COALESCE(da.`fix_cost`, 0.00) AS `fixcost`,
                da.`resolution_description` AS `resodesc`,
                COALESCE(da.`resolution_completed_on`, DATE(da.`resolved_at`)) AS `resocomplete`,
                da.`resolution_closed_on` AS `resoclosed`,
                COALESCE(da.`cost_of_defect`, 0.00) AS `costofdefect`,
                da.`activity_found` AS `actfound`,
                DATEDIFF(CURDATE(), DATE(dr.`reported_at`)) AS `days_open`,
                CASE
                    WHEN da.`triaged_at` IS NOT NULL
                    THEN ROUND(TIMESTAMPDIFF(SECOND, dr.`reported_at`, da.`triaged_at`) / 3600.0, 2)
                    WHEN da.`assigned_on` IS NOT NULL
                    THEN ROUND(DATEDIFF(da.`assigned_on`, DATE(dr.`reported_at`)) * 24.0, 2)
                    ELSE NULL
                END AS `triage_lead_time_hours`,
                CASE
                    WHEN da.`resolved_at` IS NOT NULL
                    THEN ROUND(TIMESTAMPDIFF(SECOND, dr.`reported_at`, da.`resolved_at`) / 3600.0, 2)
                    WHEN da.`resolution_completed_on` IS NOT NULL
                    THEN ROUND(DATEDIFF(da.`resolution_completed_on`, DATE(dr.`reported_at`)) * 24.0, 2)
                    ELSE NULL
                END AS `mttr_hours`,
                CASE WHEN COALESCE(da.`assigned_on`, DATE(da.`triaged_at`)) IS NOT NULL
                     THEN DATEDIFF(COALESCE(da.`assigned_on`, DATE(da.`triaged_at`)), DATE(dr.`reported_at`))
                     ELSE NULL END AS `submitted_to_assigned_days`,
                CASE WHEN da.`fix_completed_on` IS NOT NULL
                     THEN DATEDIFF(da.`fix_completed_on`, DATE(dr.`reported_at`))
                     ELSE NULL END AS `submitted_to_fixed_days`,
                CASE WHEN COALESCE(da.`resolution_completed_on`, DATE(da.`resolved_at`)) IS NOT NULL
                     THEN DATEDIFF(COALESCE(da.`resolution_completed_on`, DATE(da.`resolved_at`)), DATE(dr.`reported_at`))
                     ELSE NULL END AS `submitted_to_resolved_days`,
                CASE WHEN da.`fix_completed_on` IS NOT NULL AND COALESCE(da.`assigned_on`, DATE(da.`triaged_at`)) IS NOT NULL
                     THEN DATEDIFF(da.`fix_completed_on`, COALESCE(da.`assigned_on`, DATE(da.`triaged_at`)))
                     ELSE NULL END AS `assigned_to_fixed_days`,
                CASE WHEN COALESCE(da.`resolution_completed_on`, DATE(da.`resolved_at`)) IS NOT NULL AND COALESCE(da.`assigned_on`, DATE(da.`triaged_at`)) IS NOT NULL
                     THEN DATEDIFF(COALESCE(da.`resolution_completed_on`, DATE(da.`resolved_at`)), COALESCE(da.`assigned_on`, DATE(da.`triaged_at`)))
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
            $faultEnum = IeeeFaultType::fromLegacyString((string) $row['root_cause_type']);
            $row['classification_color'] = sprintf('#%06X', $faultEnum->hexColor());
            $row['is_regression'] = (bool) $row['is_regression'];
            $row['actual_effort_hours'] = (float) $row['actual_effort_hours'];
            $row['triage_lead_time_hours'] = $row['triage_lead_time_hours'] !== null ? (float) $row['triage_lead_time_hours'] : null;
            $row['mttr_hours'] = $row['mttr_hours'] !== null ? (float) $row['mttr_hours'] : null;
            $row['phases'] = !empty($row['phase']) ? explode(',', (string) $row['phase']) : [];
        }

        return $rows;
    }

    /**
     * Computes real DORA / Flow Engineering & IEEE Std 1044-2009 aggregations:
     * - MTTR (Mean Time To Resolution in hours: resolved_at - reported_at)
     * - Triage Lead Time (Mean Time To Triage in hours: triaged_at - reported_at)
     * - Production Escape Rate (% of anomalies detected in 'PROD')
     * - Regression Rate (% of anomalies with is_regression = 1)
     * - Actual Effort Hours broken down by IEEE 1044-2009 Root Cause
     */
    public function getDoraTelemetrySummary(): array
    {
        $summaryStmt = $this->pdo->query("
            SELECT
                COUNT(dr.`id`) AS `total_defects`,
                SUM(CASE WHEN da.`triaged_at` IS NOT NULL THEN 1 ELSE 0 END) AS `triaged_count`,
                SUM(CASE WHEN da.`resolved_at` IS NOT NULL THEN 1 ELSE 0 END) AS `resolved_count`,
                ROUND(AVG(
                    CASE WHEN da.`resolved_at` IS NOT NULL
                         THEN TIMESTAMPDIFF(SECOND, dr.`reported_at`, da.`resolved_at`) / 3600.0
                         ELSE NULL
                    END
                ), 2) AS `mttr_hours`,
                ROUND(AVG(
                    CASE WHEN da.`triaged_at` IS NOT NULL
                         THEN TIMESTAMPDIFF(SECOND, dr.`reported_at`, da.`triaged_at`) / 3600.0
                         ELSE NULL
                    END
                ), 2) AS `triage_lead_time_hours`,
                SUM(CASE WHEN COALESCE(da.`detected_environment`, dr.`detected_environment`, 'PROD') = 'PROD' THEN 1 ELSE 0 END) AS `prod_escape_count`,
                SUM(CASE WHEN COALESCE(da.`detected_environment`, dr.`detected_environment`, 'PROD') = 'STAGING' THEN 1 ELSE 0 END) AS `staging_count`,
                SUM(CASE WHEN COALESCE(da.`detected_environment`, dr.`detected_environment`, 'PROD') = 'CI' THEN 1 ELSE 0 END) AS `ci_count`,
                SUM(CASE WHEN COALESCE(da.`detected_environment`, dr.`detected_environment`, 'PROD') = 'DEV' THEN 1 ELSE 0 END) AS `dev_count`,
                SUM(CASE WHEN COALESCE(da.`is_regression`, dr.`is_regression`, 0) = 1 THEN 1 ELSE 0 END) AS `regression_count`,
                ROUND(COALESCE(SUM(COALESCE(da.`actual_effort_hours`, da.`fix_effort`, 0)), 0), 2) AS `total_actual_effort_hours`,
                ROUND(COALESCE(AVG(COALESCE(da.`actual_effort_hours`, da.`fix_effort`, 0)), 0), 2) AS `avg_actual_effort_hours`
            FROM `defect_reports` dr
            LEFT JOIN `defect_analyses` da ON da.`report_id` = dr.`id`
        ");
        $summary = $summaryStmt->fetch() ?: [];

        $totalDefects    = (int) ($summary['total_defects'] ?? 0);
        $prodEscapeCount = (int) ($summary['prod_escape_count'] ?? 0);
        $regressionCount = (int) ($summary['regression_count'] ?? 0);

        $escapeRatePct = $totalDefects > 0
            ? round(($prodEscapeCount * 100.0) / $totalDefects, 1)
            : 0.0;
        $regressionRatePct = $totalDefects > 0
            ? round(($regressionCount * 100.0) / $totalDefects, 1)
            : 0.0;

        $breakdownStmt = $this->pdo->query("
            SELECT
                COALESCE(da.`root_cause_type`, fc.`code`, 'Unclassified') AS `root_cause_type`,
                COUNT(dr.`id`) AS `defect_count`,
                ROUND(COALESCE(SUM(COALESCE(da.`actual_effort_hours`, da.`fix_effort`, 0)), 0), 2) AS `total_effort_hours`,
                ROUND(COALESCE(AVG(COALESCE(da.`actual_effort_hours`, da.`fix_effort`, 0)), 0), 2) AS `avg_effort_hours`,
                ROUND(AVG(
                    CASE WHEN da.`resolved_at` IS NOT NULL
                         THEN TIMESTAMPDIFF(SECOND, dr.`reported_at`, da.`resolved_at`) / 3600.0
                         ELSE NULL
                    END
                ), 2) AS `avg_mttr_hours`,
                SUM(CASE WHEN COALESCE(da.`is_regression`, dr.`is_regression`, 0) = 1 THEN 1 ELSE 0 END) AS `regression_count`,
                SUM(CASE WHEN COALESCE(da.`detected_environment`, dr.`detected_environment`, 'PROD') = 'PROD' THEN 1 ELSE 0 END) AS `prod_escape_count`
            FROM `defect_reports` dr
            LEFT JOIN `defect_analyses` da ON da.`report_id` = dr.`id`
            LEFT JOIN `fault_classifications` fc ON fc.`id` = da.`classification_id`
            GROUP BY COALESCE(da.`root_cause_type`, fc.`code`, 'Unclassified')
            ORDER BY `total_effort_hours` DESC, `defect_count` DESC
        ");
        $breakdownRows = $breakdownStmt->fetchAll();

        $rootCauseBreakdown = [];
        foreach ($breakdownRows as $row) {
            $rootCauseBreakdown[] = [
                'root_cause_type'    => (string) $row['root_cause_type'],
                'defect_count'       => (int) $row['defect_count'],
                'total_effort_hours' => (float) $row['total_effort_hours'],
                'avg_effort_hours'   => (float) $row['avg_effort_hours'],
                'avg_mttr_hours'     => $row['avg_mttr_hours'] !== null ? (float) $row['avg_mttr_hours'] : null,
                'regression_count'   => (int) $row['regression_count'],
                'prod_escape_count'  => (int) $row['prod_escape_count'],
            ];
        }

        return [
            'total_defects'               => $totalDefects,
            'triaged_count'               => (int) ($summary['triaged_count'] ?? 0),
            'resolved_count'              => (int) ($summary['resolved_count'] ?? 0),
            'mttr_hours'                  => isset($summary['mttr_hours']) && $summary['mttr_hours'] !== null ? (float) $summary['mttr_hours'] : null,
            'triage_lead_time_hours'      => isset($summary['triage_lead_time_hours']) && $summary['triage_lead_time_hours'] !== null ? (float) $summary['triage_lead_time_hours'] : null,
            'production_escape_rate_pct'  => $escapeRatePct,
            'prod_escape_count'           => $prodEscapeCount,
            'regression_rate_pct'         => $regressionRatePct,
            'regression_count'            => $regressionCount,
            'total_actual_effort_hours'   => (float) ($summary['total_actual_effort_hours'] ?? 0.0),
            'avg_actual_effort_hours'     => (float) ($summary['avg_actual_effort_hours'] ?? 0.0),
            'environment_counts'          => [
                'DEV'     => (int) ($summary['dev_count'] ?? 0),
                'CI'      => (int) ($summary['ci_count'] ?? 0),
                'STAGING' => (int) ($summary['staging_count'] ?? 0),
                'PROD'    => $prodEscapeCount,
            ],
            'root_cause_effort_breakdown' => $rootCauseBreakdown,
        ];
    }

    /**
     * Upserts developer triage analysis & IEEE Std 1044-2009 classification (`defect_analyses` + `analysis_phases`)
     */
    public function upsertAnalysis(int $defectId, array $data): void
    {
        $phaseList = [];
        if (is_array($data['phases'] ?? null)) {
            $phaseList = array_values(array_filter(array_map('strval', $data['phases'])));
        } elseif (!empty($data['phase'])) {
            $phaseList = array_values(array_filter(array_map('trim', explode(',', (string) $data['phase']))));
        }

        $rawRootCause = !empty($data['classification_other'])
            ? (string) $data['classification_other']
            : (string) ($data['root_cause_type'] ?? $data['classification'] ?? IeeeFaultType::CODE_LOGIC->value);

        $rootCauseType = IeeeFaultType::fromLegacyString($rawRootCause)->value;
        if (!empty($data['classification_other'])) {
            $rootCauseType = trim(strip_tags((string) $data['classification_other']));
        } elseif (!empty($data['root_cause_type']) && IeeeFaultType::fromLegacyString((string) $data['root_cause_type']) === IeeeFaultType::OTHER && strcasecmp((string) $data['root_cause_type'], 'Other') !== 0) {
            $rootCauseType = trim(strip_tags((string) $data['root_cause_type']));
        }

        $symptomCategory = !empty($data['symptom_category'])
            ? trim(strip_tags((string) $data['symptom_category']))
            : SymptomCategory::OTHER->value;

        $detectedEnv = DetectedEnvironment::fromInput((string) ($data['detected_environment'] ?? 'PROD'))->value;
        $isRegression = !empty($data['is_regression']) || $rootCauseType === IeeeFaultType::REGRESSION->value ? 1 : 0;

        $introducingRef = !empty($data['introducing_ref'])
            ? trim(strip_tags((string) $data['introducing_ref']))
            : null;
        $resolutionRef = !empty($data['resolution_ref'])
            ? trim(strip_tags((string) $data['resolution_ref']))
            : null;
        $actualEffortHours = isset($data['actual_effort_hours']) && $data['actual_effort_hours'] !== ''
            ? (float) $data['actual_effort_hours']
            : (isset($data['fixeffort']) && $data['fixeffort'] !== '' ? (float) $data['fixeffort'] : null);

        $assignedOn = !empty($data['assigned']) ? (string) $data['assigned'] : date('Y-m-d');
        $triagedAt  = $this->normalizeDateTime(
            $data['triaged_at'] ?? null,
            !empty($data['assigned']) ? $data['assigned'] . ' ' . date('H:i:s') : date('Y-m-d H:i:s')
        );

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
            $classificationId = $this->resolveClassificationId($rootCauseType);

            $stmt = $this->pdo->prepare(
                'INSERT INTO `defect_analyses`
                 (`report_id`, `developer_description`, `classification_id`, `symptom_category`,
                  `root_cause_type`, `detected_environment`, `is_regression`, `introducing_ref`,
                  `resolution_ref`, `actual_effort_hours`, `triaged_at`, `date_originated`,
                  `activity_originated`, `deliverable`, `what_to_do`, `assigned_on`, `uniqueness`,
                  `duplicate_of_report_id`, `priority`, `severity`, `workaround`, `proposed_fix`,
                  `status`, `activity_found`)
                 VALUES
                 (:report_id, :developer_description, :classification_id, :symptom_category,
                  :root_cause_type, :detected_environment, :is_regression, :introducing_ref,
                  :resolution_ref, :actual_effort_hours, :triaged_at, :date_originated,
                  :activity_originated, :deliverable, :what_to_do, :assigned_on, :uniqueness,
                  :duplicate_of_report_id, :priority, :severity, :workaround, :proposed_fix,
                  :status, :activity_found)
                 ON DUPLICATE KEY UPDATE
                  `developer_description`  = VALUES(`developer_description`),
                  `classification_id`      = VALUES(`classification_id`),
                  `symptom_category`       = VALUES(`symptom_category`),
                  `root_cause_type`        = VALUES(`root_cause_type`),
                  `detected_environment`   = VALUES(`detected_environment`),
                  `is_regression`          = VALUES(`is_regression`),
                  `introducing_ref`        = COALESCE(VALUES(`introducing_ref`), `introducing_ref`),
                  `resolution_ref`         = COALESCE(VALUES(`resolution_ref`), `resolution_ref`),
                  `actual_effort_hours`    = COALESCE(VALUES(`actual_effort_hours`), `actual_effort_hours`),
                  `triaged_at`             = COALESCE(VALUES(`triaged_at`), `triaged_at`),
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
                ':developer_description'  => trim(strip_tags((string) ($data['devdesc'] ?? ''))),
                ':classification_id'      => $classificationId,
                ':symptom_category'       => $symptomCategory,
                ':root_cause_type'        => $rootCauseType,
                ':detected_environment'   => $detectedEnv,
                ':is_regression'          => $isRegression,
                ':introducing_ref'        => $introducingRef,
                ':resolution_ref'         => $resolutionRef,
                ':actual_effort_hours'    => $actualEffortHours,
                ':triaged_at'             => $triagedAt,
                ':date_originated'        => !empty($data['dateoriginated']) ? $data['dateoriginated'] : date('Y-m-d'),
                ':activity_originated'    => trim(strip_tags((string) ($data['actoriginated'] ?? 'Development'))),
                ':deliverable'            => trim(strip_tags((string) ($data['deliverable'] ?? ''))),
                ':what_to_do'             => trim(strip_tags((string) ($data['whattodo'] ?? ''))),
                ':assigned_on'            => $assignedOn,
                ':uniqueness'             => $uniquenessDb,
                ':duplicate_of_report_id' => $duplicateOfReportId,
                ':priority'               => $priorityDb,
                ':severity'               => $severity,
                ':workaround'             => trim(strip_tags((string) ($data['workaround'] ?? ''))),
                ':proposed_fix'           => trim(strip_tags((string) ($data['proposedfix'] ?? ''))),
                ':status'                 => $status,
                ':activity_found'         => trim(strip_tags((string) ($data['actfound'] ?? 'Automated / QA Triage'))),
            ]);

            $reportSyncStmt = $this->pdo->prepare(
                'UPDATE `defect_reports`
                 SET `detected_environment` = :detected_environment,
                     `symptom_category`     = :symptom_category,
                     `is_regression`        = :is_regression
                 WHERE `id` = :id'
            );
            $reportSyncStmt->execute([
                ':detected_environment' => $detectedEnv,
                ':symptom_category'     => $symptomCategory,
                ':is_regression'        => $isRegression,
                ':id'                   => $defectId,
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
     * Upserts IEEE Std 1044-2009 root cause, resolution commit/build ref, actual effort hours,
     * and DORA resolution timestamp (`defect_analyses`)
     */
    public function upsertResolutionAndCost(int $defectId, array $data): void
    {
        $status = isset($data['status'])
            ? DefectStatus::from((string) $data['status'])->value
            : DefectStatus::RESOLVED->value;

        $rootCauseType = !empty($data['root_cause_type'])
            ? trim(strip_tags((string) $data['root_cause_type']))
            : (!empty($data['classification']) ? trim(strip_tags((string) $data['classification'])) : null);

        $classificationId = $rootCauseType !== null
            ? $this->resolveClassificationId($rootCauseType)
            : null;

        $actualEffortHours = isset($data['actual_effort_hours']) && $data['actual_effort_hours'] !== ''
            ? (float) $data['actual_effort_hours']
            : (isset($data['fixeffort']) && $data['fixeffort'] !== '' ? (float) $data['fixeffort'] : null);

        $resolutionRef = !empty($data['resolution_ref'])
            ? trim(strip_tags((string) $data['resolution_ref']))
            : null;
        $introducingRef = !empty($data['introducing_ref'])
            ? trim(strip_tags((string) $data['introducing_ref']))
            : null;

        $isResolvedState = in_array($status, [
            DefectStatus::FIXED->value,
            DefectStatus::RESOLVED->value,
            DefectStatus::CLOSED->value,
        ], true);

        $resolvedAt = $this->normalizeDateTime(
            $data['resolved_at'] ?? null,
            !empty($data['resocomplete'])
                ? $data['resocomplete'] . ' ' . date('H:i:s')
                : ($isResolvedState ? date('Y-m-d H:i:s') : null)
        );

        $stmt = $this->pdo->prepare(
            'INSERT INTO `defect_analyses`
             (`report_id`, `status`, `classification_id`, `root_cause_type`, `resolution_ref`,
              `introducing_ref`, `actual_effort_hours`, `resolved_at`, `fix_description`,
              `fix_started_on`, `fix_completed_on`, `fix_impact`, `fix_effort`,
              `resolution_description`, `resolution_completed_on`, `resolution_closed_on`)
             VALUES
             (:report_id, :status, :classification_id, :root_cause_type, :resolution_ref,
              :introducing_ref, :actual_effort_hours, :resolved_at, :fix_description,
              :fix_started_on, :fix_completed_on, :fix_impact, :fix_effort,
              :resolution_description, :resolution_completed_on, :resolution_closed_on)
             ON DUPLICATE KEY UPDATE
              `status`                  = VALUES(`status`),
              `classification_id`       = COALESCE(VALUES(`classification_id`), `classification_id`),
              `root_cause_type`         = COALESCE(VALUES(`root_cause_type`), `root_cause_type`),
              `resolution_ref`          = COALESCE(VALUES(`resolution_ref`), `resolution_ref`),
              `introducing_ref`         = COALESCE(VALUES(`introducing_ref`), `introducing_ref`),
              `actual_effort_hours`     = COALESCE(VALUES(`actual_effort_hours`), `actual_effort_hours`),
              `resolved_at`             = COALESCE(VALUES(`resolved_at`), `resolved_at`),
              `fix_description`         = COALESCE(VALUES(`fix_description`), `fix_description`),
              `fix_started_on`          = COALESCE(VALUES(`fix_started_on`), `fix_started_on`),
              `fix_completed_on`        = COALESCE(VALUES(`fix_completed_on`), `fix_completed_on`),
              `fix_impact`              = COALESCE(VALUES(`fix_impact`), `fix_impact`),
              `fix_effort`              = COALESCE(VALUES(`fix_effort`), `fix_effort`),
              `resolution_description`  = COALESCE(VALUES(`resolution_description`), `resolution_description`),
              `resolution_completed_on` = COALESCE(VALUES(`resolution_completed_on`), `resolution_completed_on`),
              `resolution_closed_on`    = COALESCE(VALUES(`resolution_closed_on`), `resolution_closed_on`)'
        );

        $stmt->execute([
            ':report_id'               => $defectId,
            ':status'                  => $status,
            ':classification_id'       => $classificationId,
            ':root_cause_type'         => $rootCauseType,
            ':resolution_ref'          => $resolutionRef,
            ':introducing_ref'         => $introducingRef,
            ':actual_effort_hours'     => $actualEffortHours,
            ':resolved_at'             => $resolvedAt,
            ':fix_description'         => isset($data['fixdesc']) ? trim(strip_tags((string) $data['fixdesc'])) : null,
            ':fix_started_on'          => !empty($data['fixstarted']) ? $data['fixstarted'] : null,
            ':fix_completed_on'        => !empty($data['fixcomplete']) ? $data['fixcomplete'] : null,
            ':fix_impact'              => isset($data['fiximpact']) ? trim(strip_tags((string) $data['fiximpact'])) : null,
            ':fix_effort'              => $actualEffortHours,
            ':resolution_description'  => isset($data['resodesc']) ? trim(strip_tags((string) $data['resodesc'])) : null,
            ':resolution_completed_on' => !empty($data['resocomplete']) ? $data['resocomplete'] : ($resolvedAt !== null ? substr($resolvedAt, 0, 10) : null),
            ':resolution_closed_on'    => !empty($data['resoclosed']) ? $data['resoclosed'] : null,
        ]);

        if (isset($data['is_regression'])) {
            $regFlag = !empty($data['is_regression']) ? 1 : 0;
            $regStmt = $this->pdo->prepare(
                'UPDATE `defect_analyses` da
                 INNER JOIN `defect_reports` dr ON dr.`id` = da.`report_id`
                 SET da.`is_regression` = :reg1, dr.`is_regression` = :reg2
                 WHERE da.`report_id` = :id'
            );
            $regStmt->execute([':reg1' => $regFlag, ':reg2' => $regFlag, ':id' => $defectId]);
        }
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
