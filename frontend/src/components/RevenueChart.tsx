import { useMemo } from 'react';
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer
} from 'recharts';
import { type Invoice } from '../api/factura';

interface RevenueChartProps {
  invoices: Invoice[];
}

// Tooltip personnalise pour le graphique de chiffre d'affaires
function CustomTooltip({ active, payload, label }: { active?: boolean; payload?: { value: number }[]; label?: string }) {
  if (active && payload && payload.length) {
    return (
      <div style={{
        background: 'var(--bg, #fff)',
        border: '1px solid var(--border)',
        padding: '12px',
        borderRadius: '8px',
        boxShadow: '0 10px 40px -10px rgba(0,0,0,0.15)'
      }}>
        <p style={{ margin: '0 0 4px', fontWeight: 600, color: 'var(--text-h)' }}>{label}</p>
        <p style={{ margin: 0, color: 'var(--accent, #10b981)', fontWeight: 600 }}>
          {payload[0].value.toFixed(2)} EUR HT
        </p>
      </div>
    );
  }
  return null;
}

export default function RevenueChart({ invoices }: RevenueChartProps) {
  const data = useMemo(() => {
    // Generate last 6 months layout
    const months: Record<string, { name: string; total: number; sortKey: number }> = {};
    const d = new Date();
    
    // Support previous months dynamically
    for (let i = 5; i >= 0; i--) {
      const monthDate = new Date(d.getFullYear(), d.getMonth() - i, 1);
      // Capitalize first letter of short month
      const label = monthDate.toLocaleString('fr-FR', { month: 'short' });
      const finalLabel = label.charAt(0).toUpperCase() + label.slice(1);
      
      const key = `${monthDate.getFullYear()}-${monthDate.getMonth()}`;
      months[key] = { name: finalLabel, total: 0, sortKey: monthDate.getTime() };
    }

    // Standard revenue counts SENT, ACKNOWLEDGED, PAID.
    const validStatuses = ['SENT', 'ACKNOWLEDGED', 'PAID'];
    
    invoices.forEach((inv) => {
      if (!validStatuses.includes(inv.status)) return;
      const invD = new Date(inv.issueDate);
      const key = `${invD.getFullYear()}-${invD.getMonth()}`;
      
      if (months[key]) {
        months[key].total += parseFloat(inv.totalExcludingTax || '0');
      }
    });

    return Object.values(months).sort((a, b) => a.sortKey - b.sortKey);
  }, [invoices]);

  return (
    <div style={{ width: '100%', height: 350, marginTop: '20px' }}>
      <ResponsiveContainer>
        <AreaChart
          data={data}
          margin={{ top: 10, right: 10, left: -20, bottom: 0 }}
        >
          <defs>
            <linearGradient id="colorTotal" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="var(--accent, #10b981)" stopOpacity={0.3}/>
              <stop offset="95%" stopColor="var(--accent, #10b981)" stopOpacity={0}/>
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" opacity={0.5} />
          <XAxis 
            dataKey="name" 
            axisLine={false} 
            tickLine={false} 
            tick={{ fill: 'var(--text, #9ca3af)', fontSize: 12 }} 
            dy={10}
          />
          <YAxis 
            axisLine={false} 
            tickLine={false} 
            tick={{ fill: 'var(--text, #9ca3af)', fontSize: 12 }}
            tickFormatter={(val) => val === 0 ? '0' : `${val}€`}
          />
          <Tooltip content={<CustomTooltip />} cursor={{ stroke: 'var(--border)', strokeWidth: 1, strokeDasharray: '4 4' }} />
          <Area 
            type="monotone" 
            dataKey="total" 
            stroke="var(--accent, #10b981)" 
            strokeWidth={3}
            fillOpacity={1} 
            fill="url(#colorTotal)" 
            animationDuration={1500}
            animationEasing="ease-in-out"
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}
