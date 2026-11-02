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
 * Defect Priority normalized across `defect_reports.initial_priority` and `defect_analyses.priority`
 * Supports 3NF DB enum ('most_urgent', 'average', 'less_urgent') as well as legacy UI values
 */
enum DefectPriority: string
{
    case MOST_URGENT = 'm_urgent';
    case AVERAGE     = 'average';
    case LESS_URGENT = 'l_urgent';

    public function dbValue(): string
    {
        return match ($this) {
            self::MOST_URGENT => 'most_urgent',
            self::AVERAGE     => 'average',
            self::LESS_URGENT => 'less_urgent',
        };
    }

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
            'most_urgent', 'm_urgent', 'most urgent' => self::MOST_URGENT,
            'less_urgent', 'l_urgent', 'less urgent' => self::LESS_URGENT,
            default                                  => self::AVERAGE,
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
 * Normalized 3NF column uses ENUM('new', 'duplicate')
 */
enum Uniqueness: string
{
    case NEW_DEFECT        = 'New defect';
    case ALREADY_SUBMITTED = 'Already submitted';
    case CRITICAL          = 'Critical';

    public function dbValue(): string
    {
        return match ($this) {
            self::ALREADY_SUBMITTED => 'duplicate',
            self::NEW_DEFECT, self::CRITICAL => 'new',
        };
    }

    public static function fromInput(string $input): self
    {
        return match (strtolower(trim($input))) {
            'duplicate', 'already submitted' => self::ALREADY_SUBMITTED,
            'critical'                       => self::CRITICAL,
            default                          => self::NEW_DEFECT,
        };
    }
}

/**
 * Lifecycle Phase in which the defect originated (`sdlc_phases`)
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
