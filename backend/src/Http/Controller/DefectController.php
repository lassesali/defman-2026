<?php
declare(strict_types=1);

namespace DefMan\Http\Controller;

use DefMan\Domain\Enum\UserLevel;
use DefMan\Http\Middleware\RbacMiddleware;
use DefMan\Repository\AnalysisRepository;
use DefMan\Repository\DefectRepository;

/**
 * Defect & IEEE Std 1044-2009 / DORA Telemetry Controller
 * Refactored from: form_raportpub.php, form_raport.php, form_raportlist.php,
 *                  form_list.php, form_defrap.php, form_alist.php,
 *                  form_analyze.php, form_analyzed.php, form_deffix.php, form_defreso.php
 */
final readonly class DefectController
{
    public function __construct(
        private DefectRepository $defectRepository,
        private AnalysisRepository $analysisRepository,
        private RbacMiddleware $rbac
    ) {}

    /**
     * POST /api/defects/public (Refactored from form_raportpub.php — Level 1 Public)
     */
    public function createPublicReport(array $body): array
    {
        $this->rbac->requireLevel(UserLevel::PUBLIC);

        $errors = $this->validateReportFields($body, isPublic: true);
        if (count($errors) > 0) {
            http_response_code(422);
            return ['errors' => $errors];
        }

        $id = $this->defectRepository->create([
            'product'              => $body['product'],
            'environment'          => $body['environment'] ?? '',
            'detected_environment' => $body['detected_environment'] ?? 'PROD',
            'symptom_category'     => $body['symptom_category'] ?? null,
            'is_regression'        => !empty($body['is_regression']),
            'customer_id'          => null,
            'who'                  => $body['who'],
            'contact_email'        => $body['contact_email'],
            'title'                => $body['title'],
            'description'          => $body['description'],
            'discovered_by'        => $body['discovered_by'] ?? '',
            'persontype'           => $body['persontype'],
            'occured'              => $body['occured'],
            'location'             => $body['location'] ?? '',
            'severity'             => $body['severity'],
            'priority'             => $body['priority'],
            'addinfo'              => $body['addinfo'] ?? '',
        ]);

        http_response_code(201);
        return [
            'id'      => $id,
            'message' => 'Report has been stored to the database',
        ];
    }

    /**
     * POST /api/defects (Refactored from form_raport.php — Level 2+ Customer)
     */
    public function createCustomerReport(array $body): array
    {
        $user = $this->rbac->requireLevel(UserLevel::CUSTOMER);

        $errors = $this->validateReportFields($body, isPublic: false);
        if (count($errors) > 0) {
            http_response_code(422);
            return ['errors' => $errors];
        }

        $id = $this->defectRepository->create([
            'product'              => $body['product'],
            'environment'          => $body['environment'] ?? '',
            'detected_environment' => $body['detected_environment'] ?? 'PROD',
            'symptom_category'     => $body['symptom_category'] ?? null,
            'is_regression'        => !empty($body['is_regression']),
            'customer_id'          => (int) $user['id'],
            'who'                  => (string) $user['login'],
            'contact_email'        => (string) $user['email'],
            'title'                => $body['title'],
            'description'          => $body['description'],
            'discovered_by'        => $body['discovered_by'] ?? ($user['name'] ?? $user['login']),
            'persontype'           => $body['persontype'],
            'occured'              => $body['occured'],
            'location'             => $body['location'] ?? '',
            'severity'             => $body['severity'],
            'priority'             => $body['priority'],
            'addinfo'              => $body['addinfo'] ?? '',
        ]);

        http_response_code(201);
        return [
            'id'      => $id,
            'message' => 'Report has been stored into the database',
        ];
    }

    /**
     * GET /api/defects (Refactored from form_raportlist.php & form_list.php)
     */
    public function listDefects(array $query): array
    {
        $user = $this->rbac->requireLevel(UserLevel::CUSTOMER);

        // Customers (Level 2) default to viewing their own defects unless Level 3+ Analyzer/Admin
        $customerId = isset($query['customer_id']) && $query['customer_id'] !== ''
            ? (int) $query['customer_id']
            : ((int) $user['userlevel'] === UserLevel::CUSTOMER->value ? (int) $user['id'] : null);

        $defects = $this->defectRepository->findFiltered(
            severity: $query['severity'] ?? null,
            priority: $query['priority'] ?? null,
            status: $query['status'] ?? null,
            product: $query['product'] ?? null,
            customerId: $customerId,
            sortBy: (string) ($query['sort'] ?? 'date')
        );

        return [
            'count'   => count($defects),
            'defects' => $defects,
        ];
    }

    /**
     * GET /api/defects/{id} (Refactored from form_defrap.php + DORA telemetry summary)
     */
    public function getDefect(int $id): array
    {
        $this->rbac->requireLevel(UserLevel::CUSTOMER);
        $defect = $this->defectRepository->findById($id);

        if ($defect === null) {
            http_response_code(404);
            return ['error' => "Defect report #{$id} not found."];
        }

        $defect['telemetry'] = $this->analysisRepository->getDoraTelemetrySummary();

        return $defect;
    }

    /**
     * PUT /api/defects/{id} (Refactored from form_defrap.php update)
     */
    public function updateDefectReport(int $id, array $body): array
    {
        $this->rbac->requireLevel(UserLevel::ANALYZER);

        $errors = $this->validateReportFields($body, isPublic: false);
        if (count($errors) > 0) {
            http_response_code(422);
            return ['errors' => $errors];
        }

        $this->defectRepository->update($id, [
            'product'              => $body['product'],
            'environment'          => $body['environment'] ?? '',
            'detected_environment' => $body['detected_environment'] ?? 'PROD',
            'symptom_category'     => $body['symptom_category'] ?? null,
            'is_regression'        => !empty($body['is_regression']),
            'title'                => $body['title'],
            'description'          => $body['description'],
            'discovered_by'        => $body['discovered_by'] ?? '',
            'persontype'           => $body['persontype'],
            'occured'              => $body['occured'],
            'location'             => $body['location'] ?? '',
            'severity'             => $body['severity'],
            'priority'             => $body['priority'],
            'submitted'            => $body['submitted'] ?? date('Y-m-d'),
            'addinfo'              => $body['addinfo'] ?? '',
        ]);

        return ['message' => "You updated the defect raport #{$id} in the database."];
    }

    /**
     * DELETE /api/defects/{id} (Refactored from db.php?function=deleteraport)
     */
    public function deleteDefect(int $id): array
    {
        $this->rbac->requireLevel(UserLevel::ANALYZER);
        $this->defectRepository->delete($id);
        return ['message' => "Defect #{$id} and its analysis deleted."];
    }

    /**
     * GET /api/analyzer/queue (Refactored from form_alist.php & form_analyzed.php + DORA Telemetry)
     */
    public function getAnalyzerQueue(array $query): array
    {
        $this->rbac->requireLevel(UserLevel::ANALYZER);
        $items = $this->analysisRepository->getAnalyzerQueue($query['sort'] ?? 'all');

        return [
            'count'     => count($items),
            'queue'     => $items,
            'telemetry' => $this->analysisRepository->getDoraTelemetrySummary(),
        ];
    }

    /**
     * PUT /api/defects/{id}/analyze (IEEE 1044-2009 Anomaly Triage & Classification)
     */
    public function analyzeDefect(int $id, array $body): array
    {
        $this->rbac->requireLevel(UserLevel::ANALYZER);

        $errors = [];
        if (empty(trim((string) ($body['devdesc'] ?? '')))) {
            $errors[] = 'Empty Defect description!';
        }
        if (
            empty($body['root_cause_type']) &&
            empty($body['classification']) &&
            empty($body['classification_other'])
        ) {
            $errors[] = 'Empty IEEE 1044-2009 Root Cause / Classification!';
        }
        if (!empty($body['dateoriginated']) && !$this->isValidDate((string) $body['dateoriginated'])) {
            $errors[] = 'Invalid Date defect originated!';
        }
        if (!empty($body['assigned']) && !$this->isValidDate((string) $body['assigned'])) {
            $errors[] = 'Invalid Date assigned!';
        }
        if (empty(trim((string) ($body['proposedfix'] ?? '')))) {
            $errors[] = 'Empty Proposed fix!';
        }

        if (count($errors) > 0) {
            http_response_code(422);
            return ['errors' => $errors];
        }

        $this->analysisRepository->upsertAnalysis($id, $body);
        return [
            'message'   => "You updated analyze report #{$id} in the database.",
            'telemetry' => $this->analysisRepository->getDoraTelemetrySummary(),
        ];
    }

    /**
     * PUT /api/defects/{id}/resolve (IEEE 1044-2009 Root Cause, Resolution Ref & Actual Effort Hours)
     */
    public function resolveDefect(int $id, array $body): array
    {
        $this->rbac->requireLevel(UserLevel::ANALYZER);

        $this->analysisRepository->upsertResolutionAndCost($id, $body);
        return [
            'id'        => $id,
            'status'    => $body['status'] ?? 'resolved',
            'message'   => "You updated the analyze report #{$id} in the database.",
            'telemetry' => $this->analysisRepository->getDoraTelemetrySummary(),
        ];
    }

    /**
     * Validates report fields per form_raport.php & form_raportpub.php
     *
     * @return list<string>
     */
    private function validateReportFields(array $body, bool $isPublic): array
    {
        $errors = [];

        if ($isPublic) {
            $who   = trim((string) ($body['who'] ?? ''));
            $email = trim((string) ($body['contact_email'] ?? ''));
            if ($who === '') {
                $errors[] = 'Empty Who reported the problem!';
            } elseif (strlen($who) > 64) {
                $errors[] = 'Who reported the problem is too long!';
            }
            if ($email === '') {
                $errors[] = 'Empty Contact email!';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Bad Contact email address!';
            }
        }

        if (empty(trim((string) ($body['title'] ?? '')))) {
            $errors[] = 'Empty Title!';
        }
        if (empty(trim((string) ($body['description'] ?? '')))) {
            $errors[] = 'Empty Description!';
        }
        if (empty($body['occured']) || !$this->isValidDate((string) $body['occured'])) {
            $errors[] = 'Invalid Date Problem occurred!';
        }
        if (empty($body['product'])) {
            $errors[] = 'Empty Product!';
        }
        if (empty($body['severity'])) {
            $errors[] = 'Empty Severity!';
        }
        if (empty($body['priority'])) {
            $errors[] = 'Empty Priority!';
        }
        if (empty($body['persontype'])) {
            $errors[] = 'Empty Type of person!';
        }

        return $errors;
    }

    /**
     * Preserves 2004 date bounds validation (1980 < year < 2100) from form_raport.php
     */
    private function isValidDate(string $dateStr): bool
    {
        $parts = explode('-', $dateStr);
        if (count($parts) !== 3) {
            return false;
        }
        [$year, $month, $day] = array_map('intval', $parts);
        return $year > 1980 && $year < 2100 && checkdate($month, $day, $year);
    }
}
