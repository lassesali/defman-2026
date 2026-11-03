import React, { useCallback, useEffect, useState } from 'react';
import { defmanApi } from '../api/client';
import {
  DefectPriority,
  type DefectReport,
  DefectSeverity,
  DefectStatus,
  type Product,
} from '../types/defman';

/**
 * <DefectReportGrid />
 * Refactored from: form_raportlist.php & form_list.php
 * Endpoint: GET /api/defects with multi-column sorting and filtering by priority, status, severity, and product
 */
export const DefectReportGrid: React.FC<{
  onSelectDefect: (id: number) => void;
  onAnalyzeDefect?: (id: number) => void;
}> = ({ onSelectDefect, onAnalyzeDefect }) => {
  const [defects, setDefects] = useState<DefectReport[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [fullDetailsMode, setFullDetailsMode] = useState(false);

  const [filters, setFilters] = useState({
    severity: '',
    priority: '',
    status: '',
    product: '',
    sort: 'date',
  });

  const loadData = useCallback(async () => {
    const [defRes, prodRes] = await Promise.all([
      defmanApi.getDefects(filters),
      defmanApi.getProducts(),
    ]);
    setDefects(defRes.defects);
    setProducts(prodRes.products);
  }, [filters]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleDelete = async (id: number) => {
    if (!window.confirm(`Are you SURE that you want to DELETE\n\nDefect ID: ${id}?`)) {
      return;
    }
    await defmanApi.deleteDefect(id);
    loadData();
  };

  const formatDaysLabel = (days?: number) => {
    if (days === undefined || days === null || days <= 0) return 'today';
    if (days === 1) return '1 day';
    return `${days} days`;
  };

  return (
    <div className="bg-slate-900 border border-slate-700 rounded-xl p-5 text-slate-100 shadow-lg space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 pb-3">
        <div>
          <h2 className="text-lg font-bold text-amber-400">
            All Defect Reports ({defects.length})
          </h2>
          <p className="text-xs text-slate-400">
            Multi-column sorting & filtering by severity, priority, status, and product
          </p>
        </div>
        <button
          type="button"
          onClick={() => setFullDetailsMode((m) => !m)}
          className="text-xs px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-amber-300 border border-slate-600"
        >
          {fullDetailsMode ? 'Summary Grid (form_raportlist.php)' : 'Full Details View (form_list.php)'}
        </button>
      </div>

      {/* Filter & Sort Controls */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-3 text-xs bg-slate-950/60 p-3 rounded-lg border border-slate-800">
        <label className="block">
          <span className="text-slate-400">Severity</span>
          <select
            value={filters.severity}
            onChange={(e) => setFilters({ ...filters, severity: e.target.value })}
            className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2 py-1.5"
          >
            <option value="">All severities</option>
            <option value={DefectSeverity.CRITICAL}>critical</option>
            <option value={DefectSeverity.MAJOR}>major</option>
            <option value={DefectSeverity.MINOR}>minor</option>
          </select>
        </label>

        <label className="block">
          <span className="text-slate-400">Priority</span>
          <select
            value={filters.priority}
            onChange={(e) => setFilters({ ...filters, priority: e.target.value })}
            className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2 py-1.5"
          >
            <option value="">All priorities</option>
            <option value={DefectPriority.MOST_URGENT}>most urgent</option>
            <option value={DefectPriority.AVERAGE}>average</option>
            <option value={DefectPriority.LESS_URGENT}>less urgent</option>
          </select>
        </label>

        <label className="block">
          <span className="text-slate-400">Status</span>
          <select
            value={filters.status}
            onChange={(e) => setFilters({ ...filters, status: e.target.value })}
            className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2 py-1.5"
          >
            <option value="">All statuses</option>
            {Object.values(DefectStatus).map((st) => (
              <option key={st} value={st}>
                {st}
              </option>
            ))}
          </select>
        </label>

        <label className="block">
          <span className="text-slate-400">Show Only Product</span>
          <select
            value={filters.product}
            onChange={(e) => setFilters({ ...filters, product: e.target.value })}
            className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2 py-1.5"
          >
            <option value="">All products</option>
            {products.map((p) => (
              <option key={p.id} value={p.name}>
                {p.name} ({p.report_count ?? 0})
              </option>
            ))}
          </select>
        </label>

        <label className="block">
          <span className="text-slate-400">Sort By</span>
          <select
            value={filters.sort}
            onChange={(e) => setFilters({ ...filters, sort: e.target.value })}
            className="mt-1 w-full rounded bg-slate-800 border border-slate-700 px-2 py-1.5"
          >
            <option value="date">Submitted Date</option>
            <option value="severity">Severity</option>
            <option value="priority">Priority</option>
            <option value="product">Product</option>
            <option value="contact">Contact</option>
          </select>
        </label>
      </div>

      {/* Table / Full Details */}
      {!fullDetailsMode ? (
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="bg-slate-800 text-slate-300 border-b border-slate-700">
                <th className="p-2.5">ID</th>
                <th className="p-2.5">Product</th>
                <th className="p-2.5">Contact</th>
                <th className="p-2.5">Title</th>
                <th className="p-2.5">Description</th>
                <th className="p-2.5">Occurred</th>
                <th className="p-2.5">Environment</th>
                <th className="p-2.5">Severity</th>
                <th className="p-2.5">Priority</th>
                <th className="p-2.5">Submitted</th>
                <th className="p-2.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {defects.map((d) => (
                <tr key={d.ID} className="hover:bg-slate-800/60">
                  <td className="p-2.5 font-mono text-amber-400">#{d.ID}</td>
                  <td className="p-2.5 font-medium">{d.Product}</td>
                  <td className="p-2.5">
                    {d.Contactemail}
                    {d.is_deleted_user === 1 && (
                      <span className="ml-1 text-[10px] text-red-400 font-semibold">
                        [DELETED USER]
                      </span>
                    )}
                  </td>
                  <td className="p-2.5 font-semibold text-slate-100">{d.Title}</td>
                  <td className="p-2.5 max-w-xs truncate text-slate-300">
                    {d.Description}
                  </td>
                  <td className="p-2.5 whitespace-nowrap">{d.Occured}</td>
                  <td className="p-2.5 max-w-[160px] truncate text-slate-400">
                    {d.Environment}
                  </td>
                  <td className="p-2.5">
                    <span
                      className={`px-2 py-0.5 rounded font-semibold ${
                        d.Severity === DefectSeverity.CRITICAL
                          ? 'bg-red-900/60 text-red-300 border border-red-700'
                          : d.Severity === DefectSeverity.MAJOR
                          ? 'bg-orange-900/60 text-orange-300 border border-orange-700'
                          : 'bg-slate-800 text-slate-300'
                      }`}
                    >
                      {d.Severity}
                    </span>
                  </td>
                  <td className="p-2.5">{d.PriorityLabel || d.Priority}</td>
                  <td className="p-2.5 whitespace-nowrap">
                    {d.Submitted} ({formatDaysLabel(d.days_since_submitted)})
                  </td>
                  <td className="p-2.5 text-right whitespace-nowrap space-x-1.5">
                    <button
                      type="button"
                      onClick={() => onSelectDefect(d.ID)}
                      className="px-2 py-1 rounded bg-slate-700 hover:bg-slate-600 text-slate-100"
                    >
                      Report
                    </button>
                    {onAnalyzeDefect && (
                      <button
                        type="button"
                        onClick={() => onAnalyzeDefect(d.ID)}
                        className="px-2 py-1 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
                      >
                        Analyze
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={() => handleDelete(d.ID)}
                      className="px-2 py-1 rounded bg-red-900/70 hover:bg-red-800 text-red-200"
                    >
                      Delete
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        /* Full Details Mode (form_list.php) */
        <div className="space-y-4">
          {defects.map((d) => (
            <div
              key={d.ID}
              className="rounded-lg bg-slate-950 border border-slate-800 p-4 grid grid-cols-1 md:grid-cols-2 gap-2 text-xs"
            >
              <div><strong>ID:</strong> #{d.ID}</div>
              <div><strong>Product and release:</strong> {d.Product}</div>
              <div><strong>Who reported:</strong> {d.Who}</div>
              <div><strong>Contact email:</strong> {d.Contactemail}</div>
              <div><strong>When reported:</strong> {d.Whenreported}</div>
              <div><strong>Date occurred:</strong> {d.Occured}</div>
              <div><strong>Who discovered:</strong> {d.Discoveredby || '—'}</div>
              <div><strong>Type of person:</strong> {d.Persontype}</div>
              <div><strong>Location found:</strong> {d.Location || '—'}</div>
              <div><strong>Technical environment:</strong> {d.Environment || '—'}</div>
              <div><strong>Severity:</strong> {d.Severity}</div>
              <div><strong>Priority:</strong> {d.PriorityLabel || d.Priority}</div>
              <div className="md:col-span-2"><strong>Title:</strong> {d.Title}</div>
              <div className="md:col-span-2"><strong>Description:</strong> {d.Description}</div>
              <div className="md:col-span-2"><strong>Additional Info:</strong> {d.Addinfo || '—'}</div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
