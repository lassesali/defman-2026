/**
 * IEEE (1992) Standard Classification for Software Anomalies
 * Refactored from: defecttype.txt & faulttypes.html
 */
export enum IeeeFaultType {
  LOGIC                  = 'Logic',
  COMPUTATIONAL          = 'Computational',
  INTERFACE              = 'Interface',
  DATA_HANDLING          = 'Data handling',
  DATA                   = 'Data',
  DOCUMENTATION          = 'Documentation',
  DOCUMENT_QUALITY       = 'Document quality',
  ENHANCEMENT            = 'Enhancement',
  CAUSED_BY_PREVIOUS_FIX = 'Caused by a prev. Fix',
  OTHER                  = 'Other',
}

export const IEEE_FAULT_HEX_COLORS: Record<IeeeFaultType, number> = {
  [IeeeFaultType.LOGIC]:                  0xef4444, // Red-500
  [IeeeFaultType.COMPUTATIONAL]:          0xf97316, // Orange-500
  [IeeeFaultType.INTERFACE]:              0xec4899, // Pink-500
  [IeeeFaultType.DATA_HANDLING]:          0x8b5cf6, // Violet-500
  [IeeeFaultType.DATA]:                   0x6366f1, // Indigo-500
  [IeeeFaultType.DOCUMENTATION]:          0x06b6d4, // Cyan-500
  [IeeeFaultType.DOCUMENT_QUALITY]:       0x14b8a6, // Teal-500
  [IeeeFaultType.ENHANCEMENT]:            0x10b981, // Emerald-500
  [IeeeFaultType.CAUSED_BY_PREVIOUS_FIX]: 0xf59e0b, // Amber-500
  [IeeeFaultType.OTHER]:                  0x64748b, // Slate-500
};

/**
 * Full IEEE (1992) Taxonomy from faulttypes.html
 */
export const IEEE_1992_TAXONOMY: Record<IeeeFaultType, readonly string[]> = {
  [IeeeFaultType.LOGIC]: [
    'Forgotten cases or steps',
    'Duplicate logic',
    'Extreme conditions neglected',
    'Unnecessary function',
    'Misinterpretation',
    'Missing condition test',
    'Checking wrong variable',
    'Iterating loop incorrectly',
  ],
  [IeeeFaultType.COMPUTATIONAL]: [
    'Equation insufficient or incorrect: Missing computation',
    'Equation insufficient or incorrect: Operand in equation incorrect',
    'Equation insufficient or incorrect: Operator in equation incorrect',
    'Equation insufficient or incorrect: Parentheses used incorrectly',
    'Precision loss: Rounding or truncation fault',
    'Precision loss: Mixed modes',
    'Sign convention fault',
  ],
  [IeeeFaultType.INTERFACE]: [
    'Interrupts handled incorrectly',
    'I/O timing incorrect: Timing fault causes data loss',
    'Subroutine/module mismatch: Wrong subroutine called',
    'Subroutine/module mismatch: Incorrectly-located subroutine call',
    'Subroutine/module mismatch: Non-existent subroutine called',
    'Subroutine/module mismatch: Inconsistent subroutine arguments',
  ],
  [IeeeFaultType.DATA_HANDLING]: [
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
  [IeeeFaultType.DATA]: [
    'Sensor data incorrect or missing',
    'Operator data incorrect or missing',
    'Embedded data in tables incorrect or missing',
    'External data incorrect or missing',
    'Output data incorrect or missing',
    'Input data incorrect or missing',
  ],
  [IeeeFaultType.DOCUMENTATION]: [
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
  [IeeeFaultType.DOCUMENT_QUALITY]: [
    'Applicable standards not met',
    'Not traceable',
    'Not current',
    'Inconsistencies',
    'Incomplete',
    'No identification',
  ],
  [IeeeFaultType.ENHANCEMENT]: [
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
  [IeeeFaultType.CAUSED_BY_PREVIOUS_FIX]: [
    'Failure caused by a previous fix',
  ],
  [IeeeFaultType.OTHER]: [
    'Other problem',
  ],
};

export enum UserLevel {
  APPLYING = 0,
  PUBLIC   = 1,
  CUSTOMER = 2,
  ANALYZER = 3,
  ADMIN    = 4,
}

export enum DefectSeverity {
  MINOR    = 'minor',
  MAJOR    = 'major',
  CRITICAL = 'critical',
}

export enum DefectPriority {
  MOST_URGENT = 'm_urgent',
  AVERAGE     = 'average',
  LESS_URGENT = 'l_urgent',
}

export enum DefectStatus {
  SUBMITTED = 'submitted',
  ASSIGNED  = 'assigned',
  POSTPONED = 'postponed',
  OPENED    = 'opened',
  FIXED     = 'fixed',
  RESOLVED  = 'resolved',
  CLOSED    = 'closed',
}

export enum PersonType {
  CUSTOMER  = 'Customer',
  DEVELOPER = 'Developer',
  TESTER    = 'Tester',
}

export enum Uniqueness {
  NEW_DEFECT        = 'New defect',
  ALREADY_SUBMITTED = 'Already submitted',
  CRITICAL          = 'Critical',
}

export enum OriginPhase {
  REQUIREMENTS   = 'Requirements',
  DESIGN         = 'Design',
  IMPLEMENTATION = 'Implementation',
  TESTING        = 'Testing',
  DEPLOYMENT     = 'Deployment',
  MAINTENANCE    = 'Maintenance',
}

export interface AuthUser {
  id: number;
  login: string;
  email: string;
  userlevel: UserLevel;
  role?: string;
  name?: string | null;
  company?: string | null;
  phone?: string | null;
  activate?: number;
  logged?: number;
}

export interface Product {
  id: number;
  name: string;
  report_count?: number;
}

export interface DefectAnalysis {
  id: number;
  devdesc: string | null;
  classification: IeeeFaultType | string;
  dateoriginated: string | null;
  phase: string | null;
  phases?: string[];
  actoriginated: string | null;
  deliverable: string | null;
  whattodo: string | null;
  submitted: string | null;
  assigned: string | null;
  uniqueness: Uniqueness | string;
  priority: 'most urgent' | 'average' | 'less urgent';
  severity: DefectSeverity;
  workaround: string | null;
  proposedfix: string | null;
  status: DefectStatus;
  fixdesc: string | null;
  fixstarted: string | null;
  fixcomplete: string | null;
  fiximpact: string | null;
  fixeffort: number;
  fixcost: number;
  resodesc: string | null;
  resocomplete: string | null;
  resoclosed: string | null;
  costofdefect: number;
  actfound: string | null;
}

export interface DefectReport {
  ID: number;
  Product: string;
  product_id: number | null;
  Environment: string | null;
  CustomerID: number | null;
  Who: string;
  Contactemail: string;
  Whenreported: string;
  Title: string;
  Description: string;
  Discoveredby: string | null;
  Persontype: PersonType;
  Occured: string;
  Location: string | null;
  Severity: DefectSeverity;
  Priority: DefectPriority;
  PriorityLabel?: string;
  Submitted: string;
  days_since_submitted?: number;
  Addinfo: string | null;
  is_deleted_user?: number;
  classification?: string | null;
  analysis_status?: DefectStatus;
  fixeffort?: number | null;
  fixcost?: number | null;
  costofdefect?: number | null;
  analysis?: DefectAnalysis | null;
}

export interface AnalyzerQueueItem extends DefectAnalysis {
  product: string;
  title: string;
  report_description: string;
  environment: string | null;
  occured: string;
  contact_email: string;
  who: string;
  classification_color: string;
  days_open: number;
  submitted_to_assigned_days: number | null;
  submitted_to_fixed_days: number | null;
  submitted_to_resolved_days: number | null;
  assigned_to_fixed_days: number | null;
  assigned_to_resolved_days: number | null;
}
