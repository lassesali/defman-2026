import React, { useEffect, useState } from 'react';
import { defmanApi } from '../api/client';
import {
  DefectPriority,
  type DefectReport,
  DefectSeverity,
  PersonType,
  type Product,
} from '../types/defman';

/**
 * <DefectDetailModal />
 * Refactored from: form_defrap.php
 * Endpoint: GET /api/defects/{id} & PUT /api/defects/{id}
 */
export const DefectDetailModal: React.FC<{
  defectId: number;
  canEdit: boolean;
  onClose: () => void;
  onOpenAnalyze?: (id: number) => void;
  onUpdated?: () => void;
}> = ({ defectId, canEdit, onClose, onOpenAnalyze, onUpdated }) => {
  const [defect, setDefect] = useState<DefectReport | null>(null);
  const [products, setProducts] = useState<Product[]>([]);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    Promise.all([
      defmanApi.getDefectById(defectId),
      defmanApi.getProducts(),
    ]).then(([defRes, prodRes]) => {
      setDefect(defRes);
      setProducts(prodRes.products);
    });
  }, [defectId]);

  if (!defect) {
    return null;
  }

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage(null);
    setError(null);
    try {
      const res = await defmanApi.updateDefectReport(defect.ID, {
        product: defect.Product,
        environment: defect.Environment,
        title: defect.Title,
        description: defect.Description,
        discovered_by: defect.Discoveredby,
        persontype: defect.Persontype,
        occured: defect.Occured,
        location: defect.Location,
        severity: defect.Severity,
        priority: defect.Priority,
        submitted: defect.Submitted,
        addinfo: defect.Addinfo,
      });
      setMessage(res.message);
      onUpdated?.();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Update failed');
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-black/75 flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-xl max-w-2xl w-full p-6 text-slate-100 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between border-b border-slate-700 pb-3 mb-4">
          <div>
            <h3 className="text-lg font-bold text-amber-400">
              Defect Report — Defect ID #{defect.ID}
            </h3>
            <p className="text-xs text-slate-400">
              Reported by {defect.Who} ({defect.Contactemail}) at {defect.Whenreported}
            </p>
          </div>
          <div className="flex items-center gap-2">
            {onOpenAnalyze && (
              <button
                type="button"
                onClick={() => onOpenAnalyze(defect.ID)}
                className="text-xs px-3 py-1.5 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
              >
                Open Analyzer Workbench
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

        <form onSubmit={handleSave} className="space-y-3 text-xs">
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="text-slate-400">Product and release *</span>
              <select
                disabled={!canEdit}
                value={defect.Product}
                onChange={(e) => setDefect({ ...defect, Product: e.target.value })}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              >
                {products.map((p) => (
                  <option key={p.id} value={p.name}>
                    {p.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="text-slate-400">Title *</span>
              <input
                type="text"
                disabled={!canEdit}
                value={defect.Title}
                onChange={(e) => setDefect({ ...defect, Title: e.target.value })}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              />
            </label>
          </div>

          <label className="block">
            <span className="text-slate-400">Description of the problem *</span>
            <textarea
              rows={3}
              disabled={!canEdit}
              value={defect.Description}
              onChange={(e) => setDefect({ ...defect, Description: e.target.value })}
              className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
            />
          </label>

          <div className="grid grid-cols-3 gap-3">
            <label className="block">
              <span className="text-slate-400">Who discovered</span>
              <input
                type="text"
                disabled={!canEdit}
                value={defect.Discoveredby || ''}
                onChange={(e) =>
                  setDefect({ ...defect, Discoveredby: e.target.value })
                }
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-400">Person type *</span>
              <select
                disabled={!canEdit}
                value={defect.Persontype}
                onChange={(e) =>
                  setDefect({ ...defect, Persontype: e.target.value as PersonType })
                }
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              >
                {Object.values(PersonType).map((pt) => (
                  <option key={pt} value={pt}>
                    {pt}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="text-slate-400">Location found</span>
              <input
                type="text"
                disabled={!canEdit}
                value={defect.Location || ''}
                onChange={(e) => setDefect({ ...defect, Location: e.target.value })}
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
                value={defect.Occured}
                onChange={(e) => setDefect({ ...defect, Occured: e.target.value })}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-400">Submitted *</span>
              <input
                type="date"
                disabled={!canEdit}
                value={defect.Submitted}
                onChange={(e) => setDefect({ ...defect, Submitted: e.target.value })}
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              />
            </label>
            <label className="block">
              <span className="text-slate-400">Severity *</span>
              <select
                disabled={!canEdit}
                value={defect.Severity}
                onChange={(e) =>
                  setDefect({ ...defect, Severity: e.target.value as DefectSeverity })
                }
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
                value={defect.Priority}
                onChange={(e) =>
                  setDefect({ ...defect, Priority: e.target.value as DefectPriority })
                }
                className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
              >
                <option value={DefectPriority.MOST_URGENT}>most urgent</option>
                <option value={DefectPriority.AVERAGE}>average</option>
                <option value={DefectPriority.LESS_URGENT}>less urgent</option>
              </select>
            </label>
          </div>

          <label className="block">
            <span className="text-slate-400">Technical environment</span>
            <textarea
              rows={2}
              disabled={!canEdit}
              value={defect.Environment || ''}
              onChange={(e) =>
                setDefect({ ...defect, Environment: e.target.value })
              }
              className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2.5 py-1.5"
            />
          </label>

          <label className="block">
            <span className="text-slate-400">Additional information</span>
            <textarea
              rows={2}
              disabled={!canEdit}
              value={defect.Addinfo || ''}
              onChange={(e) => setDefect({ ...defect, Addinfo: e.target.value })}
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
};
