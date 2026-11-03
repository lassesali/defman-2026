import React, { useEffect, useState } from 'react';
import { defmanApi } from '../api/client';
import {
  DefectPriority,
  type DefectReport,
  DefectSeverity,
  DetectedEnvironment,
  PersonType,
  type Product,
  SymptomCategory,
} from '../types/defman';

export interface DefectDetailModalProps {
  defectId: number;
  canEdit: boolean;
  onClose: () => void;
  onOpenAnalyze?: (id: number) => void;
  onUpdated?: () => void;
}

/**
 * <DefectDetailModal />
 * Displays and updates a normalized defect report along with its IEEE Std 1044-2009
 * symptom category, detection environment ('DEV' | 'CI' | 'STAGING' | 'PROD'),
 * regression flag, and DORA telemetry summary.
 * Endpoint: GET /api/defects/{id} & PUT /api/defects/{id}
 */
export function DefectDetailModal({
  defectId,
  canEdit,
  onClose,
  onOpenAnalyze,
  onUpdated,
}: DefectDetailModalProps): React.ReactElement | null {
  const [defect, setDefect] = useState<DefectReport | null>(null);
  const [products, setProducts] = useState<Product[]>([]);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(function loadDefectAndProductsEffect() {
    Promise.all([
      defmanApi.getDefectById(defectId),
      defmanApi.getProducts(),
    ]).then(function handleLoadedModalData([defRes, prodRes]) {
      setDefect(defRes);
      setProducts(prodRes.products);
    });
  }, [defectId]);

  if (!defect) {
    return null;
  }

  const currentDefect = defect;

  async function handleSave(e: React.FormEvent): Promise<void> {
    e.preventDefault();
    setMessage(null);
    setError(null);
    try {
      const res = await defmanApi.updateDefectReport(currentDefect.ID, {
        product: currentDefect.Product,
        environment: currentDefect.Environment,
        detected_environment: currentDefect.detected_environment || DetectedEnvironment.PROD,
        symptom_category: currentDefect.symptom_category || null,
        is_regression: Boolean(currentDefect.is_regression),
        title: currentDefect.Title,
        description: currentDefect.Description,
        discovered_by: currentDefect.Discoveredby,
        persontype: currentDefect.Persontype,
        occured: currentDefect.Occured,
        location: currentDefect.Location,
        severity: currentDefect.Severity,
        priority: currentDefect.Priority,
        submitted: currentDefect.Submitted,
        addinfo: currentDefect.Addinfo,
      });
      setMessage(res.message);
      if (onUpdated) {
        onUpdated();
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Update failed');
    }
  }

  function handleOpenAnalyzeClick(): void {
    if (onOpenAnalyze) {
      onOpenAnalyze(currentDefect.ID);
    }
  }

  return (
    <div className="fixed inset-0 z-50 bg-black/75 flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-xl max-w-2xl w-full p-6 text-slate-100 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between border-b border-slate-700 pb-3 mb-4">
          <div>
            <div className="flex items-center gap-2">
              <h3 className="text-lg font-bold text-amber-400">
                Defect Report — Defect ID #{currentDefect.ID}
              </h3>
              <span className="text-[11px] px-2 py-0.5 rounded bg-slate-800 border border-slate-700 font-mono text-emerald-300">
                {currentDefect.detected_environment || DetectedEnvironment.PROD}
              </span>
              {Boolean(currentDefect.is_regression) && (
                <span className="text-[11px] px-2 py-0.5 rounded bg-amber-950 border border-amber-600 text-amber-300 font-semibold">
                  REGRESSION
                </span>
              )}
            </div>
            <p className="text-xs text-slate-400 mt-0.5">
              Reported by {currentDefect.Who} ({currentDefect.Contactemail}) at {currentDefect.Whenreported}
            </p>
          </div>
          <div className="flex items-center gap-2">
            {onOpenAnalyze && (
              <button
                type="button"
                onClick={handleOpenAnalyzeClick}
                className="text-xs px-3 py-1.5 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
              >
                Open Telemetry Workbench
              </button>
            )}
            <button
              type="button"
              onClick={onClose}
              className="text-xs px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300"
            >
              Close
            </button>
          </div>
        </div>

        {error && (
          <div className="mb-3 p-2.5 rounded bg-red-950 border border-red-600 text-amber-300 text-xs">
            {error}
          </div>
        )}
        {message && (
          <div className="mb-3 p-2.5 rounded bg-emerald-950 border border-emerald-600 text-emerald-300 text-xs">
            {message}
          </div>
        )}

        {/* IEEE 1044-2009 & DORA Telemetry Snapshot if analyzed */}
        {currentDefect.analysis && (
          <div className="mb-4 p-3 rounded-lg bg-slate-950 border border-slate-800 grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
            <div>
              <span className="text-slate-400">Root Cause (1044-2009):</span>{' '}
              <strong className="text-amber-300">
                {currentDefect.analysis.root_cause_type || currentDefect.analysis.classification}
              </strong>
            </div>
            <div>
              <span className="text-slate-400">Symptom:</span>{' '}
              <span className="text-slate-200">
                {currentDefect.analysis.symptom_category || currentDefect.symptom_category || '—'}
              </span>
            </div>
            <div>
              <span className="text-slate-400">Actual Effort:</span>{' '}
              <span className="font-mono text-sky-300">
                {Number(currentDefect.analysis.actual_effort_hours ?? currentDefect.analysis.fixeffort ?? 0).toFixed(2)}h
              </span>
            </div>
            <div>
              <span className="text-slate-400">Introducing Ref:</span>{' '}
              <span className="font-mono text-slate-200">
                {currentDefect.analysis.introducing_ref || '—'}
              </span>
            </div>
            <div>
              <span className="text-slate-400">Resolution Ref:</span>{' '}
              <span className="font-mono text-emerald-300">
                {currentDefect.analysis.resolution_ref || '—'}
              </span>
            </div>
            <div>
              <span className="text-slate-400">MTTR / Triage Lead:</span>{' '}
              <span className="font-mono text-slate-200">
                {currentDefect.analysis.mttr_hours !== null && currentDefect.analysis.mttr_hours !== undefined
                  ? `${currentDefect.analysis.mttr_hours}h`
                  : 'open'}{' '}
                /{' '}
                {currentDefect.analysis.triage_lead_time_hours !== null && currentDefect.analysis.triage_lead_time_hours !== undefined
                  ? `${currentDefect.analysis.triage_lead_time_hours}h`
                  : '—'}
              </span>
            </div>
          </div>
        )}

        <form onSubmit={handleSave} className="space-y-3 text-xs">
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="text-slate-400">Product and release *</span>
              <select
                disabled={!canEdit}
                value={currentDefect.Product}
                onChange={function onProductChange(e) {
                  setDefect({ ...currentDefect, Product: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              >
                {products.map(function renderProductOption(p) {
                  return (
                    <option key={p.id} value={p.name}>
                      {p.name}
                    </option>
                  );
                })}
              </select>
            </label>
            <label className="block">
              <span className="text-slate-400">Title *</span>
              <input
                type="text"
                disabled={!canEdit}
                value={currentDefect.Title}
                onChange={function onTitleChange(e) {
                  setDefect({ ...currentDefect, Title: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              />
            </label>
          </div>

          <label className="block">
            <span className="text-slate-400">Description of the problem *</span>
            <textarea
              rows={3}
              disabled={!canEdit}
              value={currentDefect.Description}
              onChange={function onDescriptionChange(e) {
                setDefect({ ...currentDefect, Description: e.target.value });
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
            />
          </label>

          {/* IEEE 1044-2009 Symptom, Detection Environment & Regression */}
          <div className="grid grid-cols-3 gap-3 bg-slate-950/60 p-3 rounded-lg border border-slate-800">
            <label className="block">
              <span className="text-slate-400">Detected Environment *</span>
              <select
                disabled={!canEdit}
                value={currentDefect.detected_environment || DetectedEnvironment.PROD}
                onChange={function onDetectedEnvironmentChange(e) {
                  setDefect({ ...currentDefect, detected_environment: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5 font-mono"
              >
                {Object.values(DetectedEnvironment).map(function renderDetectedEnvOption(env) {
                  return (
                    <option key={env} value={env}>
                      {env}
                    </option>
                  );
                })}
              </select>
            </label>

            <label className="block">
              <span className="text-slate-400">Symptom Category (IEEE 1044-2009)</span>
              <select
                disabled={!canEdit}
                value={currentDefect.symptom_category || SymptomCategory.OTHER}
                onChange={function onSymptomCategoryChange(e) {
                  setDefect({ ...currentDefect, symptom_category: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              >
                {Object.values(SymptomCategory).map(function renderSymptomCategoryOption(sc) {
                  return (
                    <option key={sc} value={sc}>
                      {sc}
                    </option>
                  );
                })}
              </select>
            </label>

            <label className="flex items-center gap-2 pt-5 cursor-pointer">
              <input
                type="checkbox"
                disabled={!canEdit}
                checked={Boolean(currentDefect.is_regression)}
                onChange={function onRegressionFlagChange(e) {
                  setDefect({ ...currentDefect, is_regression: e.target.checked });
                }}
                className="rounded border-slate-600"
              />
              <span className="text-slate-300 font-semibold">Regression Defect</span>
            </label>
          </div>

          <div className="grid grid-cols-3 gap-3">
            <label className="block">
              <span className="text-slate-400">Who discovered</span>
              <input
                type="text"
                disabled={!canEdit}
                value={currentDefect.Discoveredby || ''}
                onChange={function onDiscoveredByChange(e) {
                  setDefect({ ...currentDefect, Discoveredby: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-400">Person type *</span>
              <select
                disabled={!canEdit}
                value={currentDefect.Persontype}
                onChange={function onPersonTypeChange(e) {
                  setDefect({ ...currentDefect, Persontype: e.target.value as PersonType });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              >
                {Object.values(PersonType).map(function renderPersonTypeOption(pt) {
                  return (
                    <option key={pt} value={pt}>
                      {pt}
                    </option>
                  );
                })}
              </select>
            </label>
            <label className="block">
              <span className="text-slate-400">Location found</span>
              <input
                type="text"
                disabled={!canEdit}
                value={currentDefect.Location || ''}
                onChange={function onLocationChange(e) {
                  setDefect({ ...currentDefect, Location: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              />
            </label>
          </div>

          <div className="grid grid-cols-4 gap-3">
            <label className="block">
              <span className="text-slate-400">Date Occurred *</span>
              <input
                type="date"
                disabled={!canEdit}
                value={currentDefect.Occured}
                onChange={function onOccuredChange(e) {
                  setDefect({ ...currentDefect, Occured: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-400">Submitted *</span>
              <input
                type="date"
                disabled={!canEdit}
                value={currentDefect.Submitted}
                onChange={function onSubmittedChange(e) {
                  setDefect({ ...currentDefect, Submitted: e.target.value });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-400">Severity *</span>
              <select
                disabled={!canEdit}
                value={currentDefect.Severity}
                onChange={function onSeveritySelectChange(e) {
                  setDefect({ ...currentDefect, Severity: e.target.value as DefectSeverity });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              >
                <option value={DefectSeverity.MINOR}>minor</option>
                <option value={DefectSeverity.MAJOR}>major</option>
                <option value={DefectSeverity.CRITICAL}>critical</option>
              </select>
            </label>
            <label className="block">
              <span className="text-slate-400">Priority *</span>
              <select
                disabled={!canEdit}
                value={currentDefect.Priority}
                onChange={function onPrioritySelectChange(e) {
                  setDefect({ ...currentDefect, Priority: e.target.value as DefectPriority });
                }}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              >
                <option value={DefectPriority.MOST_URGENT}>most urgent</option>
                <option value={DefectPriority.AVERAGE}>average</option>
                <option value={DefectPriority.LESS_URGENT}>less urgent</option>
              </select>
            </label>
          </div>

          <label className="block">
            <span className="text-slate-400">Technical environment details</span>
            <textarea
              rows={2}
              disabled={!canEdit}
              value={currentDefect.Environment || ''}
              onChange={function onEnvironmentChange(e) {
                setDefect({ ...currentDefect, Environment: e.target.value });
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
            />
          </label>

          <label className="block">
            <span className="text-slate-400">Additional information</span>
            <textarea
              rows={2}
              disabled={!canEdit}
              value={currentDefect.Addinfo || ''}
              onChange={function onAddInfoChange(e) {
                setDefect({ ...currentDefect, Addinfo: e.target.value });
              }}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
            />
          </label>

          {canEdit && (
            <div className="pt-2 flex justify-end">
              <button
                type="submit"
                className="px-4 py-2 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
              >
                Update Report
              </button>
            </div>
          )}
        </form>
      </div>
    </div>
  );
}
