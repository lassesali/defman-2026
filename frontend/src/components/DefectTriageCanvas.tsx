import React, { useEffect, useRef, useState } from 'react';
import {
  Application,
  Container,
  type FederatedPointerEvent,
  Graphics,
  Text,
} from 'pixi.js';
import { defmanApi } from '../api/client';
import {
  type AnalyzerQueueItem,
  DefectSeverity,
  DefectStatus,
  IEEE_FAULT_HEX_COLORS,
  IeeeFaultType,
} from '../types/defman';

interface TriageLaneConfig {
  id: 'open' | 'analyzing' | 'fixed' | 'resolved';
  label: string;
  targetStatus: DefectStatus;
  xStart: number;
  xEnd: number;
}

export interface DefectTriageCanvasProps {
  items: AnalyzerQueueItem[];
  onSelectDefectForWorkbench: (defectId: number) => void;
  onStatusChanged: () => void;
}

const CANVAS_WIDTH = 1200;
const CANVAS_HEIGHT = 680;
const LANE_WIDTH = CANVAS_WIDTH / 4;

const TRIAGE_LANES: readonly TriageLaneConfig[] = [
  {
    id: 'open',
    label: 'OPEN (Submitted / Postponed)',
    targetStatus: DefectStatus.SUBMITTED,
    xStart: 0,
    xEnd: LANE_WIDTH,
  },
  {
    id: 'analyzing',
    label: 'ANALYZING (Triaged / Opened)',
    targetStatus: DefectStatus.OPENED,
    xStart: LANE_WIDTH,
    xEnd: LANE_WIDTH * 2,
  },
  {
    id: 'fixed',
    label: 'FIXED (Commit / Build Verified)',
    targetStatus: DefectStatus.FIXED,
    xStart: LANE_WIDTH * 2,
    xEnd: LANE_WIDTH * 3,
  },
  {
    id: 'resolved',
    label: 'RESOLVED (QA Verified / Closed)',
    targetStatus: DefectStatus.RESOLVED,
    xStart: LANE_WIDTH * 3,
    xEnd: CANVAS_WIDTH,
  },
];

function mapStatusToLaneIndex(status: DefectStatus | string): number {
  switch (status) {
    case DefectStatus.SUBMITTED:
    case DefectStatus.POSTPONED:
      return 0;
    case DefectStatus.ASSIGNED:
    case DefectStatus.OPENED:
      return 1;
    case DefectStatus.FIXED:
      return 2;
    case DefectStatus.RESOLVED:
    case DefectStatus.CLOSED:
      return 3;
    default:
      return 0;
  }
}

function getRootCauseHexColor(rootCauseOrClassification: string): number {
  const normalized = (rootCauseOrClassification || '').trim();
  if (normalized in IEEE_FAULT_HEX_COLORS) {
    return IEEE_FAULT_HEX_COLORS[normalized];
  }
  const match = Object.values(IeeeFaultType).find(function matchFaultCase(ft) {
    return ft.toLowerCase() === normalized.toLowerCase();
  });
  return match
    ? IEEE_FAULT_HEX_COLORS[match]
    : IEEE_FAULT_HEX_COLORS[IeeeFaultType.OTHER];
}

function getSeverityGlowColor(
  severity: DefectSeverity | string,
  priority: string,
  isRegression: boolean
): {
  color: number;
  alpha: number;
  width: number;
} {
  if (severity === DefectSeverity.CRITICAL) {
    return { color: 0xff2e4c, alpha: 0.92, width: 5 };
  }
  if (isRegression || severity === DefectSeverity.MAJOR || priority === 'most urgent') {
    return { color: 0xf59e0b, alpha: 0.8, width: 3.5 };
  }
  return { color: 0x38bdf8, alpha: 0.45, width: 2 };
}

function computeNodeRadiusFromEffort(
  actualEffortHours: number,
  mttrOrLeadHours: number
): number {
  const effortComponent = Math.sqrt(Math.max(1, actualEffortHours)) * 6.5;
  const flowComponent = Math.log10(Math.max(2, mttrOrLeadHours + 2)) * 5.5;
  return Math.min(54, Math.max(22, Math.round(14 + effortComponent + flowComponent)));
}

/**
 * <DefectTriageCanvas />
 * Powered by PixiJS v8 (WebGPU primary with WebGL fallback)
 * Renders anomalies with IEEE Std 1044-2009 & DORA Flow Telemetry:
 *   - Node fill color by IEEE Std 1044-2009 Root Cause Type
 *   - Outer ring glow by Severity, Priority & Regression status
 *   - Node radius scaled proportionally to Actual Effort Hours & Flow Lead Time / MTTR
 *   - Pan/zoom viewport, hover telemetry tooltip, click-to-open <DefectAnalyzerWorkbench />,
 *     and drag-and-drop across status lanes triggering PUT /api/defects/{id}/resolve
 */
export function DefectTriageCanvas({
  items,
  onSelectDefectForWorkbench,
  onStatusChanged,
}: DefectTriageCanvasProps): React.ReactElement {
  const hostRef = useRef<HTMLDivElement | null>(null);
  const [hoveredItem, setHoveredItem] = useState<{
    item: AnalyzerQueueItem;
    screenX: number;
    screenY: number;
  } | null>(null);
  const [rendererBackend, setRendererBackend] = useState<string>('Initializing WebGPU...');
  const [zoomLevel, setZoomLevel] = useState<number>(1);

  useEffect(function setupPixiCanvasEffect() {
    const hostElement = hostRef.current;
    if (!hostElement) {
      return;
    }
    const containerElement: HTMLDivElement = hostElement;

    let destroyed = false;
    const app = new Application();

    async function initPixiCanvas(): Promise<void> {
      await app.init({
        preference: 'webgpu',
        antialias: true,
        width: CANVAS_WIDTH,
        height: CANVAS_HEIGHT,
        backgroundColor: 0x090d16,
        resolution: window.devicePixelRatio || 1,
        autoDensity: true,
      });

      if (destroyed) {
        app.destroy(true);
        return;
      }

      containerElement.innerHTML = '';
      containerElement.appendChild(app.canvas);

      const rendererType = app.renderer.name?.toLowerCase().includes('webgpu')
        ? 'WebGPU'
        : 'WebGL2';
      setRendererBackend(`PixiJS v8 (${rendererType})`);

      const world = new Container();
      app.stage.addChild(world);

      const lanesGraphics = new Graphics();
      world.addChild(lanesGraphics);

      TRIAGE_LANES.forEach(function renderLaneColumn(lane, idx) {
        const bgColor = idx % 2 === 0 ? 0x0f172a : 0x111c33;
        lanesGraphics
          .rect(lane.xStart, 0, LANE_WIDTH, CANVAS_HEIGHT)
          .fill({ color: bgColor, alpha: 0.85 })
          .stroke({ color: 0x1e293b, width: 1.5 });

        const headerText = new Text({
          text: lane.label,
          style: {
            fontFamily: 'Inter, monospace',
            fontSize: 12,
            fontWeight: 'bold',
            fill: 0xfbbf24,
          },
        });
        headerText.position.set(lane.xStart + 16, 16);
        world.addChild(headerText);
      });

      const laneCounts = [0, 0, 0, 0];

      items.forEach(function renderDefectNode(defect) {
        const laneIndex = mapStatusToLaneIndex(defect.status);
        const slotIndex = laneCounts[laneIndex]++;
        const col = slotIndex % 2;
        const row = Math.floor(slotIndex / 2);

        const initialX = TRIAGE_LANES[laneIndex].xStart + 90 + col * 125;
        const initialY = 95 + row * 115;

        const effortHours = Number(defect.actual_effort_hours ?? defect.fixeffort ?? 0);
        const flowHours = Number(
          defect.mttr_hours ?? defect.triage_lead_time_hours ?? defect.days_open * 24
        );
        const radius = computeNodeRadiusFromEffort(effortHours, flowHours);
        const rootCauseLabel = (defect.root_cause_type || defect.classification || IeeeFaultType.OTHER) as string;
        const fillHex = getRootCauseHexColor(rootCauseLabel);
        const glow = getSeverityGlowColor(
          defect.severity,
          defect.priority,
          Boolean(defect.is_regression)
        );

        const nodeContainer = new Container();
        nodeContainer.position.set(initialX, initialY);
        nodeContainer.eventMode = 'static';
        nodeContainer.cursor = 'grab';

        const circleGfx = new Graphics();
        circleGfx
          .circle(0, 0, radius + glow.width + 3)
          .fill({ color: glow.color, alpha: 0.18 })
          .stroke({ color: glow.color, width: glow.width, alpha: glow.alpha });

        circleGfx
          .circle(0, 0, radius)
          .fill({ color: fillHex, alpha: 0.92 })
          .stroke({ color: 0xffffff, width: 1.5, alpha: 0.8 });

        nodeContainer.addChild(circleGfx);

        const envTag = defect.detected_environment || 'PROD';
        const idLabel = new Text({
          text: `#${defect.id} [${envTag}]\n${effortHours.toFixed(1)}h`,
          style: {
            fontFamily: 'monospace',
            fontSize: 10,
            fontWeight: 'bold',
            fill: 0xffffff,
            align: 'center',
          },
        });
        idLabel.anchor.set(0.5);
        nodeContainer.addChild(idLabel);

        let isDragging = false;
        let pointerMovedDistance = 0;
        let dragStartPos = { x: 0, y: 0 };

        function handlePointerOver(e: FederatedPointerEvent): void {
          setHoveredItem({
            item: defect,
            screenX: e.global.x,
            screenY: e.global.y,
          });
        }

        function handlePointerOut(): void {
          if (!isDragging) {
            setHoveredItem(null);
          }
        }

        function handlePointerDown(e: FederatedPointerEvent): void {
          e.stopPropagation();
          isDragging = true;
          pointerMovedDistance = 0;
          nodeContainer.cursor = 'grabbing';
          nodeContainer.alpha = 0.85;
          dragStartPos = world.toLocal(e.global);
        }

        function handleGlobalPointerMove(e: FederatedPointerEvent): void {
          if (!isDragging) {
            return;
          }
          const currentLocal = world.toLocal(e.global);
          const dx = currentLocal.x - dragStartPos.x;
          const dy = currentLocal.y - dragStartPos.y;
          pointerMovedDistance += Math.hypot(dx, dy);
          nodeContainer.position.x += dx;
          nodeContainer.position.y += dy;
          dragStartPos = currentLocal;
        }

        async function finishInteraction(): Promise<void> {
          if (!isDragging) {
            return;
          }
          isDragging = false;
          nodeContainer.cursor = 'grab';
          nodeContainer.alpha = 1;

          if (pointerMovedDistance < 6) {
            onSelectDefectForWorkbench(defect.id);
            return;
          }

          const dropX = Math.max(0, Math.min(CANVAS_WIDTH - 1, nodeContainer.position.x));
          const targetLaneIndex = Math.min(
            TRIAGE_LANES.length - 1,
            Math.floor(dropX / LANE_WIDTH)
          );
          const targetLane = TRIAGE_LANES[targetLaneIndex];

          nodeContainer.position.x = targetLane.xStart + LANE_WIDTH / 2;

          if (targetLane.targetStatus !== defect.status) {
            await defmanApi.resolveDefect(defect.id, {
              status: targetLane.targetStatus,
            });
            onStatusChanged();
          }
        }

        nodeContainer.on('pointerover', handlePointerOver);
        nodeContainer.on('pointerout', handlePointerOut);
        nodeContainer.on('pointerdown', handlePointerDown);
        nodeContainer.on('globalpointermove', handleGlobalPointerMove);
        nodeContainer.on('pointerup', finishInteraction);
        nodeContainer.on('pointerupoutside', finishInteraction);

        world.addChild(nodeContainer);
      });

      let stagePanning = false;
      let panLast = { x: 0, y: 0 };

      app.stage.eventMode = 'static';
      app.stage.hitArea = app.screen;

      function handleStagePointerDown(e: FederatedPointerEvent): void {
        stagePanning = true;
        panLast = { x: e.global.x, y: e.global.y };
      }

      function handleStagePointerMove(e: FederatedPointerEvent): void {
        if (!stagePanning) {
          return;
        }
        world.position.x += e.global.x - panLast.x;
        world.position.y += e.global.y - panLast.y;
        panLast = { x: e.global.x, y: e.global.y };
      }

      function handleStagePointerUp(): void {
        stagePanning = false;
      }

      function onWheel(e: WheelEvent): void {
        e.preventDefault();
        const factor = e.deltaY < 0 ? 1.1 : 0.9;
        const nextScale = Math.min(2.2, Math.max(0.55, world.scale.x * factor));
        world.scale.set(nextScale);
        setZoomLevel(Number(nextScale.toFixed(2)));
      }

      app.stage.on('pointerdown', handleStagePointerDown);
      app.stage.on('globalpointermove', handleStagePointerMove);
      app.stage.on('pointerup', handleStagePointerUp);
      app.canvas.addEventListener('wheel', onWheel, { passive: false });
    }

    initPixiCanvas();

    return function cleanupPixiCanvas(): void {
      destroyed = true;
      try {
        app.destroy(true, { children: true });
      } catch {
        // Safe cleanup if unmount occurs mid-init
      }
    };
  }, [items, onSelectDefectForWorkbench, onStatusChanged]);

  return (
    <div className="bg-slate-900 border border-slate-700 rounded-xl p-4 text-slate-100 shadow-xl space-y-3 relative">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-bold text-amber-400">
            Visual Defect Triage &amp; IEEE 1044-2009 / DORA Flow Canvas
          </h2>
          <p className="text-xs text-slate-400">
            Drag nodes across lanes (Open → Analyzing → Fixed → Resolved) to trigger{' '}
            <code className="text-amber-300">PUT /api/defects/&#123;id&#125;/resolve</code>, or click any node to open{' '}
            <code className="text-amber-300">&lt;DefectAnalyzerWorkbench /&gt;</code>.
          </p>
        </div>
        <div className="flex items-center gap-3 text-xs">
          <span className="px-2.5 py-1 rounded bg-slate-800 border border-slate-700 font-mono text-emerald-400">
            {rendererBackend}
          </span>
          <span className="px-2.5 py-1 rounded bg-slate-800 border border-slate-700 font-mono">
            Zoom: {Math.round(zoomLevel * 100)}%
          </span>
        </div>
      </div>

      {/* IEEE Std 1044-2009 Root Cause Color Legend */}
      <div className="flex flex-wrap items-center gap-2 text-[11px] bg-slate-950/70 p-2.5 rounded-lg border border-slate-800">
        <span className="font-semibold text-slate-400 mr-1">
          IEEE 1044-2009 Root Cause:
        </span>
        {Object.values(IeeeFaultType).map(function renderLegendChip(fault) {
          const hex = IEEE_FAULT_HEX_COLORS[fault] ?? 0x64748b;
          return (
            <span
              key={fault}
              className="flex items-center gap-1.5 px-2 py-0.5 rounded bg-slate-900 border border-slate-800"
            >
              <span
                className="w-2.5 h-2.5 rounded-full inline-block"
                style={{
                  backgroundColor: `#${hex.toString(16).padStart(6, '0')}`,
                }}
              />
              <span>{fault}</span>
            </span>
          );
        })}
      </div>

      {/* PixiJS v8 Viewport Host */}
      <div
        ref={hostRef}
        className="w-full overflow-hidden rounded-lg border border-slate-800 bg-slate-950"
      />

      {/* Hover Telemetry Tooltip */}
      {hoveredItem && (
        <div
          className="pointer-events-none absolute z-30 bg-slate-950/95 border border-amber-500/60 rounded-lg p-3 text-xs shadow-2xl max-w-xs space-y-1"
          style={{
            left: Math.min(CANVAS_WIDTH - 280, hoveredItem.screenX + 24),
            top: Math.max(80, hoveredItem.screenY + 40),
          }}
        >
          <div className="font-bold text-amber-400">
            #{hoveredItem.item.id} — {hoveredItem.item.title}
          </div>
          <div className="text-slate-300">
            <strong>Product:</strong> {hoveredItem.item.product} ({hoveredItem.item.detected_environment || 'PROD'})
          </div>
          <div>
            <strong>IEEE 1044-2009 Root Cause:</strong>{' '}
            {hoveredItem.item.root_cause_type || hoveredItem.item.classification}
          </div>
          <div>
            <strong>Symptom:</strong> {hoveredItem.item.symptom_category || '—'}
          </div>
          <div>
            <strong>Severity / Priority:</strong> {hoveredItem.item.severity} /{' '}
            {hoveredItem.item.priority}
            {Boolean(hoveredItem.item.is_regression) ? ' • [REGRESSION]' : ''}
          </div>
          <div>
            <strong>Actual Effort:</strong>{' '}
            {Number(hoveredItem.item.actual_effort_hours ?? hoveredItem.item.fixeffort ?? 0).toFixed(2)}h
          </div>
          <div>
            <strong>Triage Lead / MTTR:</strong>{' '}
            {hoveredItem.item.triage_lead_time_hours !== null && hoveredItem.item.triage_lead_time_hours !== undefined
              ? `${hoveredItem.item.triage_lead_time_hours}h`
              : '—'}{' '}
            /{' '}
            {hoveredItem.item.mttr_hours !== null && hoveredItem.item.mttr_hours !== undefined
              ? `${hoveredItem.item.mttr_hours}h`
              : 'open'}
          </div>
          {(hoveredItem.item.introducing_ref || hoveredItem.item.resolution_ref) && (
            <div className="font-mono text-[10px] text-emerald-300">
              {hoveredItem.item.introducing_ref ? `Intro: ${hoveredItem.item.introducing_ref} ` : ''}
              {hoveredItem.item.resolution_ref ? `Fix: ${hoveredItem.item.resolution_ref}` : ''}
            </div>
          )}
          <div className="text-[10px] text-amber-300 pt-1">
            Click node to open Telemetry Workbench • Drag across lanes to update status
          </div>
        </div>
      )}
    </div>
  );
}
