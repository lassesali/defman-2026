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
    label: 'ANALYZING (Assigned / Opened)',
    targetStatus: DefectStatus.OPENED,
    xStart: LANE_WIDTH,
    xEnd: LANE_WIDTH * 2,
  },
  {
    id: 'fixed',
    label: 'FIXED (Dev Complete)',
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

function getFaultHexColor(classification: string): number {
  const match = Object.values(IeeeFaultType).find(
    (ft) => ft.toLowerCase() === (classification || '').toLowerCase()
  );
  return match
    ? IEEE_FAULT_HEX_COLORS[match]
    : IEEE_FAULT_HEX_COLORS[IeeeFaultType.OTHER];
}

function getSeverityGlowColor(severity: DefectSeverity | string, priority: string): {
  color: number;
  alpha: number;
  width: number;
} {
  if (severity === DefectSeverity.CRITICAL) {
    return { color: 0xff2e4c, alpha: 0.9, width: 5 };
  }
  if (severity === DefectSeverity.MAJOR || priority === 'most urgent') {
    return { color: 0xf59e0b, alpha: 0.75, width: 3.5 };
  }
  return { color: 0x38bdf8, alpha: 0.45, width: 2 };
}

function computeNodeRadius(effortHours: number, monetaryCostEur: number): number {
  const effortComponent = Math.sqrt(Math.max(1, effortHours)) * 4.5;
  const costComponent = Math.log10(Math.max(10, monetaryCostEur + 10)) * 7;
  return Math.min(54, Math.max(20, Math.round(12 + effortComponent + costComponent)));
}

/**
 * <DefectTriageCanvas />
 * Powered by PixiJS v8 (WebGPU primary with WebGL fallback)
 * Renders defects from form_alist.php / form_raportlist.php with:
 *   - Node fill color by IEEE (1992) Fault Type (defecttype.txt)
 *   - Outer ring glow by Severity & Priority
 *   - Node radius scaled proportionally to Fix Effort (Hours) & Monetary Cost (EUR)
 *   - Pan/zoom viewport, hover telemetry tooltip, click-to-open <DefectAnalyzerWorkbench />,
 *     and drag-and-drop across status lanes triggering PUT /api/defects/{id}/resolve
 */
export const DefectTriageCanvas: React.FC<{
  items: AnalyzerQueueItem[];
  onSelectDefectForWorkbench: (defectId: number) => void;
  onStatusChanged: () => void;
}> = ({ items, onSelectDefectForWorkbench, onStatusChanged }) => {
  const hostRef = useRef<HTMLDivElement | null>(null);
  const [hoveredItem, setHoveredItem] = useState<{
    item: AnalyzerQueueItem;
    screenX: number;
    screenY: number;
  } | null>(null);
  const [rendererBackend, setRendererBackend] = useState<string>('Initializing WebGPU...');
  const [zoomLevel, setZoomLevel] = useState<number>(1);

  useEffect(() => {
    const hostElement = hostRef.current;
    if (!hostElement) return;

    let destroyed = false;
    const app = new Application();

    const initPixiCanvas = async () => {
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

      hostElement.innerHTML = '';
      hostElement.appendChild(app.canvas);

      const rendererType =
        app.renderer.name?.toLowerCase().includes('webgpu') ? 'WebGPU' : 'WebGL2';
      setRendererBackend(`PixiJS v8 (${rendererType})`);

      // Root world container supporting pan & zoom
      const world = new Container();
      app.stage.addChild(world);

      // 1. Draw Status Lane Columns & Headers
      const lanesGraphics = new Graphics();
      world.addChild(lanesGraphics);

      TRIAGE_LANES.forEach((lane, idx) => {
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

      // 2. Group defects by lane and render interactive nodes
      const laneCounts = [0, 0, 0, 0];

      items.forEach((defect) => {
        const laneIndex = mapStatusToLaneIndex(defect.status);
        const slotIndex = laneCounts[laneIndex]++;
        const col = slotIndex % 2;
        const row = Math.floor(slotIndex / 2);

        const initialX =
          TRIAGE_LANES[laneIndex].xStart + 90 + col * 125;
        const initialY = 95 + row * 115;

        const radius = computeNodeRadius(
          Number(defect.fixeffort || 0),
          Number(defect.costofdefect || defect.fixcost || 0)
        );
        const fillHex = getFaultHexColor(defect.classification);
        const glow = getSeverityGlowColor(defect.severity, defect.priority);

        const nodeContainer = new Container();
        nodeContainer.position.set(initialX, initialY);
        nodeContainer.eventMode = 'static';
        nodeContainer.cursor = 'grab';

        // Outer severity/priority glow ring + IEEE 1992 Fault Type circle
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

        // Node label (#ID + Effort/Cost)
        const idLabel = new Text({
          text: `#${defect.id}\n€${Number(defect.costofdefect || defect.fixcost || 0).toFixed(0)}`,
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

        // Drag & Click Interaction State
        let isDragging = false;
        let pointerMovedDistance = 0;
        let dragStartPos = { x: 0, y: 0 };

        nodeContainer.on('pointerover', (e: FederatedPointerEvent) => {
          setHoveredItem({
            item: defect,
            screenX: e.global.x,
            screenY: e.global.y,
          });
        });

        nodeContainer.on('pointerout', () => {
          if (!isDragging) {
            setHoveredItem(null);
          }
        });

        nodeContainer.on('pointerdown', (e: FederatedPointerEvent) => {
          e.stopPropagation();
          isDragging = true;
          pointerMovedDistance = 0;
          nodeContainer.cursor = 'grabbing';
          nodeContainer.alpha = 0.85;
          dragStartPos = world.toLocal(e.global);
        });

        nodeContainer.on('globalpointermove', (e: FederatedPointerEvent) => {
          if (!isDragging) return;
          const currentLocal = world.toLocal(e.global);
          const dx = currentLocal.x - dragStartPos.x;
          const dy = currentLocal.y - dragStartPos.y;
          pointerMovedDistance += Math.hypot(dx, dy);
          nodeContainer.position.x += dx;
          nodeContainer.position.y += dy;
          dragStartPos = currentLocal;
        });

        const finishInteraction = async () => {
          if (!isDragging) return;
          isDragging = false;
          nodeContainer.cursor = 'grab';
          nodeContainer.alpha = 1;

          // If minimal movement, treat as a click -> open <DefectAnalyzerWorkbench />
          if (pointerMovedDistance < 6) {
            onSelectDefectForWorkbench(defect.id);
            return;
          }

          // Determine target status lane from drop X coordinate
          const dropX = Math.max(0, Math.min(CANVAS_WIDTH - 1, nodeContainer.position.x));
          const targetLaneIndex = Math.min(
            TRIAGE_LANES.length - 1,
            Math.floor(dropX / LANE_WIDTH)
          );
          const targetLane = TRIAGE_LANES[targetLaneIndex];

          // Snap X into lane center band
          nodeContainer.position.x =
            targetLane.xStart + LANE_WIDTH / 2;

          if (targetLane.targetStatus !== defect.status) {
            await defmanApi.resolveDefect(defect.id, {
              status: targetLane.targetStatus,
            });
            onStatusChanged();
          }
        };

        nodeContainer.on('pointerup', finishInteraction);
        nodeContainer.on('pointerupoutside', finishInteraction);

        world.addChild(nodeContainer);
      });

      // 3. Stage Panning & Wheel Zooming
      let stagePanning = false;
      let panLast = { x: 0, y: 0 };

      app.stage.eventMode = 'static';
      app.stage.hitArea = app.screen;

      app.stage.on('pointerdown', (e: FederatedPointerEvent) => {
        stagePanning = true;
        panLast = { x: e.global.x, y: e.global.y };
      });

      app.stage.on('globalpointermove', (e: FederatedPointerEvent) => {
        if (!stagePanning) return;
        world.position.x += e.global.x - panLast.x;
        world.position.y += e.global.y - panLast.y;
        panLast = { x: e.global.x, y: e.global.y };
      });

      app.stage.on('pointerup', () => {
        stagePanning = false;
      });

      const onWheel = (e: WheelEvent) => {
        e.preventDefault();
        const factor = e.deltaY < 0 ? 1.1 : 0.9;
        const nextScale = Math.min(2.2, Math.max(0.55, world.scale.x * factor));
        world.scale.set(nextScale);
        setZoomLevel(Number(nextScale.toFixed(2)));
      };

      app.canvas.addEventListener('wheel', onWheel, { passive: false });
    };

    initPixiCanvas();

    return () => {
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
            Visual Defect Triage & IEEE (1992) Cost Canvas
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

      {/* IEEE 1992 Color Legend */}
      <div className="flex flex-wrap items-center gap-2 text-[11px] bg-slate-950/70 p-2.5 rounded-lg border border-slate-800">
        <span className="font-semibold text-slate-400 mr-1">
          IEEE (1992) Fault Color:
        </span>
        {Object.entries(IEEE_FAULT_HEX_COLORS).map(([fault, hex]) => (
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
        ))}
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
            <strong>Product:</strong> {hoveredItem.item.product}
          </div>
          <div>
            <strong>IEEE 1992 Fault:</strong> {hoveredItem.item.classification}
          </div>
          <div>
            <strong>Severity / Priority:</strong> {hoveredItem.item.severity} /{' '}
            {hoveredItem.item.priority}
          </div>
          <div>
            <strong>Fix Effort:</strong> {hoveredItem.item.fixeffort}h |{' '}
            <strong>Fix Cost:</strong> €{hoveredItem.item.fixcost}
          </div>
          <div>
            <strong>Total Cost of Defect:</strong> €{hoveredItem.item.costofdefect}
          </div>
          <div className="text-[10px] text-amber-300 pt-1">
            Click node to open Workbench • Drag across lanes to update status
          </div>
        </div>
      )}
    </div>
  );
};
