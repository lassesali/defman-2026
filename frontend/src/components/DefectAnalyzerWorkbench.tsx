import React, { useEffect, useState } from 'react';
import { defmanApi } from '../api/client';
import {
  DefectPriority,
  DefectSeverity,
  DefectStatus,
  IEEE_1992_TAXONOMY,
  IeeeFaultType,
  OriginPhase,
  Uniqueness,
} from '../types/defman';
import { DefectResolutionForm } from './DefectResolutionForm';

/**
 * <DefectAnalyzerWorkbench />
 * Refactored from: form_analyze.php, form_analyzed.php, faulttypes.html, helpanalyze.html
 * Endpoint: PUT /api/defects/{id}/analyze
 */
export const DefectAnalyzerWorkbench: React.FC<{
  defectId: number;
  onClose?: () => void;
  onSaved?: () => void;
}> = ({ defectId, onClose, onSaved }) => {
  const [activeTab, setActiveTab] = useState<'analyze' | 'resolve'>('analyze');
  const [showTaxonomyModal, setShowTaxonomyModal] = useState(false);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const [form, setForm] = useState({
    devdesc: '',
    classification: IeeeFaultType.LOGIC as string,
    classification_other: '',
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

  useEffect(() => {
    defmanApi.getDefectById(defectId).then((res) => {
      if (res.analysis) {
        const a = res.analysis;
        const isStandardEnum = Object.values(IeeeFaultType).includes(
          a.classification as IeeeFaultType
        );
        setForm({
          devdesc: a.devdesc || '',
          classification: isStandardEnum
            ? (a.classification as string)
            : IeeeFaultType.OTHER,
          classification_other: isStandardEnum ? '' : (a.classification || ''),
          dateoriginated: a.dateoriginated || res.Occured,
          phases: a.phase ? a.phase.split(',').filter(Boolean) : [],
          actoriginated: a.actoriginated || '',
          deliverable: a.deliverable || '',
          actfound: a.actfound || '',
          submitted: a.submitted || res.Submitted,
          assigned: a.assigned || new Date().toISOString().slice(0, 10),
          uniqueness: a.uniqueness || Uniqueness.NEW_DEFECT,
          severity: a.severity || res.Severity,
          priority: a.priority || 'most urgent',
          workaround: a.workaround || '',
          proposedfix: a.proposedfix || '',
          status: a.status || DefectStatus.OPENED,
        });
      }
    });
  }, [defectId]);

  const togglePhase = (phase: string) => {
    setForm((prev) => ({
      ...prev,
      phases: prev.phases.includes(phase)
        ? prev.phases.filter((p) => p !== phase)
        : [...prev.phases, phase],
    }));
  };

  const handleAnalyzeSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);
    setStatusMessage(null);
    try {
      const res = await defmanApi.analyzeDefect(defectId, form);
      setStatusMessage(res.message);
      onSaved?.();
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Failed to save analysis');
    }
  };

  return (
    <div className="bg-slate-900 border border-slate-700 rounded-xl p-6 text-slate-100 shadow-xl space-y-4">
      {/* Header & Navigation Bar matching 2004 top links: Defect analysis - Defect fix - Defect resolution */}
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 pb-3">
        <div>
          <h2 className="text-lg font-bold text-amber-400">
            Defect Analyzer Workbench — Defect ID #{defectId}
          </h2>
          <div className="flex items-center gap-2 mt-1 text-xs">
            <button
              type="button"
              onClick={() => setActiveTab('analyze')}
              className={`px-3 py-1 rounded font-semibold ${
                activeTab === 'analyze'
                  ? 'bg-amber-500 text-slate-950'
                  : 'bg-slate-800 text-slate-300'
              }`}
            >
              1. IEEE 1992 Fault Analysis (form_analyze.php)
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('resolve')}
              className={`px-3 py-1 rounded font-semibold ${
                activeTab === 'resolve'
                  ? 'bg-amber-500 text-slate-950'
                  : 'bg-slate-800 text-slate-300'
              }`}
            >
              2. Fix Effort & Cost Resolution (form_deffix.php / form_defreso.php)
            </button>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => setShowTaxonomyModal((v) => !v)}
            className="text-xs px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-amber-300 border border-slate-600"
          >
            {showTaxonomyModal ? 'Hide IEEE 1992 Faults' : 'IEEE 1992 Fault Reference (faulttypes.html)'}
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

      {showTaxonomyModal && (
        <div className="p-4 rounded-lg bg-slate-950 border border-amber-500/40 text-xs grid grid-cols-1 md:grid-cols-2 gap-3 max-h-60 overflow-y-auto">
          {Object.entries(IEEE_1992_TAXONOMY).map(([category, subs]) => (
            <div key={category} className="space-y-1">
              <div className="font-bold text-amber-400">{category}</div>
              <ul className="list-disc list-inside text-slate-400 space-y-0.5">
                {subs.map((s) => (
                  <li key={s}>{s}</li>
                ))}
              </ul>
            </div>
          ))}
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
                Defect description by developer <span className="text-red-400">*</span>
              </span>
              <input
                type="text"
                required
                value={form.devdesc}
                onChange={(e) => setForm({ ...form, devdesc: e.target.value })}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>

            <label className="block">
              <span className="text-slate-300">
                Problem status <span className="text-red-400">*</span>
              </span>
              <select
                value={form.status}
                onChange={(e) =>
                  setForm({ ...form, status: e.target.value as DefectStatus })
                }
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              >
                {Object.values(DefectStatus).map((st) => (
                  <option key={st} value={st}>
                    {st}
                  </option>
                ))}
              </select>
            </label>
          </div>

          {/* IEEE 1992 Classification + legacy toggleKentta Other field */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 bg-slate-950/60 p-3 rounded-lg border border-slate-800">
            <label className="block">
              <span className="text-slate-300">
                Classification of defect (IEEE, 1992) <span className="text-red-400">*</span>
              </span>
              <select
                disabled={form.classification_other.trim().length > 0}
                value={
                  form.classification_other.trim().length > 0
                    ? IeeeFaultType.OTHER
                    : form.classification
                }
                onChange={(e) =>
                  setForm({ ...form, classification: e.target.value })
                }
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5 disabled:opacity-50"
              >
                {Object.values(IeeeFaultType).map((ft) => (
                  <option key={ft} value={ft}>
                    {ft}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="text-slate-300">
                Other classification (auto-locks dropdown when typed)
              </span>
              <input
                type="text"
                value={form.classification_other}
                onChange={(e) =>
                  setForm({
                    ...form,
                    classification_other: e.target.value,
                    classification:
                      e.target.value.length > 0
                        ? IeeeFaultType.OTHER
                        : form.classification,
                  })
                }
                placeholder="Custom fault classification..."
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
          </div>

          {/* Phase Originated Checkboxes */}
          <div>
            <span className="text-slate-300 block mb-1.5">
              Phase originated <span className="text-red-400">*</span>
            </span>
            <div className="grid grid-cols-2 md:grid-cols-6 gap-2 bg-slate-950/60 p-3 rounded-lg border border-slate-800">
              {Object.values(OriginPhase).map((phase) => (
                <label
                  key={phase}
                  className="flex items-center gap-2 cursor-pointer"
                >
                  <input
                    type="checkbox"
                    checked={form.phases.includes(phase)}
                    onChange={() => togglePhase(phase)}
                    className="rounded border-slate-600"
                  />
                  <span>{phase}</span>
                </label>
              ))}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <label className="block">
              <span className="text-slate-300">
                Date defect originated <span className="text-red-400">*</span>
              </span>
              <input
                type="date"
                required
                value={form.dateoriginated}
                onChange={(e) =>
                  setForm({ ...form, dateoriginated: e.target.value })
                }
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-300">
                Date submitted <span className="text-red-400">*</span>
              </span>
              <input
                type="date"
                required
                value={form.submitted}
                onChange={(e) => setForm({ ...form, submitted: e.target.value })}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-300">
                Date assigned <span className="text-red-400">*</span>
              </span>
              <input
                type="date"
                required
                value={form.assigned}
                onChange={(e) => setForm({ ...form, assigned: e.target.value })}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <label className="block">
              <span className="text-slate-300">
                Deliverable originated <span className="text-red-400">*</span>
              </span>
              <input
                type="text"
                required
                value={form.deliverable}
                onChange={(e) =>
                  setForm({ ...form, deliverable: e.target.value })
                }
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-300">
                Activity which originated defect <span className="text-red-400">*</span>
              </span>
              <input
                type="text"
                required
                value={form.actoriginated}
                onChange={(e) =>
                  setForm({ ...form, actoriginated: e.target.value })
                }
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-300">
                Activity that should have found defect <span className="text-red-400">*</span>
              </span>
              <input
                type="text"
                required
                value={form.actfound}
                onChange={(e) => setForm({ ...form, actfound: e.target.value })}
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
                onChange={(e) =>
                  setForm({ ...form, uniqueness: e.target.value })
                }
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              >
                {Object.values(Uniqueness).map((u) => (
                  <option key={u} value={u}>
                    {u}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="text-slate-300">
                Severity by developer <span className="text-red-400">*</span>
              </span>
              <select
                value={form.severity}
                onChange={(e) =>
                  setForm({
                    ...form,
                    severity: e.target.value as DefectSeverity,
                  })
                }
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              >
                <option value={DefectSeverity.MINOR}>minor</option>
                <option value={DefectSeverity.MAJOR}>major</option>
                <option value={DefectSeverity.CRITICAL}>critical</option>
              </select>
            </label>
            <label className="block">
              <span className="text-slate-300">
                Priority by developer <span className="text-red-400">*</span>
              </span>
              <select
                value={form.priority}
                onChange={(e) => setForm({ ...form, priority: e.target.value })}
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
              <span className="text-slate-300">Work around</span>
              <textarea
                rows={2}
                value={form.workaround}
                onChange={(e) =>
                  setForm({ ...form, workaround: e.target.value })
                }
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-300">
                Proposed fix <span className="text-red-400">*</span>
              </span>
              <textarea
                rows={2}
                required
                value={form.proposedfix}
                onChange={(e) =>
                  setForm({ ...form, proposedfix: e.target.value })
                }
                className="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
              />
            </label>
          </div>

          <div className="flex items-center justify-end gap-3 pt-2">
            <button
              type="submit"
              className="px-4 py-2 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
            >
              Save Analysis
            </button>
          </div>
        </form>
      ) : (
        <DefectResolutionForm defectId={defectId} onSaved={onSaved} />
      )}
    </div>
  );
};
