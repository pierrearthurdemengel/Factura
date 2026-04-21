import { useMemo, useState, useRef, useCallback, useEffect } from 'react';
import { type Invoice } from '../api/factura';

interface RevenueChartProps {
  invoices: Invoice[];
}

interface DataPoint {
  name: string;
  total: number;
}

export default function RevenueChart({ invoices }: Readonly<RevenueChartProps>) {
  const containerRef = useRef<HTMLDivElement>(null);
  const [size, setSize] = useState({ width: 0, height: 0 });
  const [hoverIndex, setHoverIndex] = useState<number | null>(null);

  useEffect(() => {
    const el = containerRef.current;
    if (!el) return;
    const observer = new ResizeObserver(([entry]) => {
      const { width, height } = entry.contentRect;
      setSize({ width, height });
    });
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const data: DataPoint[] = useMemo(() => {
    const months: Record<string, { name: string; total: number; sortKey: number }> = {};
    const d = new Date();

    for (let i = 5; i >= 0; i--) {
      const monthDate = new Date(d.getFullYear(), d.getMonth() - i, 1);
      const label = monthDate.toLocaleString('fr-FR', { month: 'short' });
      const finalLabel = label.charAt(0).toUpperCase() + label.slice(1);
      const key = `${monthDate.getFullYear()}-${monthDate.getMonth()}`;
      months[key] = { name: finalLabel, total: 0, sortKey: monthDate.getTime() };
    }

    const validStatuses = ['SENT', 'ACKNOWLEDGED', 'PAID'];
    invoices.forEach((inv) => {
      if (!validStatuses.includes(inv.status)) return;
      const invD = new Date(inv.issueDate);
      const key = `${invD.getFullYear()}-${invD.getMonth()}`;
      if (months[key]) {
        months[key].total += Number.parseFloat(inv.totalExcludingTax || '0');
      }
    });

    return Object.values(months).sort((a, b) => a.sortKey - b.sortKey);
  }, [invoices]);

  const margin = { top: 16, right: 16, bottom: 32, left: 56 };
  const w = size.width - margin.left - margin.right;
  const h = size.height - margin.top - margin.bottom;

  const maxVal = useMemo(() => Math.max(...data.map((d) => d.total), 1), [data]);

  const points = useMemo(() => {
    if (w <= 0 || h <= 0) return [];
    return data.map((d, i) => ({
      x: (i / Math.max(data.length - 1, 1)) * w,
      y: h - (d.total / maxVal) * h,
    }));
  }, [data, w, h, maxVal]);

  const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' ');
  const areaPath = linePath + (points.length > 0 ? ` L${points[points.length - 1].x},${h} L${points[0].x},${h} Z` : '');

  const yTicks = useMemo(() => {
    if (maxVal <= 1) {
      return [{ val: 0, y: h }];
    }
    const count = 4;
    // Round to nice numbers
    const rawStep = maxVal / count;
    const magnitude = Math.pow(10, Math.floor(Math.log10(rawStep)));
    const niceStep = Math.ceil(rawStep / magnitude) * magnitude;
    const ticks: { val: number; y: number }[] = [];
    for (let i = 0; i <= count; i++) {
      const val = niceStep * i;
      if (val > maxVal * 1.2 && i > 1) break;
      ticks.push({ val, y: h - (val / maxVal) * h });
    }
    return ticks;
  }, [maxVal, h]);

  const handleMouseMove = useCallback(
    (e: React.MouseEvent<SVGSVGElement>) => {
      if (w <= 0 || data.length === 0) return;
      const rect = e.currentTarget.getBoundingClientRect();
      const mouseX = e.clientX - rect.left - margin.left;
      const step = w / Math.max(data.length - 1, 1);
      const idx = Math.round(Math.max(0, Math.min(mouseX / step, data.length - 1)));
      setHoverIndex(idx);
    },
    [w, data.length, margin.left],
  );

  if (size.width === 0) {
    return <div ref={containerRef} style={{ width: '100%', height: '100%', minHeight: 200 }} />;
  }

  return (
    <div ref={containerRef} style={{ width: '100%', height: '100%', minHeight: 200 }}>
      <svg
        width={size.width}
        height={size.height}
        onMouseMove={handleMouseMove}
        onMouseLeave={() => setHoverIndex(null)}
        style={{ display: 'block' }}
      >
        <defs>
          <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="5%" stopColor="var(--accent, #7c3aed)" stopOpacity={0.25} />
            <stop offset="95%" stopColor="var(--accent, #7c3aed)" stopOpacity={0} />
          </linearGradient>
        </defs>

        <g transform={`translate(${margin.left},${margin.top})`}>
          {/* Grid lines */}
          {yTicks.map((t) => (
            <line key={`grid-${t.val}`} x1={0} y1={t.y} x2={w} y2={t.y} stroke="var(--border)" strokeDasharray="3 3" opacity={0.5} />
          ))}

          {/* Area */}
          {points.length > 1 && <path d={areaPath} fill="url(#areaGrad)" />}

          {/* Line */}
          {points.length > 1 && (
            <path d={linePath} fill="none" stroke="var(--accent, #7c3aed)" strokeWidth={3} strokeLinecap="round" strokeLinejoin="round" />
          )}

          {/* Data dots */}
          {points.map((p, i) => (
            <circle key={data[i].name} cx={p.x} cy={p.y} r={hoverIndex === i ? 5 : 3} fill="var(--accent, #7c3aed)" stroke="var(--surface, #fff)" strokeWidth={2} />
          ))}

          {/* Y axis labels */}
          {yTicks.map((t) => (
            <text key={`label-${t.val}`} x={-8} y={t.y} textAnchor="end" dominantBaseline="middle" fill="var(--text)" fontSize={12}>
              {t.val === 0 ? '0' : `${Math.round(t.val)}\u20AC`}
            </text>
          ))}

          {/* X axis labels */}
          {data.map((d, i) => (
            <text
              key={d.name}
              x={points[i]?.x ?? 0}
              y={h + 20}
              textAnchor="middle"
              fill="var(--text)"
              fontSize={12}
            >
              {d.name}
            </text>
          ))}

          {/* Hover tooltip */}
          {hoverIndex !== null && points[hoverIndex] && (
            <g>
              <line x1={points[hoverIndex].x} y1={0} x2={points[hoverIndex].x} y2={h} stroke="var(--border)" strokeDasharray="4 4" />
              <foreignObject
                x={Math.min(points[hoverIndex].x - 60, w - 130)}
                y={Math.max(points[hoverIndex].y - 60, 0)}
                width={130}
                height={52}
              >
                <div
                  style={{
                    background: 'var(--surface, #fff)',
                    border: '1px solid var(--border)',
                    padding: '6px 10px',
                    borderRadius: '8px',
                    boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
                    fontSize: '12px',
                  }}
                >
                  <div style={{ fontWeight: 600, color: 'var(--text-h)' }}>{data[hoverIndex].name}</div>
                  <div style={{ color: 'var(--accent, #7c3aed)', fontWeight: 600 }}>
                    {data[hoverIndex].total.toFixed(2)} € HT
                  </div>
                </div>
              </foreignObject>
            </g>
          )}
        </g>
      </svg>
    </div>
  );
}
