import React, { useCallback, useEffect, useState } from 'react';
import { defmanApi } from './api/client';
import { AdminUserApproval } from './components/AdminUserApproval';
import { AnalyzerQueue } from './components/AnalyzerQueue';
import { CustomerReportForm } from './components/CustomerReportForm';
import { DefectAnalyzerWorkbench } from './components/DefectAnalyzerWorkbench';
import { DefectDetailModal } from './components/DefectDetailModal';
import { DefectReportGrid } from './components/DefectReportGrid';
import { DefectTriageCanvas } from './components/DefectTriageCanvas';
import { ProductManager } from './components/ProductManager';
import { PublicReportForm } from './components/PublicReportForm';
import { UserProfileSettings } from './components/UserProfileSettings';
import {
  type AnalyzerQueueItem,
  type AuthUser,
  type DoraTelemetrySummary,
  UserLevel,
} from './types/defman';

type ActiveView =
  | 'canvas'
  | 'reports'
  | 'analyzer-queue'
  | 'submit-report'
  | 'public-report'
  | 'products'
  | 'users'
  | 'profile';

/**
 * Modern React 19 SPA Root
 * IEEE Std 1044-2009 Anomaly Classification & DORA Flow Engineering Telemetry
 */
export const App: React.FC = () => {
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [activeView, setActiveView] = useState<ActiveView>('public-report');
  const [selectedDefectModalId, setSelectedDefectModalId] = useState<number | null>(null);
  const [workbenchDefectId, setWorkbenchDefectId] = useState<number | null>(null);
  const [queueItems, setQueueItems] = useState<AnalyzerQueueItem[]>([]);
  const [telemetry, setTelemetry] = useState<DoraTelemetrySummary | null>(null);

  // Auth box state
  const [loginUsername, setLoginUsername] = useState('');
  const [loginPassword, setLoginPassword] = useState('');
  const [authError, setAuthError] = useState<string | null>(null);

  const loadAnalyzerCanvasData = useCallback(async () => {
    if (currentUser && currentUser.userlevel >= UserLevel.ANALYZER) {
      const res = await defmanApi.getAnalyzerQueue('all');
      setQueueItems(res.queue);
      setTelemetry(res.telemetry);
    }
  }, [currentUser]);

  useEffect(() => {
    loadAnalyzerCanvasData();
  }, [loadAnalyzerCanvasData]);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setAuthError(null);
    try {
      const res = await defmanApi.login(loginUsername, loginPassword);
      localStorage.setItem('defman_token', res.token);
      setCurrentUser(res.user);
      setActiveView(
        res.user.userlevel >= UserLevel.ANALYZER ? 'canvas' : 'submit-report'
      );
    } catch (err) {
      setAuthError(err instanceof Error ? err.message : 'Authentication failed');
    }
  };

  const handleLogout = async () => {
    await defmanApi.logout();
    localStorage.removeItem('defman_token');
    setCurrentUser(null);
    setTelemetry(null);
    setActiveView('public-report');
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 font-sans">
      {/* Top Menubar */}
      <header className="bg-slate-900 border-b border-slate-800 px-6 py-3">
        <div className="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <span className="text-xl font-black tracking-tight text-amber-400">
              DefMan 2026
            </span>
            <span className="text-xs px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">
              IEEE Std 1044-2009 &amp; DORA Flow Telemetry
            </span>
          </div>

          {currentUser ? (
            <div className="flex flex-wrap items-center gap-2 text-xs">
              <span className="text-slate-300 mr-2">
                Logged in: <strong className="text-amber-300">{currentUser.login}</strong> (Level {currentUser.userlevel})
              </span>

              {currentUser.userlevel >= UserLevel.CUSTOMER && (
                <button
                  type="button"
                  onClick={() => setActiveView('submit-report')}
                  className="px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700"
                >
                  Defect Report
                </button>
              )}

              {currentUser.userlevel >= UserLevel.CUSTOMER && (
                <button
                  type="button"
                  onClick={() => setActiveView('reports')}
                  className="px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700"
                >
                  List All Reports
                </button>
              )}

              {currentUser.userlevel >= UserLevel.ANALYZER && (
                <>
                  <button
                    type="button"
                    onClick={() => setActiveView('canvas')}
                    className="px-3 py-1.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/40 hover:bg-amber-500/30 font-semibold"
                  >
                    WebGPU Triage Canvas
                  </button>
                  <button
                    type="button"
                    onClick={() => setActiveView('analyzer-queue')}
                    className="px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700"
                  >
                    Telemetry &amp; Triage Queue
                  </button>
                </>
              )}

              {currentUser.userlevel === UserLevel.ADMIN && (
                <>
                  <button
                    type="button"
                    onClick={() => setActiveView('products')}
                    className="px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700"
                  >
                    Product Management
                  </button>
                  <button
                    type="button"
                    onClick={() => setActiveView('users')}
                    className="px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700"
                  >
                    User Management
                  </button>
                </>
              )}

              {currentUser.userlevel > UserLevel.PUBLIC && (
                <button
                  type="button"
                  onClick={() => setActiveView('profile')}
                  className="px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700"
                >
                  Own Settings
                </button>
              )}

              <button
                type="button"
                onClick={handleLogout}
                className="px-3 py-1.5 rounded bg-red-900/60 hover:bg-red-800 text-red-200"
              >
                Logout
              </button>
            </div>
          ) : (
            <form onSubmit={handleLogin} className="flex items-center gap-2 text-xs">
              {authError && <span className="text-red-400">{authError}</span>}
              <input
                type="text"
                placeholder="Username (e.g. zuck, julia, sabrin)"
                value={loginUsername}
                onChange={(e) => setLoginUsername(e.target.value)}
                className="rounded bg-slate-800 border border-slate-700 px-2.5 py-1"
              />
              <input
                type="password"
                placeholder="Password"
                value={loginPassword}
                onChange={(e) => setLoginPassword(e.target.value)}
                className="rounded bg-slate-800 border border-slate-700 px-2.5 py-1"
              />
              <button
                type="submit"
                className="px-3 py-1 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold"
              >
                Login &gt;
              </button>
            </form>
          )}
        </div>
      </header>

      {/* Main Content Area */}
      <main className="max-w-7xl mx-auto p-6 space-y-6">
        {/* Live DORA & IEEE Std 1044-2009 Telemetry Strip for Analyzer/Admin */}
        {telemetry && (activeView === 'canvas' || activeView === 'analyzer-queue') && (
          <div className="grid grid-cols-2 md:grid-cols-5 gap-3 text-xs">
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-3.5">
              <div className="text-slate-400">Mean Time To Resolution (MTTR)</div>
              <div className="text-xl font-bold font-mono text-emerald-400 mt-1">
                {telemetry.mttr_hours !== null ? `${telemetry.mttr_hours}h` : '—'}
              </div>
              <div className="text-[11px] text-slate-500 mt-0.5">
                {telemetry.resolved_count} / {telemetry.total_defects} resolved
              </div>
            </div>

            <div className="bg-slate-900 border border-slate-800 rounded-xl p-3.5">
              <div className="text-slate-400">Mean Triage Lead Time</div>
              <div className="text-xl font-bold font-mono text-sky-400 mt-1">
                {telemetry.triage_lead_time_hours !== null
                  ? `${telemetry.triage_lead_time_hours}h`
                  : '—'}
              </div>
              <div className="text-[11px] text-slate-500 mt-0.5">
                {telemetry.triaged_count} / {telemetry.total_defects} triaged
              </div>
            </div>

            <div className="bg-slate-900 border border-slate-800 rounded-xl p-3.5">
              <div className="text-slate-400">Production Escape Rate</div>
              <div className="text-xl font-bold font-mono text-amber-400 mt-1">
                {telemetry.production_escape_rate_pct}%
              </div>
              <div className="text-[11px] text-slate-500 mt-0.5">
                PROD: {telemetry.environment_counts.PROD} • STG: {telemetry.environment_counts.STAGING} • CI: {telemetry.environment_counts.CI} • DEV: {telemetry.environment_counts.DEV}
              </div>
            </div>

            <div className="bg-slate-900 border border-slate-800 rounded-xl p-3.5">
              <div className="text-slate-400">Regression Rate</div>
              <div className="text-xl font-bold font-mono text-rose-400 mt-1">
                {telemetry.regression_rate_pct}%
              </div>
              <div className="text-[11px] text-slate-500 mt-0.5">
                {telemetry.regression_count} regression defect(s)
              </div>
            </div>

            <div className="bg-slate-900 border border-slate-800 rounded-xl p-3.5">
              <div className="text-slate-400">Engineering Effort Hours</div>
              <div className="text-xl font-bold font-mono text-indigo-400 mt-1">
                {telemetry.total_actual_effort_hours.toFixed(2)}h
              </div>
              <div className="text-[11px] text-slate-500 mt-0.5">
                Avg {telemetry.avg_actual_effort_hours.toFixed(2)}h / anomaly
              </div>
            </div>
          </div>
        )}

        {workbenchDefectId !== null && (
          <DefectAnalyzerWorkbench
            defectId={workbenchDefectId}
            onClose={() => setWorkbenchDefectId(null)}
            onSaved={() => {
              loadAnalyzerCanvasData();
            }}
          />
        )}

        {activeView === 'public-report' && <PublicReportForm />}

        {activeView === 'submit-report' && currentUser && (
          <CustomerReportForm currentUser={currentUser} />
        )}

        {activeView === 'reports' && (
          <DefectReportGrid
            onSelectDefect={(id) => setSelectedDefectModalId(id)}
            onAnalyzeDefect={
              currentUser && currentUser.userlevel >= UserLevel.ANALYZER
                ? (id) => setWorkbenchDefectId(id)
                : undefined
            }
          />
        )}

        {activeView === 'analyzer-queue' && (
          <AnalyzerQueue
            onOpenWorkbench={(id) => setWorkbenchDefectId(id)}
          />
        )}

        {activeView === 'canvas' && (
          <DefectTriageCanvas
            items={queueItems}
            onSelectDefectForWorkbench={(id) => setWorkbenchDefectId(id)}
            onStatusChanged={loadAnalyzerCanvasData}
          />
        )}

        {activeView === 'products' && <ProductManager />}
        {activeView === 'users' && <AdminUserApproval />}
        {activeView === 'profile' && <UserProfileSettings />}
      </main>

      {/* Defect Report Detail Modal */}
      {selectedDefectModalId !== null && (
        <DefectDetailModal
          defectId={selectedDefectModalId}
          canEdit={Boolean(
            currentUser && currentUser.userlevel >= UserLevel.ANALYZER
          )}
          onClose={() => setSelectedDefectModalId(null)}
          onOpenAnalyze={(id) => {
            setSelectedDefectModalId(null);
            setWorkbenchDefectId(id);
          }}
          onUpdated={loadAnalyzerCanvasData}
        />
      )}
    </div>
  );
};

export default App;
