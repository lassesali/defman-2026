/**
 * IEEE Std 1044-2009 Standard Classification for Software Anomalies (Root Cause & Defect Type)
 * Replaces obsolete 1992 IEEE Fault & Cost Analytics while preserving legacy key compatibility
 */
export enum IeeeFaultType {
  CODE_LOGIC            = 'Code / Logic',
  COMPUTATIONAL         = 'Computational',
  INTERFACE_INTEGRATION = 'Interface / Integration',
  DATA_SCHEMA           = 'Data / Schema',
  CONCURRENCY_TIMING    = 'Concurrency / Timing',
  CONFIGURATION_ENV     = 'Configuration / Environment',
  SECURITY_ACCESS       = 'Security / Access Control',
  REQUIREMENTS_SPEC     = 'Requirements / Specification',
  ARCHITECTURE_DESIGN   = 'Architecture / Design',
  DOCUMENTATION         = 'Documentation',
  REGRESSION            = 'Regression',
  OTHER                 = 'Other',
}

export type Ieee1044RootCause = IeeeFaultType;

/**
 * IEEE Std 1044-2009 Observed Symptom / Failure Effect Classification
 */
export enum SymptomCategory {
  CRASH_HANG              = 'Application Crash / Hang',
  DATA_CORRUPTION         = 'Data Corruption / Loss',
  INCORRECT_OUTPUT        = 'Incorrect Calculation / Output',
  AUTH_SECURITY           = 'Authentication / Security Failure',
  UI_RENDERING            = 'UI / Rendering Anomaly',
  PERFORMANCE_DEGRADATION = 'Performance / Latency Degradation',
  INTEGRATION_FAILURE     = 'API / Integration Failure',
  OTHER                   = 'Other Symptom',
}

/**
 * DORA / Flow Engineering Detection Environment (`detected_environment`)
 */
export enum DetectedEnvironment {
  DEV     = 'DEV',
  CI      = 'CI',
  STAGING = 'STAGING',
  PROD    = 'PROD',
}

export const IEEE_FAULT_HEX_COLORS: Record<string, number> = {
  [IeeeFaultType.CODE_LOGIC]:            0xef4444, // Red-500
  [IeeeFaultType.COMPUTATIONAL]:         0xf97316, // Orange-500
  [IeeeFaultType.INTERFACE_INTEGRATION]: 0xec4899, // Pink-500
  [IeeeFaultType.DATA_SCHEMA]:           0x8b5cf6, // Violet-500
  [IeeeFaultType.CONCURRENCY_TIMING]:    0x6366f1, // Indigo-500
  [IeeeFaultType.CONFIGURATION_ENV]:     0x0ea5e9, // Sky-500
  [IeeeFaultType.SECURITY_ACCESS]:       0xe11d48, // Rose-600
  [IeeeFaultType.REQUIREMENTS_SPEC]:     0x10b981, // Emerald-500
  [IeeeFaultType.ARCHITECTURE_DESIGN]:   0x14b8a6, // Teal-500
  [IeeeFaultType.DOCUMENTATION]:         0x06b6d4, // Cyan-500
  [IeeeFaultType.REGRESSION]:            0xf59e0b, // Amber-500
  [IeeeFaultType.OTHER]:                 0x64748b, // Slate-500
  // Legacy 1992 color fallbacks
  Logic:                                 0xef4444,
  Interface:                             0xec4899,
  'Data handling':                       0x8b5cf6,
  Data:                                  0x6366f1,
  'Document quality':                    0x14b8a6,
  Enhancement:                           0x10b981,
  'Caused by a prev. Fix':               0xf59e0b,
};

/**
 * IEEE Std 1044-2009 Root Cause Taxonomy
 */
export const IEEE_1044_2009_TAXONOMY: Record<IeeeFaultType, readonly string[]> = {
  [IeeeFaultType.CODE_LOGIC]: [
    'Control flow or branch condition error',
    'Null / uninitialized reference dereference',
    'Boundary or off-by-one loop condition',
    'Cyclic state or recursion termination fault',
    'Missing exception or error handler',
  ],
  [IeeeFaultType.COMPUTATIONAL]: [
    'Precision loss, rounding, or numeric truncation fault',
    'Incorrect formula, operator, or operand precedence',
    'Unit conversion or scaling mismatch',
    'Overflow / underflow in arithmetic calculation',
  ],
  [IeeeFaultType.INTERFACE_INTEGRATION]: [
    'API contract or payload schema mismatch',
    'Session / protocol state desynchronization',
    'Module call parameter or return-type mismatch',
    'Upstream / downstream service timeout handling',
  ],
  [IeeeFaultType.DATA_SCHEMA]: [
    'Database schema constraint or column type mismatch',
    'Incorrect data serialization or encoding',
    'Stale cache or out-of-bounds buffer access',
    'Missing transaction isolation or migration rule',
  ],
  [IeeeFaultType.CONCURRENCY_TIMING]: [
    'Race condition or unsynchronized shared state',
    'Deadlock or lock contention under load',
    'Asynchronous event ordering violation',
  ],
  [IeeeFaultType.CONFIGURATION_ENV]: [
    'Environment variable or secret misconfiguration',
    'Container / runtime dependency drift',
    'Infrastructure or deployment manifest fault',
  ],
  [IeeeFaultType.SECURITY_ACCESS]: [
    'Authentication token or session lifecycle flaw',
    'RBAC authorization bypass or privilege escalation',
    'Input sanitization / injection vulnerability',
  ],
  [IeeeFaultType.REQUIREMENTS_SPEC]: [
    'Ambiguous or incomplete acceptance criteria',
    'Unaccounted edge case in domain specification',
    'Behavioral change in product requirements',
  ],
  [IeeeFaultType.ARCHITECTURE_DESIGN]: [
    'Coupling or component boundary design flaw',
    'Scalability or throughput bottleneck in design',
    'Missing idempotency or fallback architecture',
  ],
  [IeeeFaultType.DOCUMENTATION]: [
    'Inaccurate API or operational runbook documentation',
    'Outdated configuration or upgrade instructions',
  ],
  [IeeeFaultType.REGRESSION]: [
    'Regression introduced by a prior defect fix or refactor',
    'Unintended side effect of dependency or feature merge',
  ],
  [IeeeFaultType.OTHER]: [
    'Unclassified or external environment anomaly',
  ],
};

// Backward-compatible export alias
export const IEEE_1992_TAXONOMY = IEEE_1044_2009_TAXONOMY;

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

export interface RootCauseEffortBreakdownItem {
  root_cause_type: string;
  defect_count: number;
  total_effort_hours: number;
  avg_effort_hours: number;
  avg_mttr_hours: number | null;
  regression_count: number;
  prod_escape_count: number;
}

export interface DoraTelemetrySummary {
  total_defects: number;
  triaged_count: number;
  resolved_count: number;
  mttr_hours: number | null;
  triage_lead_time_hours: number | null;
  production_escape_rate_pct: number;
  prod_escape_count: number;
  regression_rate_pct: number;
  regression_count: number;
  total_actual_effort_hours: number;
  avg_actual_effort_hours: number;
  environment_counts: Record<string, number>;
  root_cause_effort_breakdown: RootCauseEffortBreakdownItem[];
}

export interface DefectAnalysis {
  id: number;
  devdesc: string | null;
  symptom_category?: SymptomCategory | string | null;
  root_cause_type?: IeeeFaultType | string | null;
  classification: IeeeFaultType | string;
  detected_environment?: DetectedEnvironment | string;
  is_regression?: boolean | number;
  introducing_ref?: string | null;
  resolution_ref?: string | null;
  actual_effort_hours?: number | null;
  triaged_at?: string | null;
  resolved_at?: string | null;
  triage_lead_time_hours?: number | null;
  mttr_hours?: number | null;
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
  fixcost?: number | null;
  resodesc: string | null;
  resocomplete: string | null;
  resoclosed: string | null;
  costofdefect?: number | null;
  actfound: string | null;
}

export interface DefectReport {
  ID: number;
  Product: string;
  product_id: number | null;
  Environment: string | null;
  detected_environment?: DetectedEnvironment | string;
  symptom_category?: SymptomCategory | string | null;
  is_regression?: boolean | number;
  CustomerID: number | null;
  Who: string;
  Contactemail: string;
  Whenreported: string;
  reported_at?: string;
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
  root_cause_type?: string | null;
  classification?: string | null;
  analysis_status?: DefectStatus;
  introducing_ref?: string | null;
  resolution_ref?: string | null;
  actual_effort_hours?: number | null;
  triaged_at?: string | null;
  resolved_at?: string | null;
  fixeffort?: number | null;
  fixcost?: number | null;
  costofdefect?: number | null;
  analysis?: DefectAnalysis | null;
  telemetry?: DoraTelemetrySummary | null;
}

export interface AnalyzerQueueItem extends DefectAnalysis {
  product: string;
  title: string;
  report_description: string;
  environment: string | null;
  reported_at?: string | null;
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
