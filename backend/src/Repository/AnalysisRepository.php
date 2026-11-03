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
 * Defect Analysis, Fix & Resolution Repository (`analyse` table)
 * Refactored from: form_alist.php, form_analyze.php, form_analyzed.php,
 *                  form_deffix.php, form_defreso.php, db.php (deleteanalyze)
 */
final readonly class AnalysisRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Returns the full Analyzer Queue with lead-time metrics:
     * - submitted_to_assigned_days
     * - submitted_to_fixed_days
     * - submitted_to_resolved_days
     * - assigned_to_fixed_days
     * - assigned_to_resolved_days
     * Refactored from: form_alist.php & form_analyzed.php
     */
    public function getAnalyzerQueue(?string $sort = 'all'): array
    {
        $whereSql = '';
        $params   = [];

        if (in_array($sort, ['critical', 'major', 'minor'], true)) {
            $whereSql        = 'WHERE COALESCE(a.`severity`, r.`Severity`) = :sort';
            $params[':sort'] = $sort;
        } elseif (in_array($sort, ['most urgent', 'average', 'less urgent'], true)) {
            $whereSql        = 'WHERE a.`priority` = :sort';
            $params[':sort'] = $sort;
        } elseif (in_array($sort, ['submitted', 'assigned', 'postponed', 'opened', 'fixed', 'resolved', 'closed'], true)) {
            $whereSql        = 'WHERE COALESCE(a.`status`, \'submitted\') = :sort';
            $params[':sort'] = $sort;
        }

        $orderBySql = match ($sort) {
            'date' => 'r.`Submitted` DESC, r.`ID` DESC',
            default => "FIELD(COALESCE(a.`priority`, 'average'), 'most urgent', 'average', 'less urgent') ASC, r.`Submitted` DESC",
        };

        $sql = "
            SELECT
                r.`ID` AS `id`,
                r.`Product` AS `product`,
                r.`Title` AS `title`,
                r.`Description` AS `report_description`,
                r.`Environment` AS `environment`,
                r.`Occured` AS `occured`,
                r.`Contactemail` AS `contact_email`,
                r.`Who` AS `who`,
                r.`Severity` AS `report_severity`,
                r.`Priority` AS `report_priority`,
                r.`Submitted` AS `report_submitted`,
                a.`devdesc`,
                COALESCE(a.`classification`, 'Other') AS `classification`,
                a.`dateoriginated`,
                a.`phase`,
                a.`actoriginated`,
                a.`deliverable`,
                a.`whattodo`,
                COALESCE(a.`submitted`, r.`Submitted`) AS `submitted`,
                a.`assigned`,
                COALESCE(a.`uniqueness`, 'New defect') AS `uniqueness`,
                COALESCE(a.`severity`, r.`Severity`) AS `severity`,
                COALESCE(
                    a.`priority`,
                    CASE r.`Priority`
                        WHEN 'm_urgent' THEN 'most urgent'
                        WHEN 'l_urgent' THEN 'less urgent'
                        ELSE 'average'
                    END
                ) AS `priority`,
                a.`workaround`,
                a.`proposedfix`,
                COALESCE(a.`status`, 'submitted') AS `status`,
                a.`fixdesc`,
                a.`fixstarted`,
                a.`fixcomplete`,
                a.`fiximpact`,
                COALESCE(a.`fixeffort`, 0.00) AS `fixeffort`,
                COALESCE(a.`fixcost`, 0.00) AS `fixcost`,
                a.`resodesc`,
                a.`resocomplete`,
                a.`resoclosed`,
                COALESCE(a.`costofdefect`, 0.00) AS `costofdefect`,
                a.`actfound`,
                DATEDIFF(CURDATE(), COALESCE(a.`submitted`, r.`Submitted`)) AS `days_open`,
                CASE WHEN a.`assigned` IS NOT NULL
                     THEN DATEDIFF(a.`assigned`, COALESCE(a.`submitted`, r.`Submitted`))
                     ELSE NULL END AS `submitted_to_assigned_days`,
                CASE WHEN a.`fixcomplete` IS NOT NULL
                     THEN DATEDIFF(a.`fixcomplete`, COALESCE(a.`submitted`, r.`Submitted`))
                     ELSE NULL END AS `submitted_to_fixed_days`,
                CASE WHEN a.`resocomplete` IS NOT NULL
                     THEN DATEDIFF(a.`resocomplete`, COALESCE(a.`submitted`, r.`Submitted`))
                     ELSE NULL END AS `submitted_to_resolved_days`,
                CASE WHEN a.`fixcomplete` IS NOT NULL AND a.`assigned` IS NOT NULL
                     THEN DATEDIFF(a.`fixcomplete`, a.`assigned`)
                     ELSE NULL END AS `assigned_to_fixed_days`,
                CASE WHEN a.`resocomplete` IS NOT NULL AND a.`assigned` IS NOT NULL
                     THEN DATEDIFF(a.`resocomplete`, a.`assigned`)
                     ELSE NULL END AS `assigned_to_resolved_days`
            FROM `raport` r
            LEFT JOIN `analyse` a ON a.`id` = r.`ID`
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
     * Upserts initial developer analysis & IEEE (1992) classification (form_analyze.php)
     */
    public function upsertAnalysis(int $defectId, array $data): void
    {
        $phases = is_array($data['phases'] ?? null)
            ? implode(',', array_map('strip_tags', $data['phases']))
            : strip_tags((string) ($data['phase'] ?? ''));

        $classification = !empty($data['classification_other'])
            ? trim(strip_tags((string) $data['classification_other']))
            : IeeeFaultType::fromLegacyString((string) ($data['classification'] ?? 'Logic'))->value;

        $uniqueness = Uniqueness::from($data['uniqueness'] ?? 'New defect')->value;
        $severity   = DefectSeverity::from($data['severity'] ?? 'minor')->value;
        $priority   = DefectPriority::fromInput($data['priority'] ?? 'average')->analysisLabel();
        $status     = DefectStatus::from($data['status'] ?? 'opened')->value;

        $stmt = $this->pdo->prepare(
            'INSERT INTO `analyse`
             (`id`, `devdesc`, `classification`, `dateoriginated`, `phase`, `actoriginated`,
              `deliverable`, `whattodo`, `submitted`, `assigned`, `uniqueness`, `priority`,
              `severity`, `workaround`, `proposedfix`, `status`, `actfound`)
             VALUES
             (:id, :devdesc, :classification, :dateoriginated, :phase, :actoriginated,
              :deliverable, :whattodo, :submitted, :assigned, :uniqueness, :priority,
              :severity, :workaround, :proposedfix, :status, :actfound)
             ON DUPLICATE KEY UPDATE
              `devdesc`        = VALUES(`devdesc`),
              `classification` = VALUES(`classification`),
              `dateoriginated` = VALUES(`dateoriginated`),
              `phase`          = VALUES(`phase`),
              `actoriginated`  = VALUES(`actoriginated`),
              `deliverable`    = VALUES(`deliverable`),
              `whattodo`       = VALUES(`whattodo`),
              `submitted`      = VALUES(`submitted`),
              `assigned`       = VALUES(`assigned`),
              `uniqueness`     = VALUES(`uniqueness`),
              `priority`       = VALUES(`priority`),
              `severity`       = VALUES(`severity`),
              `workaround`     = VALUES(`workaround`),
              `proposedfix`    = VALUES(`proposedfix`),
              `status`         = VALUES(`status`),
              `actfound`       = VALUES(`actfound`)'
        );

        $stmt->execute([
            ':id'             => $defectId,
            ':devdesc'        => trim(strip_tags((string) $data['devdesc'])),
            ':classification' => $classification,
            ':dateoriginated' => $data['dateoriginated'],
            ':phase'          => $phases,
            ':actoriginated'  => trim(strip_tags((string) $data['actoriginated'])),
            ':deliverable'    => trim(strip_tags((string) $data['deliverable'])),
            ':whattodo'       => trim(strip_tags((string) ($data['whattodo'] ?? ''))),
            ':submitted'      => $data['submitted'] ?? date('Y-m-d'),
            ':assigned'       => $data['assigned'],
            ':uniqueness'     => $uniqueness,
            ':priority'       => $priority,
            ':severity'       => $severity,
            ':workaround'     => trim(strip_tags((string) ($data['workaround'] ?? ''))),
            ':proposedfix'    => trim(strip_tags((string) $data['proposedfix'])),
            ':status'         => $status,
            ':actfound'       => trim(strip_tags((string) $data['actfound'])),
        ]);
    }

    /**
     * Upserts defect fix effort, monetary cost & resolution details
     * Refactored from: form_deffix.php & form_defreso.php
     */
    public function upsertResolutionAndCost(int $defectId, array $data): void
    {
        $status = isset($data['status'])
            ? DefectStatus::from((string) $data['status'])->value
            : DefectStatus::RESOLVED->value;

        $stmt = $this->pdo->prepare(
            'INSERT INTO `analyse`
             (`id`, `status`, `fixdesc`, `fixstarted`, `fixcomplete`, `fiximpact`,
              `fixeffort`, `fixcost`, `resodesc`, `resocomplete`, `resoclosed`, `costofdefect`)
             VALUES
             (:id, :status, :fixdesc, :fixstarted, :fixcomplete, :fiximpact,
              :fixeffort, :fixcost, :resodesc, :resocomplete, :resoclosed, :costofdefect)
             ON DUPLICATE KEY UPDATE
              `status`       = VALUES(`status`),
              `fixdesc`      = COALESCE(VALUES(`fixdesc`), `fixdesc`),
              `fixstarted`   = COALESCE(VALUES(`fixstarted`), `fixstarted`),
              `fixcomplete`  = COALESCE(VALUES(`fixcomplete`), `fixcomplete`),
              `fiximpact`    = COALESCE(VALUES(`fiximpact`), `fiximpact`),
              `fixeffort`    = COALESCE(VALUES(`fixeffort`), `fixeffort`),
              `fixcost`      = COALESCE(VALUES(`fixcost`), `fixcost`),
              `resodesc`     = COALESCE(VALUES(`resodesc`), `resodesc`),
              `resocomplete` = COALESCE(VALUES(`resocomplete`), `resocomplete`),
              `resoclosed`   = COALESCE(VALUES(`resoclosed`), `resoclosed`),
              `costofdefect` = COALESCE(VALUES(`costofdefect`), `costofdefect`)'
        );

        $stmt->execute([
            ':id'           => $defectId,
            ':status'       => $status,
            ':fixdesc'      => isset($data['fixdesc']) ? trim(strip_tags((string) $data['fixdesc'])) : null,
            ':fixstarted'   => !empty($data['fixstarted']) ? $data['fixstarted'] : null,
            ':fixcomplete'  => !empty($data['fixcomplete']) ? $data['fixcomplete'] : null,
            ':fiximpact'    => isset($data['fiximpact']) ? trim(strip_tags((string) $data['fiximpact'])) : null,
            ':fixeffort'    => isset($data['fixeffort']) ? (float) $data['fixeffort'] : null,
            ':fixcost'      => isset($data['fixcost']) ? (float) $data['fixcost'] : null,
            ':resodesc'     => isset($data['resodesc']) ? trim(strip_tags((string) $data['resodesc'])) : null,
            ':resocomplete' => !empty($data['resocomplete']) ? $data['resocomplete'] : null,
            ':resoclosed'   => !empty($data['resoclosed']) ? $data['resoclosed'] : null,
            ':costofdefect' => isset($data['costofdefect']) ? (float) $data['costofdefect'] : null,
        ]);
    }

    /**
     * Deletes analysis entry only (db.php?function=deleteanalyze)
     */
    public function delete(int $defectId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `analyse` WHERE `id` = :id');
        $stmt->execute([':id' => $defectId]);
        return $stmt->rowCount() > 0;
    }
}
