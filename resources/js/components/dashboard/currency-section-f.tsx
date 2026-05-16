import { Link } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    ArrowUpRightIcon,
    CheckCircle2Icon,
    MinusIcon,
    TrendingDownIcon,
    TrendingUpIcon,
} from 'lucide-react';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';

export interface BudgetDetail {
    id: number;
    uuid: string;
    name: string;
    budgeted: number;
    spent: number;
    reserved: number;
    overspend_amount: number;
    has_overspend: boolean;
    percentage: number;
    cycle_start: string;
    cycle_end: string;
    daily_spent: number[];
}

export interface CurrencySectionProps {
    currency: string;
    currencyLocale: string;
    cashOnHand: number;
    totalReserved: number;
    readyToAssign: number;
    totalBudgeted: number;
    totalSpent: number;
    totalOverspend: number;
    budgets: BudgetDetail[];
}

const ALLOC_PALETTE = [
    'bg-violet-500',
    'bg-sky-500',
    'bg-amber-500',
    'bg-pink-500',
    'bg-teal-500',
    'bg-indigo-500',
] as const;

function paletteFor(i: number) {
    return ALLOC_PALETTE[i % ALLOC_PALETTE.length];
}

function daysBetween(a: Date, b: Date): number {
    return Math.floor((b.getTime() - a.getTime()) / (1000 * 60 * 60 * 24));
}

interface DayMetrics {
    daysTotal: number;
    daysPassed: number;
    daysLeft: number;
    burnRate: number;
    avgDailyCap: number;
    daysToCap: number;
    willBust: boolean;
}

function dayMetrics(b: BudgetDetail): DayMetrics {
    const start = new Date(b.cycle_start);
    const end = new Date(b.cycle_end);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    // +1 because both endpoints are inclusive cycle days.
    const daysTotal = Math.max(1, daysBetween(start, end) + 1);
    const daysPassed = Math.max(
        0,
        Math.min(daysTotal, daysBetween(start, today) + 1),
    );
    const daysLeft = Math.max(0, daysTotal - daysPassed);
    const burnRate = daysPassed > 0 ? b.spent / daysPassed : 0;
    const avgDailyCap = daysTotal > 0 ? b.budgeted / daysTotal : 0;
    const remainingCap = Math.max(0, b.budgeted - b.spent);
    const daysToCap = burnRate > 0 ? remainingCap / burnRate : Infinity;
    const willBust =
        !b.has_overspend &&
        burnRate > avgDailyCap &&
        Number.isFinite(daysToCap) &&
        daysToCap < daysLeft;
    return {
        daysTotal,
        daysPassed,
        daysLeft,
        burnRate,
        avgDailyCap,
        daysToCap,
        willBust,
    };
}

type BudgetStatus = 'over' | 'full' | 'tight' | 'idle' | 'ok';

function statusFor(b: BudgetDetail): BudgetStatus {
    if (b.has_overspend) return 'over';
    if (b.percentage >= 100) return 'full';
    if (b.percentage >= 85) return 'tight';
    if (b.percentage < 15) return 'idle';
    return 'ok';
}

const STATUS_DOT: Record<BudgetStatus, string> = {
    over: 'bg-red-500',
    full: 'bg-amber-500',
    tight: 'bg-amber-400',
    idle: 'bg-sky-500',
    ok: 'bg-emerald-500',
};

const STATUS_LABEL: Record<BudgetStatus, string> = {
    over: 'over',
    full: 'full',
    tight: 'tight',
    idle: 'idle',
    ok: 'ok',
};

export function CurrencySectionF({
    currency,
    currencyLocale,
    cashOnHand,
    totalReserved,
    readyToAssign,
    totalBudgeted,
    totalSpent,
    totalOverspend,
    budgets,
}: CurrencySectionProps) {
    const fmt = (n: number) => formatCurrency(n, currency, currencyLocale);
    const isOvercommitted = readyToAssign < 0;
    const incidents = budgets.filter((b) => b.has_overspend);
    const projected = budgets
        .map((b) => ({ b, m: dayMetrics(b) }))
        .filter(({ m }) => m.willBust);

    // Cycle: use the most-common cycle bounds (most budgets typically share one).
    const cycleData = (() => {
        if (budgets.length === 0) return null;
        const counts = new Map<string, { count: number; b: BudgetDetail }>();
        for (const b of budgets) {
            const key = `${b.cycle_start}|${b.cycle_end}`;
            const cur = counts.get(key);
            if (cur) {
                cur.count += 1;
            } else {
                counts.set(key, { count: 1, b });
            }
        }
        const winner = [...counts.values()].sort(
            (a, b) => b.count - a.count,
        )[0].b;
        const start = new Date(winner.cycle_start);
        const end = new Date(winner.cycle_end);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const total = Math.max(1, daysBetween(start, end) + 1);
        const passed = Math.max(0, Math.min(total, daysBetween(start, today) + 1));
        const left = Math.max(0, total - passed);
        return { total, passed, left, start, end };
    })();

    // Determine overall status banner
    const status: 'ok' | 'warn' | 'incident' =
        incidents.length > 0 || isOvercommitted
            ? 'incident'
            : projected.length > 0 ||
                budgets.some((b) => b.percentage >= 85)
              ? 'warn'
              : 'ok';

    const statusBg =
        status === 'ok'
            ? 'bg-emerald-500'
            : status === 'warn'
              ? 'bg-amber-500'
              : 'bg-red-500';

    const warnCount =
        projected.length + budgets.filter((b) => b.percentage >= 85 && !b.has_overspend).length;

    const statusLabel =
        status === 'ok'
            ? 'ALL OK'
            : status === 'warn'
              ? `${warnCount} WARNING${warnCount !== 1 ? 'S' : ''}`
              : `${incidents.length || 1} INCIDENT${(incidents.length || 1) > 1 ? 'S' : ''}`;

    // Allocation stacked bar segments
    const allocBudgets = budgets
        .filter((b) => b.budgeted > 0)
        .slice()
        .sort((a, b) => b.budgeted - a.budgeted);
    const allocDenom = Math.max(cashOnHand, totalBudgeted, 1);
    const allocBudgetTotal = allocBudgets.reduce(
        (sum, b) => sum + b.budgeted,
        0,
    );
    const allocFreePct = Math.max(
        0,
        ((cashOnHand - allocBudgetTotal) / allocDenom) * 100,
    );

    return (
        <section className="bg-card border-border overflow-hidden rounded-2xl border shadow-sm">
            {/* Status bar */}
            <div className="border-border flex flex-wrap items-center justify-between gap-3 border-b px-6 py-3">
                <div className="flex items-center gap-3">
                    <span className="font-mono text-xs font-semibold tracking-[0.18em] uppercase text-foreground">
                        {currency}
                    </span>
                    <span className="text-muted-foreground text-[11px]">
                        Status board · monthly
                    </span>
                </div>
                <div className="flex items-center gap-4">
                    <div className="flex items-center gap-2">
                        <span
                            className={cn('size-2 rounded-full', statusBg)}
                            aria-hidden
                        />
                        <span
                            className={cn(
                                'font-mono text-[11px] font-bold tracking-wider uppercase',
                                status === 'ok' &&
                                    'text-emerald-700 dark:text-emerald-300',
                                status === 'warn' &&
                                    'text-amber-700 dark:text-amber-300',
                                status === 'incident' &&
                                    'text-red-700 dark:text-red-300',
                            )}
                        >
                            {statusLabel}
                        </span>
                    </div>
                    {cycleData && (
                        <div className="text-muted-foreground flex items-center gap-2 text-[11px]">
                            <span>cycle:</span>
                            <span className="text-foreground font-mono font-semibold tabular-nums">
                                {cycleData.left}d
                            </span>
                            <span>left</span>
                        </div>
                    )}
                </div>
            </div>

            {/* Cycle strip */}
            {cycleData && (
                <div className="border-border bg-muted/20 border-b px-6 py-3">
                    <div className="mb-2 flex items-center justify-between text-[10px]">
                        <span className="text-muted-foreground font-mono uppercase tracking-wider">
                            Cycle progress
                        </span>
                        <span className="text-muted-foreground font-mono tabular-nums">
                            {cycleData.passed}/{cycleData.total}d ·{' '}
                            {Math.round((cycleData.passed / cycleData.total) * 100)}%
                        </span>
                    </div>
                    <div className="flex h-3 w-full gap-[1px]">
                        {Array.from({ length: cycleData.total }).map((_, i) => {
                            const isPast = i < cycleData.passed;
                            const isToday = i === cycleData.passed - 1;
                            return (
                                <div
                                    key={i}
                                    className={cn(
                                        'flex-1 rounded-[1px]',
                                        isToday
                                            ? status === 'incident'
                                                ? 'bg-red-500'
                                                : status === 'warn'
                                                  ? 'bg-amber-500'
                                                  : 'bg-emerald-500'
                                            : isPast
                                              ? 'bg-foreground/40'
                                              : 'bg-muted',
                                    )}
                                />
                            );
                        })}
                    </div>
                </div>
            )}

            {/* KPI rows + overall composition */}
            <div className="grid grid-cols-1 gap-px bg-border lg:grid-cols-[1fr_1fr]">
                <div className="bg-card divide-border divide-y">
                    <KpiRow
                        label="CASH"
                        value={fmt(cashOnHand)}
                        note="Suma de cuentas incluidas en presupuesto"
                    />
                    <KpiRow
                        label="RESERVADO"
                        value={fmt(totalReserved)}
                        note={`${cashOnHand > 0 ? Math.round((totalReserved / cashOnHand) * 100) : 0}% del cash comprometido`}
                    />
                    <KpiRow
                        label="READY"
                        value={fmt(readyToAssign)}
                        tone={isOvercommitted ? 'danger' : 'success'}
                        emphasize
                        note={
                            isOvercommitted
                                ? 'Reservaste más de lo que tenés'
                                : readyToAssign === 0
                                  ? 'Todo asignado'
                                  : 'Disponible para asignar'
                        }
                    />
                    <KpiRow
                        label="OVERSPEND"
                        value={fmt(totalOverspend)}
                        tone={totalOverspend > 0 ? 'danger' : 'neutral'}
                        note={
                            incidents.length > 0
                                ? `${incidents.length} incident${incidents.length > 1 ? 's' : ''} abierto${incidents.length > 1 ? 's' : ''}`
                                : 'Sin caps excedidos'
                        }
                    />
                </div>

                <div className="bg-card flex flex-col gap-3 px-6 py-5">
                    <div className="flex items-center justify-between">
                        <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                            Allocation
                        </p>
                        <span className="text-muted-foreground text-[10px]">
                            $ por budget · libre = sin asignar
                        </span>
                    </div>
                    <div className="bg-muted relative flex h-6 w-full overflow-hidden rounded-md">
                        {allocBudgets.map((b, i) => (
                            <div
                                key={b.id}
                                className={cn(
                                    'h-full',
                                    paletteFor(i),
                                )}
                                style={{
                                    width: `${(b.budgeted / allocDenom) * 100}%`,
                                }}
                                title={`${b.name} · ${fmt(b.budgeted)}`}
                            />
                        ))}
                        {allocFreePct > 0 && (
                            <div
                                className="bg-emerald-500/30 h-full"
                                style={{ width: `${allocFreePct}%` }}
                                title={`Libre · ${fmt(cashOnHand - allocBudgetTotal)}`}
                            />
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-x-4 gap-y-1.5 text-[11px]">
                        {allocBudgets.map((b, i) => (
                            <div
                                key={b.id}
                                className="flex items-center justify-between gap-2"
                            >
                                <div className="flex min-w-0 items-center gap-1.5">
                                    <span
                                        className={cn(
                                            'size-2 flex-shrink-0 rounded-full',
                                            paletteFor(i),
                                        )}
                                    />
                                    <span className="text-foreground truncate">
                                        {b.name}
                                    </span>
                                </div>
                                <span className="text-muted-foreground font-mono tabular-nums">
                                    {totalBudgeted > 0
                                        ? `${Math.round((b.budgeted / totalBudgeted) * 100)}%`
                                        : '—'}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Incidents (active overspends) */}
            <div className="border-border border-t px-6 py-4">
                <div className="mb-2 flex items-center justify-between">
                    <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                        Incidents
                    </p>
                    {incidents.length > 0 && (
                        <span className="text-muted-foreground text-[10px]">
                            Budgets sobre el cap
                        </span>
                    )}
                </div>

                {incidents.length === 0 ? (
                    <div className="text-muted-foreground flex items-center gap-2 py-1 text-xs">
                        <CheckCircle2Icon className="size-4 text-emerald-500" />
                        <span>Sin incidentes activos.</span>
                    </div>
                ) : (
                    <ul className="divide-border divide-y">
                        {incidents.map((b) => (
                            <li key={b.id}>
                                <Link
                                    href={`/budgets/${b.uuid}`}
                                    className="group grid grid-cols-[24px_1fr_auto_auto_auto] items-center gap-3 py-2 -mx-2 px-2 rounded-md transition-colors hover:bg-muted/40"
                                >
                                    <AlertTriangleIcon className="size-4 text-red-600 dark:text-red-400" />
                                    <div>
                                        <p className="text-foreground text-sm font-medium group-hover:underline">
                                            {b.name}
                                        </p>
                                        <p className="text-muted-foreground text-[11px]">
                                            cap excedido en este ciclo
                                        </p>
                                    </div>
                                    <span className="font-mono text-[10px] font-bold uppercase tracking-wider rounded bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300 px-1.5 py-0.5">
                                        OVER
                                    </span>
                                    <span className="font-mono text-sm font-semibold tabular-nums text-red-600 dark:text-red-400">
                                        +{fmt(b.overspend_amount)}
                                    </span>
                                    <ArrowUpRightIcon className="text-muted-foreground size-3.5 opacity-0 transition-opacity group-hover:opacity-100" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {/* Projected incidents (will bust before cycle ends) */}
            {projected.length > 0 && (
                <div className="border-border border-t px-6 py-4">
                    <div className="mb-2 flex items-center justify-between">
                        <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                            Projected incidents
                        </p>
                        <span className="text-muted-foreground text-[10px]">
                            A este ritmo se pasan antes de cerrar
                        </span>
                    </div>
                    <ul className="divide-border divide-y">
                        {projected.map(({ b, m }) => (
                            <li key={b.id}>
                                <Link
                                    href={`/budgets/${b.uuid}`}
                                    className="group grid grid-cols-[24px_1fr_auto_auto_auto] items-center gap-3 py-2 -mx-2 px-2 rounded-md transition-colors hover:bg-muted/40"
                                >
                                    <TrendingUpIcon className="size-4 text-amber-600 dark:text-amber-400" />
                                    <div>
                                        <p className="text-foreground text-sm font-medium group-hover:underline">
                                            {b.name}
                                        </p>
                                        <p className="text-muted-foreground text-[11px]">
                                            burn{' '}
                                            <span className="font-mono tabular-nums">
                                                {fmt(m.burnRate)}/d
                                            </span>{' '}
                                            vs cap{' '}
                                            <span className="font-mono tabular-nums">
                                                {fmt(m.avgDailyCap)}/d
                                            </span>
                                        </p>
                                    </div>
                                    <span className="font-mono text-[10px] font-bold uppercase tracking-wider rounded bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 px-1.5 py-0.5">
                                        SOON
                                    </span>
                                    <span className="font-mono text-sm font-semibold tabular-nums text-amber-700 dark:text-amber-300">
                                        busts in {Math.max(0, Math.floor(m.daysToCap))}d
                                    </span>
                                    <ArrowUpRightIcon className="text-muted-foreground size-3.5 opacity-0 transition-opacity group-hover:opacity-100" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Dense table */}
            {budgets.length > 0 && (
                <div className="border-border border-t">
                    <div className="px-6 pt-4 pb-2 flex items-center justify-between">
                        <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                            Budgets
                        </p>
                        <span className="text-muted-foreground text-[10px]">
                            pace = ritmo actual proyectado al cap · sparkline = gasto acumulado del ciclo
                        </span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-border text-muted-foreground border-b font-mono text-[10px] uppercase tracking-wider">
                                    <th className="px-6 py-2 text-left font-semibold">name</th>
                                    <th className="px-3 py-2 text-right font-semibold">spent / cap</th>
                                    <th className="px-3 py-2 text-right font-semibold">%</th>
                                    <th className="px-3 py-2 text-right font-semibold">burn/d</th>
                                    <th className="px-3 py-2 text-center font-semibold">pace</th>
                                    <th className="px-3 py-2 text-center font-semibold">status</th>
                                    <th className="px-6 py-2 text-center font-semibold">sparkline</th>
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {budgets.map((b) => {
                                    const st = statusFor(b);
                                    const m = dayMetrics(b);
                                    return (
                                        <tr
                                            key={b.id}
                                            className="hover:bg-muted/40 transition-colors"
                                        >
                                            <td className="px-6 py-2">
                                                <Link
                                                    href={`/budgets/${b.uuid}`}
                                                    className="text-foreground font-medium hover:underline"
                                                >
                                                    {b.name}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-2 text-right font-mono tabular-nums text-foreground">
                                                <span
                                                    className={cn(
                                                        b.has_overspend
                                                            ? 'text-red-600 dark:text-red-400'
                                                            : 'text-foreground',
                                                    )}
                                                >
                                                    {fmt(b.spent)}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    / {fmt(b.budgeted)}
                                                </span>
                                            </td>
                                            <td
                                                className={cn(
                                                    'px-3 py-2 text-right font-mono font-semibold tabular-nums',
                                                    b.has_overspend
                                                        ? 'text-red-600 dark:text-red-400'
                                                        : 'text-foreground',
                                                )}
                                            >
                                                {Math.round(b.percentage)}%
                                            </td>
                                            <td
                                                className="px-3 py-2 text-right font-mono tabular-nums text-muted-foreground"
                                                title={`Avg cap diario: ${fmt(m.avgDailyCap)}`}
                                            >
                                                {fmt(m.burnRate)}
                                            </td>
                                            <td className="px-3 py-2">
                                                <div className="flex items-center justify-center">
                                                    <PaceBadge b={b} m={m} fmt={fmt} />
                                                </div>
                                            </td>
                                            <td className="px-3 py-2">
                                                <div className="flex items-center justify-center gap-1.5">
                                                    <span
                                                        className={cn(
                                                            'size-1.5 rounded-full',
                                                            STATUS_DOT[st],
                                                        )}
                                                    />
                                                    <span className="text-muted-foreground font-mono text-[10px] uppercase">
                                                        {STATUS_LABEL[st]}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-2">
                                                <div className="flex items-center justify-center">
                                                    <Sparkline
                                                        data={b.daily_spent}
                                                        cap={b.budgeted}
                                                        hasOverspend={b.has_overspend}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

        </section>
    );
}

function KpiRow({
    label,
    value,
    note,
    tone = 'neutral',
    emphasize,
}: {
    label: string;
    value: string;
    note: string;
    tone?: 'neutral' | 'success' | 'danger';
    emphasize?: boolean;
}) {
    const valueClass =
        tone === 'success'
            ? 'text-emerald-700 dark:text-emerald-300'
            : tone === 'danger'
              ? 'text-red-600 dark:text-red-400'
              : 'text-foreground';
    return (
        <div className="grid grid-cols-[120px_1fr] items-center gap-4 px-6 py-3.5">
            <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                {label}
            </p>
            <div className="flex items-baseline justify-between gap-3">
                <p
                    className={cn(
                        'font-mono font-bold tabular-nums',
                        emphasize ? 'text-2xl' : 'text-lg',
                        valueClass,
                    )}
                >
                    {value}
                </p>
                <p className="text-muted-foreground text-right text-[11px] truncate">
                    {note}
                </p>
            </div>
        </div>
    );
}

function PaceBadge({
    b,
    m,
    fmt,
}: {
    b: BudgetDetail;
    m: DayMetrics;
    fmt: (n: number) => string;
}) {
    if (b.has_overspend) {
        return (
            <span
                className="inline-flex items-center gap-1 rounded-sm bg-red-50 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase text-red-700 dark:bg-red-950/50 dark:text-red-300"
                title={`Excedió el cap en ${fmt(b.overspend_amount)}`}
            >
                <AlertTriangleIcon className="size-2.5" />
                over
            </span>
        );
    }
    if (m.daysPassed === 0) {
        return (
            <span className="text-muted-foreground inline-flex items-center gap-1 font-mono text-[10px] uppercase">
                <MinusIcon className="size-2.5" />
                —
            </span>
        );
    }
    if (m.willBust) {
        const d = Math.max(0, Math.floor(m.daysToCap));
        return (
            <span
                className="inline-flex items-center gap-1 rounded-sm bg-amber-50 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"
                title={`Burn ${fmt(m.burnRate)}/d vs avg cap ${fmt(m.avgDailyCap)}/d`}
            >
                <TrendingUpIcon className="size-2.5" />
                busts in {d}d
            </span>
        );
    }
    if (m.burnRate <= m.avgDailyCap) {
        return (
            <span
                className="inline-flex items-center gap-1 rounded-sm bg-emerald-50 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                title={`Burn ${fmt(m.burnRate)}/d vs avg cap ${fmt(m.avgDailyCap)}/d`}
            >
                <TrendingDownIcon className="size-2.5" />
                under
            </span>
        );
    }
    return (
        <span
            className="text-muted-foreground inline-flex items-center gap-1 font-mono text-[10px] uppercase"
            title={`Burn ${fmt(m.burnRate)}/d vs avg cap ${fmt(m.avgDailyCap)}/d`}
        >
            <MinusIcon className="size-2.5" />
            on pace
        </span>
    );
}

function Sparkline({
    data,
    cap,
    hasOverspend,
}: {
    data: number[];
    cap: number;
    hasOverspend: boolean;
}) {
    const width = 96;
    const height = 24;

    if (data.length < 2) {
        return (
            <span className="text-muted-foreground font-mono text-[10px]">
                —
            </span>
        );
    }

    const maxY = Math.max(cap, ...data, 1);
    const stepX = width / (data.length - 1);
    const toY = (v: number) => height - (v / maxY) * height;

    const points = data
        .map((v, i) => `${(i * stepX).toFixed(2)},${toY(v).toFixed(2)}`)
        .join(' ');

    const capY = cap > 0 ? toY(cap) : -1;
    const last = data[data.length - 1];

    const lineClass =
        hasOverspend || last > cap
            ? 'stroke-red-500'
            : last >= cap * 0.85
              ? 'stroke-amber-500'
              : 'stroke-emerald-500';

    const fillClass =
        hasOverspend || last > cap
            ? 'fill-red-500/15'
            : last >= cap * 0.85
              ? 'fill-amber-500/15'
              : 'fill-emerald-500/15';

    // Build closed area for fill: line + back to bottom-right + bottom-left
    const areaPoints = `${points} ${width.toFixed(2)},${height} 0,${height}`;

    return (
        <svg
            width={width}
            height={height}
            viewBox={`0 0 ${width} ${height}`}
            className="overflow-visible"
            aria-label="Gasto acumulado del ciclo"
        >
            <polygon points={areaPoints} className={fillClass} />
            {cap > 0 && capY >= 0 && capY <= height && (
                <line
                    x1={0}
                    y1={capY}
                    x2={width}
                    y2={capY}
                    className="stroke-muted-foreground/40"
                    strokeWidth={1}
                    strokeDasharray="2 2"
                />
            )}
            <polyline
                fill="none"
                points={points}
                className={cn(lineClass, 'transition-all')}
                strokeWidth={1.5}
                strokeLinejoin="round"
                strokeLinecap="round"
            />
            {/* End dot */}
            <circle
                cx={width}
                cy={toY(last)}
                r={1.75}
                className={cn(lineClass, 'fill-current')}
            />
        </svg>
    );
}
