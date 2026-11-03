import React, { useEffect, useState } from 'react';
import { defmanApi } from '../api/client';
import { DefectStatus, IeeeFaultType } from '../types/defman';

export interface DefectResolutionFormProps {
  defectId: number;
  onSaved?: () => void;
}

/**
 * <DefectResolutionForm />
 * Captures IEEE Std 1044-2009 `root_cause_type`, `resolution_ref` (commit/build/PR ref),
 * `actual_effort_hours`, and `resolved_at` for DORA MTTR and Root Cause Effort telemetry.
 * Endpoint: PUT /api/defects/{id}/resolve
 */
export function DefectResolutionForm({
  defectId,
  onSaved,
}: DefectResolutionFormProps): React.ReactElement {
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const [form, setForm] = useState({
    status: DefectStatus.RESOLVED,
    root_cause_type: IeeeFaultType.CODE_LOGIC as string,
    introducing_ref: '',
    resolution_ref: '',
    actual_effort_hours: 2.5,
    is_regression: false,
    resolved_at: new Date().toISOString().slice(0, 16),
    fixdesc: '',
    fixstarted: new Date().toISOString().slice(0, 10),
    fixcomplete: new Date().toISOString().slice(0, 10),
    fiximpact: '',
    resodesc: '',
    resocomplete: new Date().toISOString().slice(0, 10),
    resoclosed: new Date().toISOString().slice(0, 10),
  });

  useEffect(function loadResolutionDetailsEffect() {
    defmanApi.getDefectById(defectId).then(function handleDefectResponse(res) {
      if (res.analysis) {
        const a = res.analysis;
        const effortHours = Number(a.actual_effort_hours ?? a.fixeffort ?? 2.5);
        setForm({
          status: a.status || DefectStatus.RESOLVED,
          root_cause_type: (a.root_cause_type || a.classification || IeeeFaultType.CODE_LOGIC) as string,
          introducing_ref: a.introducing_ref || '',
          resolution_ref: a.resolution_ref || '',
          actual_effort_hours: effortHours,
          is_regression: Boolean(a.is_regression ?? res.is_regression),
          resolved_at: a.resolved_at
            ? a.resolved_at.replace(' ', 'T').slice(0, 16)
            : new Date().toISOString().slice(0, 16),
          fixdesc: a.fixdesc || '',
          fixstarted: a.fixstarted || new Date().toISOString().slice(0, 10),
          fixcomplete: a.fixcomplete || new Date().toISOString().slice(0, 10),
          fiximpact: a.fiximpact || '',
          resodesc: a.resodesc || '',
          resocomplete: a.resocomplete || new Date().toISOString().slice(0, 10),
          resoclosed: a.resoclosed || new Date().toISOString().slice(0, 10),
        });
      }
    });
  }, [defectId]);

  function handleEffortChange(effortHours: number): void {
    setForm(function updateEffortState(prev) {
      return {
        ...prev,
        actual_effort_hours: effortHours,
      };
    });
  }

  async function handleSubmit(e: React.FormEvent): Promise<void> {
    e.preventDefault();
    setErrorMessage(null);
    setStatusMessage(null);
    try {
      const res = await defmanApi.resolveDefect(defectId, {
        ...form,
        fixeffort: form.actual_effort_hours,
      });
      setStatusMessage(res.message);
      if (onSaved) {
        onSaved();
      }
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Resolution save failed');
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4 text-xs">
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

      {/* Section 1: IEEE 1044-2009 Root Cause, Build/Commit Ref & Actual Effort Hours */}
      <div className="p-4 rounded-lg bg-slate-950/70 border border-slate-800 space-y-3">
        <div className="font-bold text-amber-400">
          Part A — IEEE 1044-2009 Root Cause, Resolution Ref &amp; Actual Effort Telemetry
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
          <label className="block">
            <span className="text-slate-300">
              IEEE 1044-2009 Root Cause Type <span className="text-red-400">*</span>
            </span>
            <select
              value={form.root_cause_type}
              onChange={function onRootCauseChange(e) {
                setForm({ ...form, root_cause_type: e.target.value });
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            >
              {Object.values(IeeeFaultType).map(function renderRootCauseOption(rc) {
                return (
                  <option key={rc} value={rc}>
                    {rc}
                  </option>
                );
              })}
            </select>
          </label>

          <label className="block">
            <span className="text-slate-300">
              Resolution Commit / PR / Release Ref <span className="text-red-400">*</span>
            </span>
            <input
              type="text"
              required
              value={form.resolution_ref}
              onChange={function onResolutionRefChange(e) {
                setForm({ ...form, resolution_ref: e.target.value });
              }}
              placeholder="e.g. git:e83a11f (pr-412 / v1.5.3)"
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5 font-mono"
            />
          </label>

          <label className="block">
            <span className="text-slate-300">
              Introducing Commit / Build Ref
            </span>
            <input
              type="text"
              value={form.introducing_ref}
              onChange={function onIntroducingRefChange(e) {
                setForm({ ...form, introducing_ref: e.target.value });
              }}
              placeholder="e.g. git:a4f9c21 (build-1502)"
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5 font-mono"
            />
          </label>

          <label className="block">
            <span className="text-slate-300">
              Actual Effort Hours <span className="text-red-400">*</span>
            </span>
            <input
              type="number"
              step="0.25"
              min="0"
              required
              value={form.actual_effort_hours}
              onChange={function onActualEffortInput(e) {
                handleEffortChange(Number(e.target.value));
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5 font-mono"
            />
          </label>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <label className="block">
            <span className="text-slate-300">Description of engineering fix *</span>
            <textarea
              rows={2}
              required
              value={form.fixdesc}
              onChange={function onFixDescChange(e) {
                setForm({ ...form, fixdesc: e.target.value });
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Modules / Deliverables impacted by fix</span>
            <textarea
              rows={2}
              value={form.fiximpact}
              onChange={function onFixImpactChange(e) {
                setForm({ ...form, fiximpact: e.target.value });
              }}
              placeholder="e.g. settings.cpp, popup.cpp, AnalysisRepository.php"
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <label className="block">
            <span className="text-slate-300">Date work started *</span>
            <input
              type="date"
              required
              value={form.fixstarted}
              onChange={function onFixStartedChange(e) {
                setForm({ ...form, fixstarted: e.target.value });
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Date work completed *</span>
            <input
              type="date"
              required
              value={form.fixcomplete}
              onChange={function onFixCompleteChange(e) {
                setForm({ ...form, fixcomplete: e.target.value });
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            />
          </label>
          <label className="flex items-center gap-2 pt-5 cursor-pointer">
            <input
              type="checkbox"
              checked={form.is_regression}
              onChange={function onRegressionChange(e) {
                setForm({ ...form, is_regression: e.target.checked });
              }}
              className="rounded border-slate-600"
            />
            <span className="text-slate-200 font-semibold">
              Regression Anomaly (is_regression)
            </span>
          </label>
        </div>
      </div>

      {/* Section 2: DORA Resolution Timestamp & Verification Status */}
      <div className="p-4 rounded-lg bg-slate-950/70 border border-slate-800 space-y-3">
        <div className="font-bold text-amber-400">
          Part B — DORA Resolution Verification &amp; MTTR Timestamp
        </div>
        <label className="block">
          <span className="text-slate-300">Resolution &amp; verification summary *</span>
          <textarea
            rows={2}
            required
            value={form.resodesc}
            onChange={function onResoDescChange(e) {
              setForm({ ...form, resodesc: e.target.value });
            }}
            className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
          />
        </label>

        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <label className="block">
            <span className="text-slate-300">Resolution Status *</span>
            <select
              value={form.status}
              onChange={function onStatusSelectChange(e) {
                setForm({ ...form, status: e.target.value as DefectStatus });
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            >
              {Object.values(DefectStatus).map(function renderDefectStatusOption(st) {
                return (
                  <option key={st} value={st}>
                    {st}
                  </option>
                );
              })}
            </select>
          </label>
          <label className="block">
            <span className="text-slate-300">Resolved At (DORA MTTR) *</span>
            <input
              type="datetime-local"
              required
              value={form.resolved_at}
              onChange={function onResolvedAtChange(e) {
                setForm({
                  ...form,
                  resolved_at: e.target.value,
                  resocomplete: e.target.value.slice(0, 10),
                });
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5 font-mono"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Date of resolution *</span>
            <input
              type="date"
              required
              value={form.resocomplete}
              onChange={function onResoCompleteChange(e) {
                setForm({ ...form, resocomplete: e.target.value });
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Date closed *</span>
            <input
              type="date"
              required
              value={form.resoclosed}
              onChange={function onResoClosedChange(e) {
                setForm({ ...form, resoclosed: e.target.value });
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            />
          </label>
        </div>
      </div>

      <div className="flex justify-end">
        <button
          type="submit"
          className="px-4 py-2 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
        >
          Save IEEE 1044-2009 Resolution &amp; Effort Telemetry
        </button>
      </div>
    </form>
  );
}
