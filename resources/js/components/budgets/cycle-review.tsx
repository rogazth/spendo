import { AlertTriangleIcon, CheckCircle2Icon } from 'lucide-react';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import { formatCurrency, formatCurrencyValue } from '@/lib/currency';
import { cn } from '@/lib/utils';

export interface CycleReviewItem {
    id: number;
    category_id: number | null;
    category_name: string;
    category_color: string;
    category_emoji: string;
    budgeted: number;
    spent: number;
    delta: number;
    percentage: number;
}

export interface CycleReviewDelta {
    category_id: number | null;
    category_name: string;
    category_emoji: string;
    spent: number;
    previous_spent: number;
    delta: number;
}

export interface CycleReviewData {
    start: string;
    end: string;
    is_backfilled: boolean;
    currency_locale: string;
    budgeted: number;
    spent: number;
    leftover: number;
    overspend: number;
    over_items: number;
    item_count: number;
    items: CycleReviewItem[];
    burn: { date: string; spent: number; ideal: number }[];
    top_transactions: {
        id: number;
        uuid: string;
        description: string;
        amount: number;
        category_name: string;
        category_emoji: string;
        transaction_date: string;
    }[];
    deltas: CycleReviewDelta[];
}

const UNDER_COLOR = 'bg-teal-500';
const OVER_COLOR = 'bg-rose-600 dark:bg-rose-500';

const burnConfig = {
    spent: { label: 'Gasto real', color: 'var(--chart-1)' },
    ideal: { label: 'Ritmo ideal', color: 'var(--muted-foreground)' },
} satisfies ChartConfig;

export function CycleReview({
    review,
    currency,
}: {
    review: CycleReviewData;
    currency: string;
}) {
    const locale = review.currency_locale ?? 'es-CL';
    const money = (value: number) => formatCurrency(value, currency, locale);
    const closedWell = review.overspend === 0;

    return (
        <div className="flex flex-col gap-6">
            <Verdict review={review} money={money} closedWell={closedWell} />

            {review.is_backfilled && (
                <p className="text-xs text-muted-foreground">
                    Este ciclo se reconstruyó con los límites actuales del
                    budget porque cerró antes de que Spendo guardara cierres.
                </p>
            )}

            <Waterfall
                items={review.items}
                currency={currency}
                locale={locale}
            />

            <BurnCurve review={review} currency={currency} locale={locale} />

            <div className="grid gap-6 lg:grid-cols-2">
                <TopTransactions
                    review={review}
                    money={money}
                    locale={locale}
                />
                <Deltas review={review} money={money} />
            </div>
        </div>
    );
}

function Verdict({
    review,
    money,
    closedWell,
}: {
    review: CycleReviewData;
    money: (value: number) => string;
    closedWell: boolean;
}) {
    const Icon = closedWell ? CheckCircle2Icon : AlertTriangleIcon;

    return (
        <div className="flex items-start gap-3 rounded-xl border border-border bg-card p-4">
            <Icon
                className={cn(
                    'mt-0.5 size-5 shrink-0',
                    closedWell
                        ? 'text-teal-500'
                        : 'text-rose-600 dark:text-rose-500',
                )}
            />
            <div className="space-y-1">
                <p className="text-base font-semibold text-foreground">
                    {closedWell
                        ? `Cerraste con ${money(review.leftover)} sin gastar.`
                        : `Te pasaste por ${money(review.overspend)}.`}
                </p>
                <p className="text-sm text-muted-foreground">
                    Gastaste {money(review.spent)} de {money(review.budgeted)}.{' '}
                    {review.over_items} de {review.item_count} categorías se
                    pasaron.
                </p>
            </div>
        </div>
    );
}

function Waterfall({
    items,
    currency,
    locale,
}: {
    items: CycleReviewItem[];
    currency: string;
    locale: string;
}) {
    if (items.length === 0) {
        return null;
    }

    const maxMagnitude = Math.max(
        ...items.map((item) => Math.abs(item.delta)),
        1,
    );

    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-lg font-semibold text-foreground">
                    Sobrante y exceso por categoría
                </h2>
                <p className="text-sm text-muted-foreground">
                    A la izquierda lo que sobró, a la derecha lo que se pasó.
                </p>
            </div>

            <div className="space-y-2 rounded-xl border border-border bg-card p-4">
                {items.map((item) => {
                    const isOver = item.delta < 0;
                    const width = (Math.abs(item.delta) / maxMagnitude) * 100;

                    return (
                        <div
                            key={item.id}
                            className="grid grid-cols-[minmax(0,9rem)_1fr] items-center gap-3"
                        >
                            <span className="truncate text-sm text-foreground">
                                <span aria-hidden>{item.category_emoji}</span>{' '}
                                {item.category_name}
                            </span>

                            <div className="flex items-center">
                                <div className="flex flex-1 justify-end pr-px">
                                    {!isOver && (
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {formatCurrencyValue(
                                                    item.delta,
                                                    currency,
                                                    locale,
                                                )}
                                            </span>
                                            <div
                                                className={cn(
                                                    'h-4 rounded-l-sm',
                                                    UNDER_COLOR,
                                                )}
                                                style={{
                                                    width: `${Math.max(width, 1)}%`,
                                                    minWidth: '0.25rem',
                                                }}
                                            />
                                        </div>
                                    )}
                                </div>

                                <div className="h-5 w-px bg-border" />

                                <div className="flex flex-1 justify-start pl-px">
                                    {isOver && (
                                        <div className="flex items-center gap-2">
                                            <div
                                                className={cn(
                                                    'h-4 rounded-r-sm',
                                                    OVER_COLOR,
                                                )}
                                                style={{
                                                    width: `${Math.max(width, 1)}%`,
                                                    minWidth: '0.25rem',
                                                }}
                                            />
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {formatCurrencyValue(
                                                    item.delta,
                                                    currency,
                                                    locale,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function BurnCurve({
    review,
    currency,
    locale,
}: {
    review: CycleReviewData;
    currency: string;
    locale: string;
}) {
    if (review.burn.length === 0) {
        return null;
    }

    const data = review.burn.map((point) => ({
        ...point,
        label: new Date(`${point.date}T00:00:00`).toLocaleDateString(locale, {
            day: 'numeric',
            month: 'short',
        }),
    }));

    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-lg font-semibold text-foreground">
                    Ritmo de gasto
                </h2>
                <p className="text-sm text-muted-foreground">
                    Gasto acumulado contra el ritmo que aguantaba el budget.
                </p>
            </div>

            <div className="rounded-xl border border-border bg-card p-4">
                <ChartContainer config={burnConfig} className="h-56 w-full">
                    <LineChart
                        data={data}
                        margin={{ left: 4, right: 12, top: 8 }}
                    >
                        <CartesianGrid vertical={false} strokeOpacity={0.4} />
                        <XAxis
                            dataKey="label"
                            tickLine={false}
                            axisLine={false}
                            minTickGap={32}
                            tickMargin={8}
                        />
                        <YAxis
                            tickLine={false}
                            axisLine={false}
                            width={56}
                            tickFormatter={(value: number) =>
                                formatCurrencyValue(value, currency, locale)
                            }
                        />
                        <ChartTooltip
                            content={
                                <ChartTooltipContent
                                    formatter={(value) => (
                                        <span className="tabular-nums">
                                            {formatCurrency(
                                                Number(value),
                                                currency,
                                                locale,
                                            )}
                                        </span>
                                    )}
                                />
                            }
                        />
                        <ChartLegend content={<ChartLegendContent />} />
                        <Line
                            dataKey="ideal"
                            stroke="var(--color-ideal)"
                            strokeWidth={2}
                            strokeDasharray="4 4"
                            dot={false}
                        />
                        <Line
                            dataKey="spent"
                            stroke="var(--color-spent)"
                            strokeWidth={2}
                            dot={false}
                        />
                    </LineChart>
                </ChartContainer>
            </div>
        </section>
    );
}

function TopTransactions({
    review,
    money,
    locale,
}: {
    review: CycleReviewData;
    money: (value: number) => string;
    locale: string;
}) {
    return (
        <section className="space-y-3">
            <h2 className="text-lg font-semibold text-foreground">
                Los 5 gastos más grandes
            </h2>

            <div className="divide-y divide-border rounded-xl border border-border bg-card">
                {review.top_transactions.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        No hubo gastos en este ciclo.
                    </p>
                ) : (
                    review.top_transactions.map((transaction) => (
                        <div
                            key={transaction.id}
                            className="flex items-center justify-between gap-3 p-3"
                        >
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-foreground">
                                    {transaction.description}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    <span aria-hidden>
                                        {transaction.category_emoji}
                                    </span>{' '}
                                    {transaction.category_name} ·{' '}
                                    {new Date(
                                        `${transaction.transaction_date}T00:00:00`,
                                    ).toLocaleDateString(locale, {
                                        day: 'numeric',
                                        month: 'short',
                                    })}
                                </p>
                            </div>
                            <span className="shrink-0 text-sm font-semibold text-foreground tabular-nums">
                                {money(transaction.amount)}
                            </span>
                        </div>
                    ))
                )}
            </div>
        </section>
    );
}

function Deltas({
    review,
    money,
}: {
    review: CycleReviewData;
    money: (value: number) => string;
}) {
    return (
        <section className="space-y-3">
            <h2 className="text-lg font-semibold text-foreground">
                Contra el ciclo anterior
            </h2>

            <div className="divide-y divide-border rounded-xl border border-border bg-card">
                {review.deltas.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        Sin ciclo anterior con el que comparar.
                    </p>
                ) : (
                    review.deltas.map((delta) => (
                        <div
                            key={delta.category_id ?? delta.category_name}
                            className="flex items-center justify-between gap-3 p-3"
                        >
                            <span className="truncate text-sm text-foreground">
                                <span aria-hidden>{delta.category_emoji}</span>{' '}
                                {delta.category_name}
                            </span>
                            <span
                                className={cn(
                                    'shrink-0 text-sm font-semibold tabular-nums',
                                    delta.delta > 0
                                        ? 'text-rose-600 dark:text-rose-500'
                                        : 'text-teal-600 dark:text-teal-400',
                                )}
                            >
                                {delta.delta > 0 ? '↑' : '↓'}{' '}
                                {money(Math.abs(delta.delta))}
                            </span>
                        </div>
                    ))
                )}
            </div>
        </section>
    );
}
