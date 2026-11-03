import React, { useCallback, useEffect, useState } from 'react';
import { defmanApi } from '../api/client';
import {
  DefectSeverity,
  DefectStatus,
  DetectedEnvironment,
  type DoraTelemetrySummary,
  IEEE_1044_2009_TAXONOMY,
  IeeeFaultType,
  OriginPhase,
  SymptomCategory,
  Uniqueness,
} from '../types/defman';
import { DefectResolutionForm } from './DefectResolutionForm';

export interface DefectAnalyzerWorkbenchProps {
  defectId: number;
  onClose?: () => void;
  onSaved?: () => void;
}

function formatHoursMetric(hours: number | null | undefined): string {
  if (hours === null || hours === undefined || Number.isNaN(Number(hours))) {
    return '—';
  }
  const numeric = Number(hours);
  if (numeric < 24) {
    return `${numeric.toFixed(1)}h`;
  }
  const days = (numeric / 24).toFixed(1);
  return `${numeric.toFixed(1)}h (${days}d)`;
}

/**
 * <DefectAnalyzerWorkbench />
 * IEEE 1044-2009 & DORA Defect Telemetry Workbench
 * Replaces obsolete 1992 IEEE Fault & Cost Analytics and phase-cost multipliers with:
 * - MTTR (resolved_at - reported_at)
 * - Triage Lead Time (triaged_at - reported_at)
 * - Production Escape Rate ('PROD' vs 'DEV' / 'CI' / 'STAGING')
 * - Regression Ratio (is_regression)
 * - Root Cause Effort Breakdown (IEEE Std 1044-2009)
 * Endpoint: PUT /api/defects/{id}/analyze
 */
export function DefectAnalyzerWorkbench({
  defectId,
  onClose,
  onSaved,
}: DefectAnalyzerWorkbenchProps): React.ReactElement {
  const [activeTab, setActiveTab] = useState<'analyze' | 'resolve'>('analyze');
  const [showTaxonomyModal, setShowTaxonomyModal] = useState<boolean>(false);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [telemetry, setTelemetry] = useState<DoraTelemetrySummary | null>(null);
  const [defectTimings, setDefectTimings] = useState<{
    triageLeadTimeHours: number | null;
    mttrHours: number | null;
  }>({
    triageLeadTimeHours: null,
    mttrHours: null,
  });

  const [form, setForm] = useState({
    devdesc: '',
    symptom_category: SymptomCategory.CRASH_HANG as string,
    root_cause_type: IeeeFaultType.CODE_LOGIC as string,
    classification: IeeeFaultType.CODE_LOGIC as string,
    classification_other: '',
    detected_environment: DetectedEnvironment.PROD as string,
    is_regression: false,
    introducing_ref: '',
    triaged_at: new Date().toISOString().slice(0, 16),
    dateoriginated: new Date().toISOString().slice(0, 10),
    phases: [OriginPhase.IMPLEMENTATION] as string[],
    actoriginated: '',
    deliverable: '',
    actfound: '',
    submitted: new Date().toISOString().slice(0, 10),
    assigned: new Date().toISOString().slice(0, 10),
    uniqueness: Uniqueness.NEW_DEFECT as string,
    severity: DefectSeverity.MAJOR,
    priority: 'most urgent',
    workaround: '',
    proposedfix: '',
    status: DefectStatus.OPENED,
  });

  const loadDefectTelemetryAndAnalysis = useCallback(async function fetchWorkbenchState(): Promise<void> {
    const res = await defmanApi.getDefectById(defectId);
    if (res.telemetry) {
      setTelemetry(res.telemetry);
    }
    if (res.analysis) {
      const a = res.analysis;
      const candidateRootCause = (a.root_cause_type || a.classification || IeeeFaultType.CODE_LOGIC) as string;
      const isStandardEnum = Object.values(IeeeFaultType).includes(
        candidateRootCause as IeeeFaultType
      );
      setDefectTimings({
        triageLeadTimeHours: a.triage_lead_time_hours ?? null,
        mttrHours: a.mttr_hours ?? null,
      });
      setForm({
        devdesc: a.devdesc || '',
        symptom_category: (a.symptom_category || res.symptom_category || SymptomCategory.CRASH_HANG) as string,
        root_cause_type: isStandardEnum ? candidateRootCause : IeeeFaultType.OTHER,
        classification: isStandardEnum ? candidateRootCause : IeeeFaultType.OTHER,
        classification_other: isStandardEnum ? '' : candidateRootCause,
        detected_environment: (a.detected_environment || res.detected_environment || DetectedEnvironment.PROD) as string,
        is_regression: Boolean(a.is_regression ?? res.is_regression),
        introducing_ref: a.introducing_ref || '',
        triaged_at: a.triaged_at
          ? a.triaged_at.replace(' ', 'T').slice(0, 16)
          : new Date().toISOString().slice(0, 16),
        dateoriginated: a.dateoriginated || res.Occured,
        phases: a.phase ? a.phase.split(',').filter(Boolean) : [],
        actoriginated: a.actoriginated || '',
        deliverable: a.deliverable || '',
        actfound: a.actfound || '',
        submitted: a.submitted || res.Submitted,
        assigned: a.assigned || new Date().toISOString().slice(0, 10),
        uniqueness: (a.uniqueness || Uniqueness.NEW_DEFECT) as string,
        severity: a.severity || res.Severity,
        priority: a.priority || 'most urgent',
        workaround: a.workaround || '',
        proposedfix: a.proposedfix || '',
        status: a.status || DefectStatus.OPENED,
      });
    } else {
      setForm((prev) => ({
        ...prev,
        symptom_category: (res.symptom_category || SymptomCategory.CRASH_HANG) as string,
        detected_environment: (res.detected_environment || DetectedEnvironment.PROD) as string,
        is_regression: Boolean(res.is_regression),
        dateoriginated: res.Occured,
        submitted: res.Submitted,
        severity: res.Severity,
      }));
    }
  }, [defectId]);

  useEffect(function initWorkbenchEffect() {
    loadDefectTelemetryAndAnalysis();
  }, [loadDefectTelemetryAndAnalysis]);

  function togglePhase(phase: string): void {
    setForm(function updatePhases(prev) {
      return {
        ...prev,
        phases: prev.phases.includes(phase)
          ? prev.phases.filter(function filterPhase(p) {
              return p !== phase;
            })
          : [...prev.phases, phase],
      };
    });
  }

  function handleToggleTaxonomyModal(): void {
    setShowTaxonomyModal(function flipModal(prev) {
      return !prev;
    });
  }

  async function handleAnalyzeSubmit(e: React.FormEvent): Promise<void> {
    e.preventDefault();
    setErrorMessage(null);
    setStatusMessage(null);
    try {
      const payload = {
        ...form,
        classification:
          form.classification_other.trim().length > 0
            ? form.classification_other.trim()
            : form.root_cause_type,
      };
      const res = await defmanApi.analyzeDefect(defectId, payload);
      setStatusMessage(res.message);
      await loadDefectTelemetryAndAnalysis();
      if (onSaved) {
        onSaved();
      }
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Failed to save IEEE 1044-2009 analysis');
    }
  }

  async function handleResolutionSaved(): Promise<void> {
    await loadDefectTelemetryAndAnalysis();
    if (onSaved) {
      onSaved();
    }
  }

  return (
    <div className="bg-slate-900 border border-slate-700 rounded-xl p-6 text-slate-100 shadow-xl space-y-5">
      {/* Header & Navigation Bar */}
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 pb-3">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-lg font-bold text-amber-400">
              IEEE 1044-2009 &amp; DORA Defect Telemetry — Defect ID #{defectId}
            </h2>
            <span className="text-[11px] px-2 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-700 font-mono">
              {form.detected_environment}
            </span>
            {form.is_regression && (
              <span className="text-[11px] px-2 py-0.5 rounded bg-amber-950 text-amber-300 border border-amber-600 font-semibold">
                REGRESSION
              </span>
            )}
          </div>
          <div className="flex items-center gap-2 mt-2 text-xs">
            <button
              type="button"
              onClick={function selectAnalyzeTab() {
                setActiveTab('analyze');
              }}
              className={`px-3 py-1 rounded font-semibold ${
                activeTab === 'analyze'
                  ? 'bg-amber-500 text-slate-950'
                  : 'bg-slate-800 text-slate-300'
              }`}
            >
              1. IEEE 1044-2009 Anomaly Triage &amp; Classification
            </button>
            <button
              type="button"
              onClick={function selectResolveTab() {
                setActiveTab('resolve');
              }}
              className={`px-3 py-1 rounded font-semibold ${
                activeTab === 'resolve'
                  ? 'bg-amber-500 text-slate-950'
                  : 'bg-slate-800 text-slate-300'
              }`}
            >
              2. Resolution Ref, Root Cause &amp; Actual Effort Hours
            </button>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={handleToggleTaxonomyModal}
            className="text-xs px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-amber-300 border border-slate-600"
          >
            {showTaxonomyModal
              ? 'Hide IEEE 1044-2009 Taxonomy'
              : 'IEEE 1044-2009 Anomaly Reference'}
          </button>
          {onClose && (
            <button
              type="button"
              onClick={onClose}
              className="text-xs px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300"
            >
              Close
            </button>
          )}
        </div>
      </div>

      {/* IEEE 1044-2009 & DORA Defect Telemetry Summary Cards */}
      {telemetry && (
        <div className="space-y-3 bg-slate-950/80 border border-slate-800 rounded-xl p-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <span className="text-xs font-bold uppercase tracking-wider text-amber-400">
              IEEE 1044-2009 &amp; DORA Defect Telemetry
            </span>
            <span className="text-[11px] text-slate-400 font-mono">
              This Defect Triage Lead: {formatHoursMetric(defectTimings.triageLeadTimeHours)} • This Defect MTTR: {formatHoursMetric(defectTimings.mttrHours)}
            </span>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div className="p-3 rounded-lg bg-slate-900 border border-slate-800">
              <div className="text-slate-400">MTTR (Resolved − Reported)</div>
              <div className="text-lg font-bold text-emerald-400 font-mono mt-0.5">
                {formatHoursMetric(telemetry.mttr_hours)}
              </div>
              <div className="text-[11px] text-slate-500">
                 Triage Lead Time: {formatHoursMetric(telemetry.triage_lead_time_hours)}
              </div>
            </div>

            <div className="p-3 rounded-lg bg-slate-900 border border-slate-800">
              <div className="text-slate-400">Production Escape Rate</div>
              <div className="text-lg font-bold text-rose-400 font-mono mt-0.5">
                {telemetry.production_escape_rate_pct.toFixed(1)}%
              </div>
              <div className="text-[11px] text-slate-500">
                PROD: {telemetry.environment_counts?.PROD ?? telemetry.prod_escape_count} • STAGING: {telemetry.environment_counts?.STAGING ?? 0} • CI: {telemetry.environment_counts?.CI ?? 0} • DEV: {telemetry.environment_counts?.DEV ?? 0}
              </div>
            </div>

            <div className="p-3 rounded-lg bg-slate-900 border border-slate-800">
              <div className="text-slate-400">Regression Ratio</div>
              <div className="text-lg font-bold text-amber-400 font-mono mt-0.5">
                {telemetry.regression_rate_pct.toFixed(1)}%
              </div>
              <div className="text-[11px] text-slate-500">
                {telemetry.regression_count} regressions of {telemetry.total_defects} anomalies
              </div>
            </div>

            <div className="p-3 rounded-lg bg-slate-900 border border-slate-800">
              <div className="text-slate-400">Actual Engineering Effort</div>
              <div className="text-lg font-bold text-sky-400 font-mono mt-0.5">
                {telemetry.total_actual_effort_hours.toFixed(2)}h
              </div>
              <div className="text-[11px] text-slate-500">
                Avg {telemetry.avg_actual_effort_hours.toFixed(2)}h / anomaly ({telemetry.resolved_count} resolved)
              </div>
            </div>
          </div>

          {/* Root Cause Effort Breakdown Table */}
          {telemetry.root_cause_effort_breakdown.length > 0 && (
            <div className="pt-1">
              <div className="text-[11px] font-semibold text-slate-300 mb-1.5">
                Root Cause Effort Breakdown (IEEE Std 1044-2009)
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-[11px] border-collapse">
                  <thead>
                    <tr className="border-b border-slate-800 text-slate-400">
                      <th className="py-1 pr-3">IEEE 1044-2009 Root Cause</th>
                      <th className="py-1 px-2">Anomalies</th>
                      <th className="py-1 px-2">Total Effort (h)</th>
                      <th className="py-1 px-2">Avg Effort (h)</th>
                      <th className="py-1 px-2">Avg MTTR</th>
                      <th className="py-1 px-2">PROD Escapes</th>
                      <th className="py-1 pl-2">Regressions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-900">
                    {telemetry.root_cause_effort_breakdown.map(function renderBreakdownRow(item) {
                      return (
                        <tr key={item.root_cause_type} className="text-slate-200">
                          <td className="py-1 pr-3 font-medium text-amber-300">
                            {item.root_cause_type}
                          </td>
                          <td className="py-1 px-2 font-mono">{item.defect_count}</td>
                          <td className="py-1 px-2 font-mono">{item.total_effort_hours.toFixed(2)}h</td>
                          <td className="py-1 px-2 font-mono">{item.avg_effort_hours.toFixed(2)}h</td>
                          <td className="py-1 px-2 font-mono">{formatHoursMetric(item.avg_mttr_hours)}</td>
                          <td className="py-1 px-2 font-mono">{item.prod_escape_count}</td>
                          <td className="py-1 pl-2 font-mono">{item.regression_count}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      )}

      {showTaxonomyModal && (
        <div className="p-4 rounded-lg bg-slate-950 border border-amber-500/40 text-xs grid grid-cols-1 md:grid-cols-2 gap-3 max-h-60 overflow-y-auto">
          {Object.entries(IEEE_1044_2009_TAXONOMY).map(function renderTaxonomyGroup([category, subs]) {
            return (
              <div key={category} className="space-y-1">
                <div className="font-bold text-amber-400">{category}</div>
                <ul className="list-disc list-inside text-slate-400 space-y-0.5">
                  {subs.map(function renderSubcategory(s) {
                    return <li key={s}>{s}</li>;
                  })}
                </ul>
              </div>
            );
          })}
        </div>
      )}

      {activeTab === 'analyze' ? (
        <form onSubmit={handleAnalyzeSubmit} className="space-y-4 text-xs">
          {errorMessage && (
            <div className="p-3 rounded bg-red-950 border border-red-600 text-amber-300">
              {errorMessage}
            </div>
          )}
          {statusMessage && (
            <div className="p-3 rounded bg-emerald-950 border border-emerald-600 text-emerald-300">
              {statusMessage}
            </div>
          )}

          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <label className="block md:col-span-2">
              <span className="text-slate-300">
                Engineering anomaly analysis <span className="text-red-400">*</span>
              </span>
              <input
                type="text"
                required
                value={form.devdesc}
                onChange={function onDevDescChange(e) {
                  setForm({ ...form, devdesc: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>

            <label className="block">
              <span className="text-slate-300">
                Workflow status <span className="text-red-400">*</span>
              </span>
              <select
                value={form.status}
                onChange={function onStatusChange(e) {
                  setForm({ ...form, status: e.target.value as DefectStatus });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              >
                {Object.values(DefectStatus).map(function renderStatusOption(st) {
                  return (
                    <option key={st} value={st}>
                      {st}
                    </option>
                  );
                })}
              </select>
            </label>
          </div>

          {/* IEEE Std 1044-2009 Symptom Category, Root Cause & Detection Environment */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3 bg-slate-950/60 p-3 rounded-lg border border-slate-800">
            <label className="block">
              <span className="text-slate-300">
                Observed Symptom Category (IEEE 1044-2009) <span className="text-red-400">*</span>
              </span>
              <select
                value={form.symptom_category}
                onChange={function onSymptomChange(e) {
                  setForm({ ...form, symptom_category: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              >
                {Object.values(SymptomCategory).map(function renderSymptomOption(sc) {
                  return (
                    <option key={sc} value={sc}>
                      {sc}
                    </option>
                  );
                })}
              </select>
            </label>

            <label className="block">
              <span className="text-slate-300">
                Root Cause Type (IEEE 1044-2009) <span className="text-red-400">*</span>
              </span>
              <select
                disabled={form.classification_other.trim().length > 0}
                value={
                  form.classification_other.trim().length > 0
                    ? IeeeFaultType.OTHER
                    : form.root_cause_type
                }
                onChange={function onRootCauseChange(e) {
                  setForm({
                    ...form,
                    root_cause_type: e.target.value,
                    classification: e.target.value,
                  });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5 disabled:opacity-50"
              >
                {Object.values(IeeeFaultType).map(function renderRootCauseOption(ft) {
                  return (
                    <option key={ft} value={ft}>
                      {ft}
                    </option>
                  );
                })}
              </select>
            </label>

            <label className="block">
              <span className="text-slate-300">
                Custom Root Cause Override (optional)
              </span>
              <input
                type="text"
                value={form.classification_other}
                onChange={function onCustomClassificationChange(e) {
                  setForm({
                    ...form,
                    classification_other: e.target.value,
                    root_cause_type:
                      e.target.value.length > 0
                        ? IeeeFaultType.OTHER
                        : form.root_cause_type,
                  });
                }}
                placeholder="Custom IEEE 1044-2009 root cause..."
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
          </div>

          {/* DORA Environment Escape, Regression Flag, Introducing Commit/Build Ref, Triage Timestamp */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-3 bg-slate-950/60 p-3 rounded-lg border border-slate-800">
            <label className="block">
              <span className="text-slate-300">
                Detected Environment <span className="text-red-400">*</span>
              </span>
              <select
                value={form.detected_environment}
                onChange={function onDetectedEnvChange(e) {
                  setForm({ ...form, detected_environment: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5 font-mono"
              >
                {Object.values(DetectedEnvironment).map(function renderEnvOption(env) {
                  return (
                    <option key={env} value={env}>
                      {env}
                    </option>
                  );
                })}
              </select>
            </label>

            <label className="block">
              <span className="text-slate-300">Introducing Commit / Build Ref</span>
              <input
                type="text"
                value={form.introducing_ref}
                onChange={function onIntroducingRefChange(e) {
                  setForm({ ...form, introducing_ref: e.target.value });
                }}
                placeholder="e.g. git:a4f9c21 (build-1502)"
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5 font-mono"
              />
            </label>

            <label className="block">
              <span className="text-slate-300">
                Triaged Timestamp (DORA Lead Time)
              </span>
              <input
                type="datetime-local"
                value={form.triaged_at}
                onChange={function onTriagedAtChange(e) {
                  setForm({ ...form, triaged_at: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5 font-mono"
              />
            </label>

            <label className="flex items-center gap-2 pt-5 cursor-pointer">
              <input
                type="checkbox"
                checked={form.is_regression}
                onChange={function onRegressionToggle(e) {
                  setForm({ ...form, is_regression: e.target.checked });
                }}
                className="rounded border-slate-600"
              />
              <span className="text-slate-200 font-semibold">
                Regression Defect (is_regression)
              </span>
            </label>
          </div>

          {/* Phase Originated Checkboxes */}
          <div>
            <span className="text-slate-300 block mb-1.5">
              SDLC Phase Originated <span className="text-red-400">*</span>
            </span>
            <div className="grid grid-cols-2 md:grid-cols-6 gap-2 bg-slate-950/60 p-3 rounded-lg border border-slate-800">
              {Object.values(OriginPhase).map(function renderPhaseCheckbox(phase) {
                return (
                  <label
                    key={phase}
                    className="flex items-center gap-2 cursor-pointer"
                  >
                    <input
                      type="checkbox"
                      checked={form.phases.includes(phase)}
                      onChange={function onPhaseCheckboxChange() {
                        togglePhase(phase);
                      }}
                      className="rounded border-slate-600"
                    />
                    <span>{phase}</span>
                  </label>
                );
              })}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <label className="block">
              <span className="text-slate-300">
                Deliverable / Module Originated <span className="text-red-400">*</span>
              </span>
              <input
                type="text"
                required
                value={form.deliverable}
                onChange={function onDeliverableChange(e) {
                  setForm({ ...form, deliverable: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-300">
                Originating Engineering Activity <span className="text-red-400">*</span>
              </span>
              <input
                type="text"
                required
                value={form.actoriginated}
                onChange={function onActOriginatedChange(e) {
                  setForm({ ...form, actoriginated: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-300">
                Target Verification / Detection Gate <span className="text-red-400">*</span>
              </span>
              <input
                type="text"
                required
                value={form.actfound}
                onChange={function onActFoundChange(e) {
                  setForm({ ...form, actfound: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <label className="block">
              <span className="text-slate-300">
                Uniqueness <span className="text-red-400">*</span>
              </span>
              <select
                value={form.uniqueness}
                onChange={function onUniquenessChange(e) {
                  setForm({ ...form, uniqueness: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              >
                {Object.values(Uniqueness).map(function renderUniquenessOption(u) {
                  return (
                    <option key={u} value={u}>
                      {u}
                    </option>
                  );
                })}
              </select>
            </label>
            <label className="block">
              <span className="text-slate-300">
                Triaged Severity <span className="text-red-400">*</span>
              </span>
              <select
                value={form.severity}
                onChange={function onSeverityChange(e) {
                  setForm({
                    ...form,
                    severity: e.target.value as DefectSeverity,
                  });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              >
                <option value={DefectSeverity.MINOR}>minor</option>
                <option value={DefectSeverity.MAJOR}>major</option>
                <option value={DefectSeverity.CRITICAL}>critical</option>
              </select>
            </label>
            <label className="block">
              <span className="text-slate-300">
                Triaged Priority <span className="text-red-400">*</span>
              </span>
              <select
                value={form.priority}
                onChange={function onPriorityChange(e) {
                  setForm({ ...form, priority: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              >
                <option value="most urgent">most urgent</option>
                <option value="average">average</option>
                <option value="less urgent">less urgent</option>
              </select>
            </label>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <label className="block">
              <span className="text-slate-300">Mitigation / Workaround</span>
              <textarea
                rows={2}
                value={form.workaround}
                onChange={function onWorkaroundChange(e) {
                  setForm({ ...form, workaround: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-300">
                Proposed Engineering Remediation <span className="text-red-400">*</span>
              </span>
              <textarea
                rows={2}
                required
                value={form.proposedfix}
                onChange={function onProposedFixChange(e) {
                  setForm({ ...form, proposedfix: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
          </div>

          <div className="flex items-center justify-end gap-3 pt-2">
            <button
              type="submit"
              className="px-4 py-2 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
            >
              Save IEEE 1044-2009 Triage &amp; Telemetry
            </button>
          </div>
        </form>
      ) : (
        <DefectResolutionForm defectId={defectId} onSaved={handleResolutionSaved} />
      )}
    </div>
  );
}
