import React, { useEffect, useState } from 'react';
import { defmanApi } from '../api/client';
import { DefectStatus } from '../types/defman';

/**
 * <DefectResolutionForm />
 * Refactored from: form_deffix.php & form_defreso.php
 * Endpoint: PUT /api/defects/{id}/resolve
 */
export const DefectResolutionForm: React.FC<{
  defectId: number;
  onSaved?: () => void;
}> = ({ defectId, onSaved }) => {
  const [hourlyRateEur, setHourlyRateEur] = useState<number>(30); // 2004 helpanalyze.html default: 30 EUR/h
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const [form, setForm] = useState({
    status: DefectStatus.RESOLVED,
    fixdesc: '',
    fixstarted: new Date().toISOString().slice(0, 10),
    fixcomplete: new Date().toISOString().slice(0, 10),
    fiximpact: '',
    fixeffort: 2,
    fixcost: 60,
    resodesc: '',
    resocomplete: new Date().toISOString().slice(0, 10),
    resoclosed: new Date().toISOString().slice(0, 10),
    costofdefect: 240,
  });

  useEffect(() => {
    defmanApi.getDefectById(defectId).then((res) => {
      if (res.analysis) {
        const a = res.analysis;
        setForm({
          status: a.status || DefectStatus.RESOLVED,
          fixdesc: a.fixdesc || '',
          fixstarted: a.fixstarted || new Date().toISOString().slice(0, 10),
          fixcomplete: a.fixcomplete || new Date().toISOString().slice(0, 10),
          fiximpact: a.fiximpact || '',
          fixeffort: Number(a.fixeffort ?? 2),
          fixcost: Number(a.fixcost ?? 60),
          resodesc: a.resodesc || '',
          resocomplete: a.resocomplete || new Date().toISOString().slice(0, 10),
          resoclosed: a.resoclosed || new Date().toISOString().slice(0, 10),
          costofdefect: Number(a.costofdefect ?? 240),
        });
      }
    });
  }, [defectId]);

  const handleEffortChange = (effortHours: number) => {
    setForm((prev) => ({
      ...prev,
      fixeffort: effortHours,
      fixcost: Number((effortHours * hourlyRateEur).toFixed(2)),
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);
    setStatusMessage(null);
    try {
      const res = await defmanApi.resolveDefect(defectId, form);
      setStatusMessage(res.message);
      onSaved?.();
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Resolution save failed');
    }
  };

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

      {/* Section 1: Defect Fix (form_deffix.php) */}
      <div className="p-4 rounded-lg bg-slate-950/70 border border-slate-800 space-y-3">
        <div className="font-bold text-amber-400">
          Part A — Defect Fix & Effort Tracking (form_deffix.php)
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <label className="block">
            <span className="text-slate-300">Description of fix *</span>
            <textarea
              rows={2}
              required
              value={form.fixdesc}
              onChange={(e) => setForm({ ...form, fixdesc: e.target.value })}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Deliverables impacted by fix</span>
            <textarea
              rows={2}
              value={form.fiximpact}
              onChange={(e) => setForm({ ...form, fiximpact: e.target.value })}
              placeholder="e.g. MMF 1.5 settings.cpp popup.cpp"
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
            />
          </label>
        </div>

        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
          <label className="block">
            <span className="text-slate-300">Date work started *</span>
            <input
              type="date"
              required
              value={form.fixstarted}
              onChange={(e) => setForm({ ...form, fixstarted: e.target.value })}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Date work completed *</span>
            <input
              type="date"
              required
              value={form.fixcomplete}
              onChange={(e) => setForm({ ...form, fixcomplete: e.target.value })}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Effort expended (hours) *</span>
            <input
              type="number"
              step="0.5"
              min="0"
              required
              value={form.fixeffort}
              onChange={(e) => handleEffortChange(Number(e.target.value))}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Rate (€/h helper)</span>
            <input
              type="number"
              step="5"
              value={hourlyRateEur}
              onChange={(e) => {
                const rate = Number(e.target.value);
                setHourlyRateEur(rate);
                setForm((prev) => ({
                  ...prev,
                  fixcost: Number((prev.fixeffort * rate).toFixed(2)),
                }));
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Cost of fix (€ EUR) *</span>
            <input
              type="number"
              step="0.01"
              min="0"
              required
              value={form.fixcost}
              onChange={(e) =>
                setForm({ ...form, fixcost: Number(e.target.value) })
              }
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            />
          </label>
        </div>
      </div>

      {/* Section 2: Defect Resolution (form_defreso.php) */}
      <div className="p-4 rounded-lg bg-slate-950/70 border border-slate-800 space-y-3">
        <div className="font-bold text-amber-400">
          Part B — Defect Resolution & Total Cost (form_defreso.php)
        </div>
        <label className="block">
          <span className="text-slate-300">Description of resolution *</span>
          <textarea
            rows={2}
            required
            value={form.resodesc}
            onChange={(e) => setForm({ ...form, resodesc: e.target.value })}
            className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
          />
        </label>

        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <label className="block">
            <span className="text-slate-300">Resolution Status *</span>
            <select
              value={form.status}
              onChange={(e) =>
                setForm({ ...form, status: e.target.value as DefectStatus })
              }
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            >
              {Object.values(DefectStatus).map((st) => (
                <option key={st} value={st}>
                  {st}
                </option>
              ))}
            </select>
          </label>
          <label className="block">
            <span className="text-slate-300">Date of resolution *</span>
            <input
              type="date"
              required
              value={form.resocomplete}
              onChange={(e) =>
                setForm({ ...form, resocomplete: e.target.value })
              }
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Date closed *</span>
            <input
              type="date"
              required
              value={form.resoclosed}
              onChange={(e) => setForm({ ...form, resoclosed: e.target.value })}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2.5 py-1.5"
            />
          </label>
          <label className="block">
            <span className="text-slate-300">Total Cost of Defect (€ EUR) *</span>
            <input
              type="number"
              step="0.01"
              min="0"
              required
              value={form.costofdefect}
              onChange={(e) =>
                setForm({ ...form, costofdefect: Number(e.target.value) })
              }
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
          Save Fix & Resolution Metrics
        </button>
      </div>
    </form>
  );
};
