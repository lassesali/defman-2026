<?php
declare(strict_types=1);

namespace DefMan\Domain\Enum;

/**
 * IEEE Std 1044-2009 Standard Classification for Software Anomalies (Root Cause & Defect Type)
 * Replaces obsolete 1992 IEEE Fault & Cost multiplier classification while preserving
 * backward-compatible mapping for legacy 2004 records.
 */
enum IeeeFaultType: string
{
    case CODE_LOGIC            = 'Code / Logic';
    case COMPUTATIONAL         = 'Computational';
    case INTERFACE_INTEGRATION = 'Interface / Integration';
    case DATA_SCHEMA           = 'Data / Schema';
    case CONCURRENCY_TIMING    = 'Concurrency / Timing';
    case CONFIGURATION_ENV     = 'Configuration / Environment';
    case SECURITY_ACCESS       = 'Security / Access Control';
    case REQUIREMENTS_SPEC     = 'Requirements / Specification';
    case ARCHITECTURE_DESIGN   = 'Architecture / Design';
    case DOCUMENTATION         = 'Documentation';
    case REGRESSION            = 'Regression';
    case OTHER                 = 'Other';

    // Backward-compatibility aliases for legacy 1992 case references
    public const LOGIC                  = self::CODE_LOGIC;
    public const INTERFACE              = self::INTERFACE_INTEGRATION;
    public const DATA_HANDLING          = self::DATA_SCHEMA;
    public const DATA                   = self::DATA_SCHEMA;
    public const DOCUMENT_QUALITY       = self::DOCUMENTATION;
    public const ENHANCEMENT            = self::REQUIREMENTS_SPEC;
    public const CAUSED_BY_PREVIOUS_FIX = self::REGRESSION;

    /**
     * Hex color palette used by PixiJS v8 WebGPU DefectTriageCanvas & UI telemetry badges
     */
    public function hexColor(): int
    {
        return match ($this) {
            self::CODE_LOGIC            => 0xEF4444, // Red-500
            self::COMPUTATIONAL         => 0xF97316, // Orange-500
            self::INTERFACE_INTEGRATION => 0xEC4899, // Pink-500
            self::DATA_SCHEMA           => 0x8B5CF6, // Violet-500
            self::CONCURRENCY_TIMING    => 0x6366F1, // Indigo-500
            self::CONFIGURATION_ENV     => 0x0EA5E9, // Sky-500
            self::SECURITY_ACCESS       => 0xE11D48, // Rose-600
            self::REQUIREMENTS_SPEC     => 0x10B981, // Emerald-500
            self::ARCHITECTURE_DESIGN   => 0x14B8A6, // Teal-500
            self::DOCUMENTATION         => 0x06B6D4, // Cyan-500
            self::REGRESSION            => 0xF59E0B, // Amber-500
            self::OTHER                 => 0x64748B, // Slate-500
        };
    }

    /**
     * Detailed IEEE Std 1044-2009 root-cause sub-classifications
     *
     * @return list<string>
     */
    public function subcategories(): array
    {
        return match ($this) {
            self::CODE_LOGIC => [
                'Control flow or branch condition error',
                'Null / uninitialized reference dereference',
                'Boundary or off-by-one loop condition',
                'Cyclic state or recursion termination fault',
                'Missing exception or error handler',
            ],
            self::COMPUTATIONAL => [
                'Precision loss, rounding, or numeric truncation fault',
                'Incorrect formula, operator, or operand precedence',
                'Unit conversion or scaling mismatch',
                'Overflow / underflow in arithmetic calculation',
            ],
            self::INTERFACE_INTEGRATION => [
                'API contract or payload schema mismatch',
                'Session / protocol state desynchronization',
                'Module call parameter or return-type mismatch',
                'Upstream / downstream service timeout handling',
            ],
            self::DATA_SCHEMA => [
                'Database schema constraint or column type mismatch',
                'Incorrect data serialization or encoding',
                'Stale cache or out-of-bounds buffer access',
                'Missing transaction isolation or migration rule',
            ],
            self::CONCURRENCY_TIMING => [
                'Race condition or unsynchronized shared state',
                'Deadlock or lock contention under load',
                'Asynchronous event ordering violation',
            ],
            self::CONFIGURATION_ENV => [
                'Environment variable or secret misconfiguration',
                'Container / runtime dependency drift',
                'Infrastructure or deployment manifest fault',
            ],
            self::SECURITY_ACCESS => [
                'Authentication token or session lifecycle flaw',
                'RBAC authorization bypass or privilege escalation',
                'Input sanitization / injection vulnerability',
            ],
            self::REQUIREMENTS_SPEC => [
                'Ambiguous or incomplete acceptance criteria',
                'Unaccounted edge case in domain specification',
                'Behavioral change in product requirements',
            ],
            self::ARCHITECTURE_DESIGN => [
                'Coupling or component boundary design flaw',
                'Scalability or throughput bottleneck in design',
                'Missing idempotency or fallback architecture',
            ],
            self::DOCUMENTATION => [
                'Inaccurate API or operational runbook documentation',
                'Outdated configuration or upgrade instructions',
            ],
            self::REGRESSION => [
                'Regression introduced by a prior defect fix or refactor',
                'Unintended side effect of dependency or feature merge',
            ],
            self::OTHER => [
                'Unclassified or external environment anomaly',
            ],
        };
    }

    /**
     * Normalizes both IEEE Std 1044-2009 strings and legacy 1992 strings
     */
    public static function fromLegacyString(string $value): self
    {
        $trimmed = trim($value);
        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $trimmed) === 0 || strcasecmp($case->name, $trimmed) === 0) {
                return $case;
            }
        }

        return match (strtolower($trimmed)) {
            'logic'                                             => self::CODE_LOGIC,
            'computational'                                     => self::COMPUTATIONAL,
            'interface'                                         => self::INTERFACE_INTEGRATION,
            'data handling', 'data'                             => self::DATA_SCHEMA,
            'documentation', 'document quality'                 => self::DOCUMENTATION,
            'enhancement'                                       => self::REQUIREMENTS_SPEC,
            'caused by a prev. fix', 'caused by a previous fix' => self::REGRESSION,
            default                                             => self::OTHER,
        };
    }
}
