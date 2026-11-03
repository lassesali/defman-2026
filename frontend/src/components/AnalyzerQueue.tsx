import React, { useCallback, useEffect, useState } from 'react';
import { defmanApi } from '../api/client';
import type { AnalyzerQueueItem, DoraTelemetrySummary } from '../types/defman';

/**
 * <AnalyzerQueue />
 * IEEE Std 1044-2009 Anomaly Triage & DORA Flow Engineering Telemetry Queue
 * Endpoint: GET /api/analyzer/queue
 */
export const AnalyzerQueue: React.FC<{
  onOpenWorkbench: (defectId: number) => void;
}> = ({ onOpenWorkbench }) => {
  const [queue, setQueue] = useState<AnalyzerQueueItem[]>([]);
  const [telemetry, setTelemetry] = useState<DoraTelemetrySummary | null>(null);
  const [sort, setSort] = useState<string>('all');
  const [detailedView, setDetailedView] = useState<boolean>(false);

  const loadQueue = useCallback(async () => {
    const res = await defmanApi.getAnalyzerQueue(sort);
    setQueue(res.queue);
    setTelemetry(res.telemetry);
  }, [sort]);

  useEffect(() => {
    loadQueue();
  }, [loadQueue]);

  const formatHours = (hours: number | null | undefined) => {
    if (hours === null || hours === undefined) return '—';
    return `${Number(hours).toFixed(2)}h`;
  };

  return (
    <div className="bg-slate-900 border border-slate-700 rounded-xl p-5 text-slate-100 shadow-lg space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 pb-3">
        <div>
          <h2 className="text-lg font-bold text-amber-400">
            IEEE Std 1044-2009 Anomaly &amp; DORA Telemetry Queue ({queue.length})
          </h2>
          <p className="text-xs text-slate-400">
            IEEE 1044-2009 root cause &amp; symptom classification, DORA MTTR / triage lead time, detection environment, and actual effort hours
          </p>
        </div>
        <div className="flex items-center gap-2">
          <select
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            className="text-xs rounded bg-slate-800 border border-slate-600 px-3 py-1.5"
          >
            <option value="all">Sort: All (by Priority)</option>
            <option value="date">Sort: Reported Timestamp</option>
            <option value="PROD">Env: PROD Escapes</option>
            <option value="STAGING">Env: STAGING</option>
            <option value="CI">Env: CI</option>
            <option value="DEV">Env: DEV</option>
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
            {detailedView ? 'Summary Table' : 'Full IEEE 1044-2009 & DORA Breakdown'}
          </button>
        </div>
      </div>

      {/* IEEE 1044-2009 Root Cause Effort Breakdown */}
      {telemetry && telemetry.root_cause_effort_breakdown.length > 0 && (
        <div className="bg-slate-950/70 border border-slate-800 rounded-lg p-3 space-y-2">
          <div className="text-xs font-semibold text-slate-300">
            Actual Engineering Effort &amp; MTTR by IEEE Std 1044-2009 Root Cause
          </div>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-2 text-xs">
            {telemetry.root_cause_effort_breakdown.map((rc) => (
              <div
                key={rc.root_cause_type}
                className="bg-slate-900 border border-slate-800 rounded p-2.5 flex flex-col justify-between"
              >
                <div className="font-semibold text-amber-300">{rc.root_cause_type}</div>
                <div className="text-[11px] text-slate-400 mt-1">
                  Count: <strong className="text-slate-200">{rc.defect_count}</strong> • Effort:{' '}
                  <strong className="text-sky-300 font-mono">{rc.total_effort_hours.toFixed(2)}h</strong>
                </div>
                <div className="text-[11px] text-slate-500">
                  Avg MTTR: {rc.avg_mttr_hours !== null ? `${rc.avg_mttr_hours}h` : 'open'} • PROD Escapes: {rc.prod_escape_count}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {!detailedView ? (
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="bg-slate-800 text-slate-300 border-b border-slate-700">
                <th className="p-2.5">ID</th>
                <th className="p-2.5">Product</th>
                <th className="p-2.5">Env</th>
                <th className="p-2.5">Developer Description</th>
                <th className="p-2.5">IEEE 1044-2009 Root Cause</th>
                <th className="p-2.5">Symptom</th>
                <th className="p-2.5">Status</th>
                <th className="p-2.5">Severity</th>
                <th className="p-2.5">Effort (h)</th>
                <th className="p-2.5">Triage Lead</th>
                <th className="p-2.5">MTTR</th>
                <th className="p-2.5 text-right">Edit</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {queue.map((item) => (
                <tr key={item.id} className="hover:bg-slate-800/60">
                  <td className="p-2.5 font-mono text-amber-400">#{item.id}</td>
                  <td className="p-2.5 font-medium">{item.product}</td>
                  <td className="p-2.5 whitespace-nowrap">
                    <span className="px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 font-mono text-[11px]">
                      {item.detected_environment || 'PROD'}
                    </span>
                    {Boolean(item.is_regression) && (
                      <span className="ml-1 px-1.5 py-0.5 rounded bg-amber-950 border border-amber-600 text-amber-300 text-[10px] font-semibold">
                        REG
                      </span>
                    )}
                  </td>
                  <td className="p-2.5 max-w-xs truncate">
                    {item.devdesc || item.title}
                  </td>
                  <td className="p-2.5">
                    <span
                      className="px-2 py-0.5 rounded text-slate-950 font-semibold whitespace-nowrap"
                      style={{ backgroundColor: item.classification_color }}
                    >
                      {item.root_cause_type || item.classification}
                    </span>
                  </td>
                  <td className="p-2.5 text-slate-300">{item.symptom_category || '—'}</td>
                  <td className="p-2.5 uppercase font-mono">{item.status}</td>
                  <td className="p-2.5">{item.severity}</td>
                  <td className="p-2.5 whitespace-nowrap font-mono text-sky-300">
                    {Number(item.actual_effort_hours ?? item.fixeffort ?? 0).toFixed(2)}h
                  </td>
                  <td className="p-2.5 whitespace-nowrap font-mono">
                    {formatHours(item.triage_lead_time_hours)}
                  </td>
                  <td className="p-2.5 whitespace-nowrap font-mono text-emerald-300">
                    {formatHours(item.mttr_hours)}
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
              <div><strong>IEEE 1044-2009 Root Cause:</strong> {item.root_cause_type || item.classification}</div>
              <div><strong>Symptom Category:</strong> {item.symptom_category || '—'}</div>
              <div><strong>Detected Environment:</strong> {item.detected_environment || 'PROD'}</div>
              <div><strong>Regression:</strong> {item.is_regression ? 'Yes (Regression)' : 'No'}</div>
              <div><strong>Introducing Ref:</strong> <span className="font-mono">{item.introducing_ref || '—'}</span></div>
              <div><strong>Resolution Ref:</strong> <span className="font-mono text-emerald-300">{item.resolution_ref || '—'}</span></div>
              <div><strong>Actual Effort:</strong> {Number(item.actual_effort_hours ?? item.fixeffort ?? 0).toFixed(2)} hours</div>
              <div><strong>Reported At:</strong> {item.reported_at || item.submitted}</div>
              <div><strong>Triaged At:</strong> {item.triaged_at || item.assigned || '—'}</div>
              <div><strong>Resolved At:</strong> {item.resolved_at || item.resocomplete || '—'}</div>
              <div><strong>Triage Lead / MTTR:</strong> {formatHours(item.triage_lead_time_hours)} / {formatHours(item.mttr_hours)}</div>
              <div><strong>Origin SDLC Phases:</strong> {item.phase || '—'}</div>
              <div><strong>Uniqueness:</strong> {item.uniqueness}</div>
              <div><strong>Deliverable:</strong> {item.deliverable || '—'}</div>
              <div><strong>Status:</strong> <span className="uppercase font-mono">{item.status}</span></div>
              <div className="col-span-2 md:col-span-4 flex justify-end pt-1">
                <button
                  type="button"
                  onClick={() => onOpenWorkbench(item.id)}
                  className="px-3 py-1 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
                >
                  Open IEEE 1044-2009 &amp; DORA Telemetry Workbench
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
