<?php
declare(strict_types=1);

namespace DefMan\Domain\Enum;

/**
 * RBAC User Levels from menubar.php, form_usermanagement.php, form_apply.php
 */
enum UserLevel: int
{
    case APPLYING = 0; // Unapproved applicant (form_apply.php)
    case PUBLIC   = 1; // Public defect reporter (form_raportpub.php)
    case CUSTOMER = 2; // Authenticated customer (form_raport.php)
    case ANALYZER = 3; // Developer / QA triage & cost tracking (form_analyze.php)
    case ADMIN    = 4; // Full administrator (form_usermanagement.php, form_productdetails.php)

    public function label(): string
    {
        return match ($this) {
            self::APPLYING => 'Applying',
            self::PUBLIC   => 'Public',
            self::CUSTOMER => 'Customer',
            self::ANALYZER => 'Analyzer',
            self::ADMIN    => 'Admin',
        };
    }
}

/**
 * Defect Severity from form_raport.php & form_analyze.php
 */
enum DefectSeverity: string
{
    case MINOR    = 'minor';
    case MAJOR    = 'major';
    case CRITICAL = 'critical';
}

/**
 * Defect Priority from form_raport.php (m_urgent, average, l_urgent)
 * and form_analyze.php (most urgent, average, less urgent)
 */
enum DefectPriority: string
{
    case MOST_URGENT = 'm_urgent';
    case AVERAGE     = 'average';
    case LESS_URGENT = 'l_urgent';

    public function analysisLabel(): string
    {
        return match ($this) {
            self::MOST_URGENT => 'most urgent',
            self::AVERAGE     => 'average',
            self::LESS_URGENT => 'less urgent',
        };
    }

    public static function fromInput(string $input): self
    {
        return match (strtolower(trim($input))) {
            'm_urgent', 'most urgent' => self::MOST_URGENT,
            'l_urgent', 'less urgent' => self::LESS_URGENT,
            default                   => self::AVERAGE,
        };
    }
}

/**
 * Defect Status from form_analyze.php & PixiJS v8 Triage Lanes
 */
enum DefectStatus: string
{
    case SUBMITTED = 'submitted';
    case ASSIGNED  = 'assigned';
    case POSTPONED = 'postponed';
    case OPENED    = 'opened';
    case FIXED     = 'fixed';
    case RESOLVED  = 'resolved';
    case CLOSED    = 'closed';
}

/**
 * Person Type who discovered the defect (form_raport.php)
 */
enum PersonType: string
{
    case CUSTOMER  = 'Customer';
    case DEVELOPER = 'Developer';
    case TESTER    = 'Tester';
}

/**
 * Defect Uniqueness (form_analyze.php)
 */
enum Uniqueness: string
{
    case NEW_DEFECT        = 'New defect';
    case ALREADY_SUBMITTED = 'Already submitted';
    case CRITICAL          = 'Critical';
}

/**
 * Lifecycle Phase in which the defect originated (form_analyze.php)
 */
enum OriginPhase: string
{
    case REQUIREMENTS   = 'Requirements';
    case DESIGN         = 'Design';
    case IMPLEMENTATION = 'Implementation';
    case TESTING        = 'Testing';
    case DEPLOYMENT     = 'Deployment';
    case MAINTENANCE    = 'Maintenance';
}
