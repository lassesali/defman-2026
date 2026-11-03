import React, { useCallback, useEffect, useState } from 'react';
import { defmanApi } from '../api/client';
import type { AnalyzerQueueItem } from '../types/defman';

/**
 * <AnalyzerQueue />
 * Refactored from: form_alist.php & form_analyzed.php
 * Endpoint: GET /api/analyzer/queue
 */
export const AnalyzerQueue: React.FC<{
  onOpenWorkbench: (defectId: number) => void;
}> = ({ onOpenWorkbench }) => {
  const [queue, setQueue] = useState<AnalyzerQueueItem[]>([]);
  const [sort, setSort] = useState<string>('all');
  const [detailedView, setDetailedView] = useState<boolean>(false);

  const loadQueue = useCallback(async () => {
    const res = await defmanApi.getAnalyzerQueue(sort);
    setQueue(res.queue);
  }, [sort]);

  useEffect(() => {
    loadQueue();
  }, [loadQueue]);

  const formatDeltaDays = (days: number | null) => {
    if (days === null || days === undefined) return 'n/a';
    if (days === 0) return 'the same day';
    if (days === 1) return '1 day';
    return `${days} days`;
  };

  return (
    <div className="bg-slate-900 border border-slate-700 rounded-xl p-5 text-slate-100 shadow-lg space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 pb-3">
        <div>
          <h2 className="text-lg font-bold text-amber-400">
            Analyzer Triage & Cost Queue ({queue.length} Analyzes)
          </h2>
          <p className="text-xs text-slate-400">
            IEEE (1992) fault classification, lead-time deltas, effort hours, and EUR defect cost
          </p>
        </div>
        <div className="flex items-center gap-2">
          <select
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            className="text-xs rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
          >
            <option value="all">Sort: All (by Priority)</option>
            <option value="date">Sort: Submitted Date</option>
            <option value="critical">Filter Severity: critical</option>
            <option value="major">Filter Severity: major</option>
            <option value="minor">Filter Severity: minor</option>
            <option value="most urgent">Filter Priority: most urgent</option>
            <option value="average">Filter Priority: average</option>
            <option value="less urgent">Filter Priority: less urgent</option>
            <option value="submitted">Status: submitted</option>
            <option value="assigned">Status: assigned</option>
            <option value="opened">Status: opened</option>
            <option value="fixed">Status: fixed</option>
            <option value="resolved">Status: resolved</option>
            <option value="closed">Status: closed</option>
          </select>
          <button
            type="button"
            onClick={() => setDetailedView((v) => !v)}
            className="text-xs px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-amber-300 border border-slate-600"
          >
            {detailedView ? 'Summary Table (form_analyzed.php)' : 'Full Lead-Time Metrics (form_alist.php)'}
          </button>
        </div>
      </div>

      {!detailedView ? (
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="bg-slate-800 text-slate-300 border-b border-slate-700">
                <th className="p-2.5">ID</th>
                <th className="p-2.5">Product</th>
                <th className="p-2.5">Developer Description</th>
                <th className="p-2.5">IEEE 1992 Classification</th>
                <th className="p-2.5">Status</th>
                <th className="p-2.5">Severity</th>
                <th className="p-2.5">Priority</th>
                <th className="p-2.5">Effort / Cost</th>
                <th className="p-2.5">Sub → Assg</th>
                <th className="p-2.5">Assg → Reso</th>
                <th className="p-2.5 text-right">Edit</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {queue.map((item) => (
                <tr key={item.id} className="hover:bg-slate-800/60">
                  <td className="p-2.5 font-mono text-amber-400">#{item.id}</td>
                  <td className="p-2.5 font-medium">{item.product}</td>
                  <td className="p-2.5 max-w-xs truncate">
                    {item.devdesc || item.title}
                  </td>
                  <td className="p-2.5">
                    <span
                      className="px-2 py-0.5 rounded text-slate-950 font-semibold"
                      style={{ backgroundColor: item.classification_color }}
                    >
                      {item.classification}
                    </span>
                  </td>
                  <td className="p-2.5 uppercase font-mono">{item.status}</td>
                  <td className="p-2.5">{item.severity}</td>
                  <td className="p-2.5">{item.priority}</td>
                  <td className="p-2.5 whitespace-nowrap font-mono">
                    {Number(item.fixeffort).toFixed(1)}h / €{Number(item.costofdefect || item.fixcost).toFixed(0)}
                  </td>
                  <td className="p-2.5 whitespace-nowrap">
                    {formatDeltaDays(item.submitted_to_assigned_days)}
                  </td>
                  <td className="p-2.5 whitespace-nowrap">
                    {formatDeltaDays(item.assigned_to_resolved_days)}
                  </td>
                  <td className="p-2.5 text-right">
                    <button
                      type="button"
                      onClick={() => onOpenWorkbench(item.id)}
                      className="px-2.5 py-1 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
                    >
                      Workbench
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="space-y-4">
          {queue.map((item) => (
            <div
              key={item.id}
              className="rounded-lg bg-slate-950 border border-slate-800 p-4 grid grid-cols-2 md:grid-cols-4 gap-2.5 text-xs"
            >
              <div><strong>ID:</strong> #{item.id} ({item.product})</div>
              <div><strong>IEEE Fault Type:</strong> {item.classification}</div>
              <div><strong>Origin Phase:</strong> {item.phase || '—'}</div>
              <div><strong>Uniqueness:</strong> {item.uniqueness}</div>
              <div><strong>Originated Activity:</strong> {item.actoriginated || '—'}</div>
              <div><strong>Deliverable:</strong> {item.deliverable || '—'}</div>
              <div><strong>Detection Activity:</strong> {item.actfound || '—'}</div>
              <div><strong>Problem Status:</strong> {item.status}</div>
              <div><strong>Fix Effort:</strong> {item.fixeffort} hours</div>
              <div><strong>Cost of Fix:</strong> €{item.fixcost}</div>
              <div><strong>Total Defect Cost:</strong> €{item.costofdefect}</div>
              <div><strong>Submitted → Assigned:</strong> {formatDeltaDays(item.submitted_to_assigned_days)}</div>
              <div><strong>Submitted → Fixed:</strong> {formatDeltaDays(item.submitted_to_fixed_days)}</div>
              <div><strong>Submitted → Resolved:</strong> {formatDeltaDays(item.submitted_to_resolved_days)}</div>
              <div><strong>Assigned → Fixed:</strong> {formatDeltaDays(item.assigned_to_fixed_days)}</div>
              <div><strong>Assigned → Resolved:</strong> {formatDeltaDays(item.assigned_to_resolved_days)}</div>
              <div className="col-span-2 md:col-span-4 flex justify-end pt-1">
                <button
                  type="button"
                  onClick={() => onOpenWorkbench(item.id)}
                  className="px-3 py-1 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
                >
                  Open Analyzer & Resolution Workbench
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
