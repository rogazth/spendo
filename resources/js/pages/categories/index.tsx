import { Head } from '@inertiajs/react';
import { ChevronDownIcon, ChevronRightIcon, TagIcon } from 'lucide-react';
import { useState } from 'react';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface CategoryRow {
    id: number;
    uuid: string;
    name: string;
    color: string;
    emoji: string | null;
    is_system: boolean;
    is_user_owned: boolean;
    transaction_count: number;
    total_spent: number;
    total_income: number;
    net: number;
    daily_usage: number[];
    last_used_at: string | null;
}

interface ParentCategoryRow extends CategoryRow {
    children: CategoryRow[];
}

interface Totals {
    categories: number;
    in_use: number;
    idle: number;
    total_spent: number;
    total_income: number;
    top_category: { name: string; total_spent: number } | null;
}

interface Period {
    start: string;
    end: string;
    days: number;
    today: string;
}

interface Props {
    categories: ParentCategoryRow[];
    totals: Totals;
    period: Period;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Categorías', href: '/categories' },
];

const NUMBER_FMT = new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 });

function fmtAmount(n: number): string {
    return NUMBER_FMT.format(Math.round(n));
}

function fmtMonthLabel(period: Period): string {
    const date = new Date(`${period.start}T00:00:00`);
    return date.toLocaleDateString('es-CL', {
        month: 'long',
        year: 'numeric',
    });
}

type CategoryStatus = 'top' | 'active' | 'income' | 'idle' | 'system';

function categoryStatus(c: CategoryRow, topId: number | null): CategoryStatus {
    if (c.transaction_count === 0) {
        return c.is_system && !c.is_user_owned ? 'system' : 'idle';
    }
    if (topId !== null && c.id === topId) return 'top';
    if (c.total_spent === 0 && c.total_income > 0) return 'income';
    return 'active';
}

const STATUS_DOT: Record<CategoryStatus, string> = {
    top: 'bg-amber-500',
    active: 'bg-emerald-500',
    income: 'bg-sky-500',
    idle: 'bg-muted-foreground/40',
    system: 'bg-slate-400',
};

const STATUS_LABEL: Record<CategoryStatus, string> = {
    top: 'top',
    active: 'active',
    income: 'income',
    idle: 'idle',
    system: 'system',
};

export default function CategoriesIndex({ categories, totals, period }: Props) {
    const isEmpty = categories.length === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categorías" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-foreground text-2xl font-bold tracking-tight">
                        Categorías
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Status board · uso del mes en curso ·{' '}
                        <span className="font-mono">
                            {fmtMonthLabel(period)}
                        </span>
                    </p>
                </div>

                {isEmpty ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <TagIcon />
                            </EmptyMedia>
                            <EmptyTitle>
                                No hay categorías disponibles
                            </EmptyTitle>
                            <EmptyDescription>
                                Las categorías se administran mediante el
                                asistente de IA.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <>
                        <GlobalKpiStrip totals={totals} />
                        <CategoriesBoard
                            categories={categories}
                            totals={totals}
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function GlobalKpiStrip({ totals }: { totals: Totals }) {
    const topSpentLabel = totals.top_category
        ? `$${fmtAmount(totals.top_category.total_spent)}`
        : '—';

    return (
        <div className="bg-card border-border grid grid-cols-2 overflow-hidden rounded-2xl border shadow-sm md:grid-cols-4">
            <KpiCell
                label="CATEGORIES"
                value={String(totals.categories)}
                note="top-level"
            />
            <KpiCell
                label="IN USE"
                value={String(totals.in_use)}
                note="con movimiento este mes"
            />
            <KpiCell
                label="IDLE"
                value={String(totals.idle)}
                note="sin movimiento este mes"
                tone={totals.idle > 0 ? 'muted' : 'neutral'}
            />
            <KpiCell
                label="TOP SPEND"
                value={totals.top_category?.name ?? '—'}
                note={topSpentLabel}
                small
            />
        </div>
    );
}

function KpiCell({
    label,
    value,
    note,
    small,
    tone = 'neutral',
}: {
    label: string;
    value: string;
    note: string;
    small?: boolean;
    tone?: 'neutral' | 'muted';
}) {
    return (
        <div className="border-border flex flex-col gap-1.5 border-r border-b px-5 py-4 last:border-r-0 md:border-b-0">
            <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                {label}
            </p>
            <p
                className={cn(
                    'font-mono font-bold tabular-nums',
                    tone === 'muted'
                        ? 'text-muted-foreground'
                        : 'text-foreground',
                    small ? 'truncate text-base' : 'text-2xl',
                )}
                title={value}
            >
                {value}
            </p>
            <p className="text-muted-foreground truncate text-[11px]">{note}</p>
        </div>
    );
}

function CategoriesBoard({
    categories,
    totals,
}: {
    categories: ParentCategoryRow[];
    totals: Totals;
}) {
    const [expanded, setExpanded] = useState<Set<number>>(new Set());
    const topId = totals.top_category
        ? (categories.find((c) => c.name === totals.top_category!.name)?.id ??
          null)
        : null;

    const denom = Math.max(totals.total_spent, 1);

    const inUseCount = totals.in_use;
    const idleCount = totals.idle;
    const status: 'ok' | 'warn' | 'idle' =
        inUseCount === 0 ? 'idle' : idleCount > inUseCount ? 'warn' : 'ok';
    const statusBg =
        status === 'ok'
            ? 'bg-emerald-500'
            : status === 'warn'
              ? 'bg-amber-500'
              : 'bg-slate-400';
    const statusLabel =
        status === 'ok'
            ? `${inUseCount} ACTIVE`
            : status === 'warn'
              ? `${idleCount} IDLE`
              : 'NO ACTIVITY';

    const toggleExpand = (id: number) => {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    return (
        <section className="bg-card border-border overflow-hidden rounded-2xl border shadow-sm">
            <div className="border-border flex flex-wrap items-center justify-between gap-3 border-b px-6 py-3">
                <div className="flex items-center gap-3">
                    <span className="text-foreground font-mono text-xs font-semibold tracking-[0.18em] uppercase">
                        Categories
                    </span>
                    <span className="text-muted-foreground text-[11px]">
                        Usage breakdown · roll-up parent + children
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
                                status === 'idle' && 'text-muted-foreground',
                            )}
                        >
                            {statusLabel}
                        </span>
                    </div>
                    <div className="text-muted-foreground text-[11px]">
                        share = % del gasto · sparkline = uso diario · sumas en
                        major units
                    </div>
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-xs">
                    <thead>
                        <tr className="border-border text-muted-foreground border-b font-mono text-[10px] tracking-wider uppercase">
                            <th className="px-6 py-2 text-left font-semibold">
                                name
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                txns
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                spent
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                income
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                net
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                share
                            </th>
                            <th className="px-6 py-2 text-center font-semibold">
                                trend
                            </th>
                            <th className="px-3 py-2 text-center font-semibold">
                                status
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-border divide-y">
                        {categories.map((parent) => {
                            const isExpanded = expanded.has(parent.id);
                            const hasChildren = parent.children.length > 0;
                            return (
                                <CategoryRows
                                    key={parent.uuid}
                                    parent={parent}
                                    expanded={isExpanded}
                                    hasChildren={hasChildren}
                                    onToggle={() => toggleExpand(parent.id)}
                                    denom={denom}
                                    topId={topId}
                                />
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function CategoryRows({
    parent,
    expanded,
    hasChildren,
    onToggle,
    denom,
    topId,
}: {
    parent: ParentCategoryRow;
    expanded: boolean;
    hasChildren: boolean;
    onToggle: () => void;
    denom: number;
    topId: number | null;
}) {
    return (
        <>
            <CategoryRow
                row={parent}
                level={0}
                hasChildren={hasChildren}
                expanded={expanded}
                onToggle={onToggle}
                denom={denom}
                topId={topId}
            />
            {expanded &&
                parent.children.map((child) => (
                    <CategoryRow
                        key={child.uuid}
                        row={child}
                        level={1}
                        hasChildren={false}
                        expanded={false}
                        onToggle={() => undefined}
                        denom={denom}
                        topId={topId}
                    />
                ))}
        </>
    );
}

function CategoryRow({
    row,
    level,
    hasChildren,
    expanded,
    onToggle,
    denom,
    topId,
}: {
    row: CategoryRow;
    level: 0 | 1;
    hasChildren: boolean;
    expanded: boolean;
    onToggle: () => void;
    denom: number;
    topId: number | null;
}) {
    const status = categoryStatus(row, topId);
    const share =
        denom > 0 ? Math.round((row.total_spent / denom) * 100) : 0;
    const isChild = level === 1;

    return (
        <tr
            className={cn(
                'hover:bg-muted/40 transition-colors',
                isChild && 'bg-muted/10',
                row.transaction_count === 0 && 'opacity-70',
            )}
        >
            <td className={cn('py-2.5 pr-3', isChild ? 'pl-14' : 'pl-6')}>
                <div className="flex items-center gap-2.5">
                    {hasChildren ? (
                        <button
                            type="button"
                            onClick={onToggle}
                            className="hover:bg-muted text-muted-foreground -ml-1 flex size-5 items-center justify-center rounded-sm transition-colors"
                            aria-label={expanded ? 'Colapsar' : 'Expandir'}
                        >
                            {expanded ? (
                                <ChevronDownIcon className="size-3.5" />
                            ) : (
                                <ChevronRightIcon className="size-3.5" />
                            )}
                        </button>
                    ) : !isChild ? (
                        <span className="size-5" />
                    ) : null}

                    <span
                        className="flex size-6 flex-shrink-0 items-center justify-center rounded-md border text-[11px]"
                        style={{
                            backgroundColor: row.color + '20',
                            borderColor: row.color,
                        }}
                    >
                        {row.emoji ?? ''}
                    </span>
                    <span
                        className={cn(
                            'text-foreground',
                            isChild ? 'text-[12px]' : 'font-medium',
                        )}
                    >
                        {row.name}
                    </span>
                    {row.is_system && !row.is_user_owned && (
                        <span className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 font-mono text-[10px] font-bold tracking-wider uppercase">
                            system
                        </span>
                    )}
                </div>
            </td>
            <td className="text-muted-foreground px-3 py-2.5 text-right font-mono tabular-nums">
                {row.transaction_count > 0 ? row.transaction_count : '—'}
            </td>
            <td
                className={cn(
                    'px-3 py-2.5 text-right font-mono font-semibold tabular-nums',
                    row.total_spent > 0
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-muted-foreground',
                )}
            >
                {row.total_spent > 0 ? fmtAmount(row.total_spent) : '—'}
            </td>
            <td
                className={cn(
                    'px-3 py-2.5 text-right font-mono font-semibold tabular-nums',
                    row.total_income > 0
                        ? 'text-emerald-700 dark:text-emerald-300'
                        : 'text-muted-foreground',
                )}
            >
                {row.total_income > 0 ? fmtAmount(row.total_income) : '—'}
            </td>
            <td
                className={cn(
                    'px-3 py-2.5 text-right font-mono tabular-nums',
                    row.net > 0
                        ? 'text-emerald-700 dark:text-emerald-300'
                        : row.net < 0
                          ? 'text-red-600 dark:text-red-400'
                          : 'text-muted-foreground',
                )}
            >
                {row.net !== 0
                    ? `${row.net > 0 ? '+' : '-'}${fmtAmount(Math.abs(row.net))}`
                    : '—'}
            </td>
            <td className="px-3 py-2.5 text-right">
                <div className="flex items-center justify-end gap-2">
                    <div className="bg-muted relative h-1.5 w-16 overflow-hidden rounded-full">
                        <div
                            className="h-full rounded-full bg-foreground/40"
                            style={{ width: `${share}%` }}
                        />
                    </div>
                    <span className="text-muted-foreground w-8 text-right font-mono tabular-nums">
                        {share}%
                    </span>
                </div>
            </td>
            <td className="px-6 py-2.5">
                <div className="flex items-center justify-center">
                    <UsageSparkline data={row.daily_usage} />
                </div>
            </td>
            <td className="px-3 py-2.5">
                <div className="flex items-center justify-center gap-1.5">
                    <span
                        className={cn(
                            'size-1.5 rounded-full',
                            STATUS_DOT[status],
                        )}
                    />
                    <span className="text-muted-foreground font-mono text-[10px] uppercase">
                        {STATUS_LABEL[status]}
                    </span>
                </div>
            </td>
        </tr>
    );
}

function UsageSparkline({ data }: { data: number[] }) {
    const width = 96;
    const height = 22;

    if (data.length < 2 || data.every((v) => v === 0)) {
        return (
            <span className="text-muted-foreground font-mono text-[10px]">
                —
            </span>
        );
    }

    const maxY = Math.max(...data, 1);
    const stepX = width / (data.length - 1);
    const toY = (v: number) => height - (v / maxY) * height;
    const points = data
        .map((v, i) => `${(i * stepX).toFixed(2)},${toY(v).toFixed(2)}`)
        .join(' ');
    const last = data[data.length - 1];
    const areaPoints = `${points} ${width.toFixed(2)},${height} 0,${height}`;

    return (
        <svg
            width={width}
            height={height}
            viewBox={`0 0 ${width} ${height}`}
            className="overflow-visible"
            aria-label="Uso diario"
        >
            <polygon points={areaPoints} className="fill-foreground/10" />
            <polyline
                fill="none"
                points={points}
                className="stroke-foreground/60"
                strokeWidth={1.5}
                strokeLinejoin="round"
                strokeLinecap="round"
            />
            <circle
                cx={width}
                cy={toY(last)}
                r={1.75}
                className="fill-foreground/70"
            />
        </svg>
    );
}
