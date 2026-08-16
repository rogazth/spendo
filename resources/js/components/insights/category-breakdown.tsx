import { Link } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';
import { useState } from 'react';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';

export interface BreakdownChild {
    key: string;
    name: string;
    emoji: string;
    amount: number;
    count: number;
    share: number;
    delta: number | null;
}

export interface BreakdownRow extends Omit<BreakdownChild, 'key'> {
    key: string;
    id: number | null;
    children: BreakdownChild[];
}

export interface Breakdown {
    total: number;
    rows: BreakdownRow[];
}

function transactionsHref(
    categoryKey: string,
    start: string,
    end: string,
): string | null {
    if (!/^\d+$/.test(categoryKey)) {
        return null;
    }

    return `/transactions?category_ids[]=${categoryKey}&date_from=${start}&date_to=${end}`;
}

export function CategoryBreakdown({
    breakdown,
    currency,
    locale,
    range,
    compareLabel,
}: {
    breakdown: Breakdown;
    currency: string;
    locale: string;
    range: { start: string; end: string };
    compareLabel?: string;
}) {
    const money = (value: number) => formatCurrency(value, currency, locale);

    if (breakdown.rows.length === 0) {
        return (
            <p className="rounded-xl border border-border bg-card p-6 text-center text-sm text-muted-foreground">
                Sin gastos registrados en este período.
            </p>
        );
    }

    return (
        <div className="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card">
            {breakdown.rows.map((row) => (
                <CategoryRow
                    key={row.key}
                    row={row}
                    money={money}
                    range={range}
                    compareLabel={compareLabel}
                />
            ))}

            <div className="flex items-center justify-between gap-3 bg-muted/40 px-4 py-3 text-sm">
                <span className="font-medium text-muted-foreground">
                    Total gastado
                </span>
                <span className="font-semibold text-foreground tabular-nums">
                    {money(breakdown.total)}
                </span>
            </div>
        </div>
    );
}

function CategoryRow({
    row,
    money,
    range,
    compareLabel,
}: {
    row: BreakdownRow;
    money: (value: number) => string;
    range: { start: string; end: string };
    compareLabel?: string;
}) {
    const [open, setOpen] = useState(false);
    const hasChildren = row.children.length > 0;
    const href = transactionsHref(row.key, range.start, range.end);

    return (
        <div>
            <div className="flex items-center gap-3 px-4 py-3">
                <button
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    disabled={!hasChildren}
                    aria-label={open ? 'Contraer' : 'Desplegar'}
                    className={cn(
                        'flex size-5 shrink-0 items-center justify-center rounded text-muted-foreground transition-transform',
                        hasChildren
                            ? 'cursor-pointer hover:text-foreground'
                            : 'invisible',
                        open && 'rotate-90',
                    )}
                >
                    <ChevronRightIcon className="size-4" />
                </button>

                <div className="min-w-0 flex-1">
                    <div className="flex items-baseline justify-between gap-3">
                        <p className="truncate text-sm font-medium text-foreground">
                            <span aria-hidden>{row.emoji}</span>{' '}
                            {href ? (
                                <Link href={href} className="hover:underline">
                                    {row.name}
                                </Link>
                            ) : (
                                row.name
                            )}
                        </p>
                        <span className="shrink-0 text-sm font-semibold text-foreground tabular-nums">
                            {money(row.amount)}
                        </span>
                    </div>

                    <div className="mt-1.5 flex items-center gap-3">
                        <div className="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-chart-1"
                                style={{ width: `${Math.max(row.share, 1)}%` }}
                            />
                        </div>
                        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                            {row.share}% · {row.count} mov
                        </span>
                        <Delta
                            value={row.delta}
                            money={money}
                            label={compareLabel}
                        />
                    </div>
                </div>
            </div>

            {open && hasChildren && (
                <div className="divide-y divide-border/60 border-t border-border/60 bg-muted/20">
                    {row.children.map((child) => {
                        const childHref = transactionsHref(
                            child.key,
                            range.start,
                            range.end,
                        );

                        return (
                            <div
                                key={child.key}
                                className="flex items-center justify-between gap-3 py-2 pr-4 pl-12"
                            >
                                <p className="truncate text-sm text-muted-foreground">
                                    <span aria-hidden>{child.emoji}</span>{' '}
                                    {childHref ? (
                                        <Link
                                            href={childHref}
                                            className="hover:underline"
                                        >
                                            {child.name}
                                        </Link>
                                    ) : (
                                        child.name
                                    )}
                                </p>
                                <div className="flex shrink-0 items-center gap-3">
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {child.share}%
                                    </span>
                                    <span className="text-sm text-foreground tabular-nums">
                                        {money(child.amount)}
                                    </span>
                                    <Delta
                                        value={child.delta}
                                        money={money}
                                        label={compareLabel}
                                    />
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function Delta({
    value,
    money,
    label,
}: {
    value: number | null;
    money: (value: number) => string;
    label?: string;
}) {
    if (value === null || value === 0) {
        return <span className="w-24 shrink-0" />;
    }

    return (
        <span
            title={label ? `Contra ${label}` : undefined}
            className={cn(
                'w-24 shrink-0 text-right text-xs font-medium tabular-nums',
                value > 0
                    ? 'text-rose-600 dark:text-rose-500'
                    : 'text-teal-600 dark:text-teal-400',
            )}
        >
            {value > 0 ? '↑' : '↓'} {money(Math.abs(value))}
        </span>
    );
}
