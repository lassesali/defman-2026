<?php
declare(strict_types=1);

namespace DefMan\Domain\Enum;

/**
 * IEEE (1992) Standard Classification for Software Anomalies (IEEE Std 1044-1993 / 1992 draft)
 * Refactored from: defecttype.txt & faulttypes.html
 */
enum IeeeFaultType: string
{
    case LOGIC                  = 'Logic';
    case COMPUTATIONAL          = 'Computational';
    case INTERFACE              = 'Interface';
    case DATA_HANDLING          = 'Data handling';
    case DATA                   = 'Data';
    case DOCUMENTATION          = 'Documentation';
    case DOCUMENT_QUALITY       = 'Document quality';
    case ENHANCEMENT            = 'Enhancement';
    case CAUSED_BY_PREVIOUS_FIX = 'Caused by a prev. Fix';
    case OTHER                  = 'Other';

    /**
     * Hex color palette used by PixiJS v8 WebGPU DefectTriageCanvas & UI badges
     */
    public function hexColor(): int
    {
        return match ($this) {
            self::LOGIC                  => 0xEF4444, // Red-500
            self::COMPUTATIONAL          => 0xF97316, // Orange-500
            self::INTERFACE              => 0xEC4899, // Pink-500
            self::DATA_HANDLING          => 0x8B5CF6, // Violet-500
            self::DATA                   => 0x6366F1, // Indigo-500
            self::DOCUMENTATION          => 0x06B6D4, // Cyan-500
            self::DOCUMENT_QUALITY       => 0x14B8A6, // Teal-500
            self::ENHANCEMENT            => 0x10B981, // Emerald-500
            self::CAUSED_BY_PREVIOUS_FIX => 0xF59E0B, // Amber-500
            self::OTHER                  => 0x64748B, // Slate-500
        };
    }

    /**
     * Detailed sub-classifications from faulttypes.html (IEEE, 1992)
     *
     * @return list<string>
     */
    public function subcategories(): array
    {
        return match ($this) {
            self::LOGIC => [
                'Forgotten cases or steps',
                'Duplicate logic',
                'Extreme conditions neglected',
                'Unnecessary function',
                'Misinterpretation',
                'Missing condition test',
                'Checking wrong variable',
                'Iterating loop incorrectly',
            ],
            self::COMPUTATIONAL => [
                'Equation insufficient or incorrect: Missing computation',
                'Equation insufficient or incorrect: Operand in equation incorrect',
                'Equation insufficient or incorrect: Operator in equation incorrect',
                'Equation insufficient or incorrect: Parentheses used incorrectly',
                'Precision loss: Rounding or truncation fault',
                'Precision loss: Mixed modes',
                'Sign convention fault',
            ],
            self::INTERFACE => [
                'Interrupts handled incorrectly',
                'I/O timing incorrect: Timing fault causes data loss',
                'Subroutine/module mismatch: Wrong subroutine called',
                'Subroutine/module mismatch: Incorrectly-located subroutine call',
                'Subroutine/module mismatch: Non-existent subroutine called',
                'Subroutine/module mismatch: Inconsistent subroutine arguments',
            ],
            self::DATA_HANDLING => [
                'Initialized data incorrectly',
                'Accessed or stored data incorrectly: Flag or index set incorrectly',
                'Accessed or stored data incorrectly: Packed/unpacked data incorrectly',
                'Accessed or stored data incorrectly: Referenced wrong data variable',
                'Accessed or stored data incorrectly: Data referenced out of bounds',
                'Scaling or units of data incorrect',
                'Dimensioned data incorrectly: Variable type incorrect',
                'Dimensioned data incorrectly: Subscripted variable incorrectly',
                'Scope of data incorrect',
            ],
            self::DATA => [
                'Sensor data incorrect or missing',
                'Operator data incorrect or missing',
                'Embedded data in tables incorrect or missing',
                'External data incorrect or missing',
                'Output data incorrect or missing',
                'Input data incorrect or missing',
            ],
            self::DOCUMENTATION => [
                'Ambiguous statement',
                'Incomplete item',
                'Incorrect item',
                'Missing item',
                'Conflicting items',
                'Redundant items',
                'Confusing item',
                'Illogical item',
                'Non-verifiable item',
            ],
            self::DOCUMENT_QUALITY => [
                'Applicable standards not met',
                'Not traceable',
                'Not current',
                'Inconsistencies',
                'Incomplete',
                'No identification',
            ],
            self::ENHANCEMENT => [
                'Change in program requirements: Add new capability',
                'Change in program requirements: Remove unnecessary capability',
                'Change in program requirements: Update current capability',
                'Improve comments',
                'Improve code efficiency',
                'Implement editorial changes',
                'Improve usability',
                'Software fix of a hardware problem',
                'Other enhancement',
            ],
            self::CAUSED_BY_PREVIOUS_FIX => [
                'Failure caused by a previous fix',
            ],
            self::OTHER => [
                'Other problem',
            ],
        };
    }

    /**
     * Normalizes legacy case variations (e.g. "Caused by a prev. fix" vs "Caused by a prev. Fix")
     */
    public static function fromLegacyString(string $value): self
    {
        $trimmed = trim($value);
        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $trimmed) === 0) {
                return $case;
            }
        }
        return self::OTHER;
    }
}
