import { Head, Link } from '@inertiajs/react';
import {
    Bar,
    CartesianGrid,
    ComposedChart,
    Line,
    XAxis,
    YAxis,
} from 'recharts';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatCurrencyValue } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface CashFlowPoint {
    month: string;
    income: number;
    expense: number;
    net: number;
}

interface ComplianceCycle {
    start: string;
    end: string;
    budgeted: number;
    spent: number;
    status: 'kept' | 'tight' | 'over' | 'empty';
    percentage: number;
}

interface ComplianceBudget {
    id: number;
    uuid: string;
    name: string;
    emoji: string;
    cycles: ComplianceCycle[];
    kept: number;
    total: number;
}

interface CategoryTrend {
    name: string;
    emoji: string;
    points: number[];
    latest: number;
    baseline: number;
    delta: number;
    total: number;
}

interface CapSuggestion {
    budget_name: string;
    budget_uuid: string;
    category_name: string;
    category_emoji: string;
    cap: number;
    average: number;
    gap: number;
    cycles: number;
}

interface Section {
    currency: string;
    currency_locale: string;
    cash_flow: CashFlowPoint[];
    budgets: ComplianceBudget[];
    trends: CategoryTrend[];
    cap_suggestions: CapSuggestion[];
}

interface Props {
    sections: Section[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insights', href: '/insights' },
];

const cashFlowConfig = {
    income: { label: 'Ingreso', color: 'var(--chart-2)' },
    expense: { label: 'Gasto', color: 'var(--chart-1)' },
    net: { label: 'Ahorro neto', color: 'var(--muted-foreground)' },
} satisfies ChartConfig;

const STATUS_STYLES: Record<ComplianceCycle['status'], string> = {
    kept: 'bg-teal-500',
    tight: 'bg-amber-500',
    over: 'bg-rose-600 dark:bg-rose-500',
    empty: 'bg-muted',
};

const STATUS_LABELS: Record<ComplianceCycle['status'], string> = {
    kept: 'Cumplido',
    tight: 'Al límite',
    over: 'Excedido',
    empty: 'Sin límites',
};

export default function Insights({ sections }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Insights" />

            <div className="flex flex-1 flex-col gap-8 px-4 py-6 md:px-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        Insights
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Cómo se comportaron tus finanzas en el tiempo.
                    </p>
                </div>

                {sections.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-2xl border border-border bg-card p-12 text-center">
                        <p className="font-medium text-foreground">
                            Todavía no hay historia que mostrar
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Registrá transacciones y dejá cerrar un ciclo de
                            budget para ver tendencias acá.
                        </p>
                    </div>
                ) : (
                    sections.map((section) => (
                        <CurrencyInsights
                            key={section.currency}
                            section={section}
                        />
                    ))
                )}
            </div>
        </AppLayout>
    );
}

function CurrencyInsights({ section }: { section: Section }) {
    const locale = section.currency_locale ?? 'es-CL';
    const money = (value: number) =>
        formatCurrency(value, section.currency, locale);

    return (
        <div className="space-y-8">
            {section.cash_flow.length > 0 && (
                <CashFlow section={section} locale={locale} />
            )}
            {section.budgets.length > 0 && (
                <Compliance section={section} locale={locale} money={money} />
            )}
            {section.trends.length > 0 && (
                <Trends section={section} money={money} />
            )}
            {section.cap_suggestions.length > 0 && (
                <CapSuggestions section={section} money={money} />
            )}
        </div>
    );
}

function monthLabel(month: string, locale: string): string {
    return new Date(`${month}-01T00:00:00`).toLocaleDateString(locale, {
        month: 'short',
        year: '2-digit',
    });
}

function CashFlow({ section, locale }: { section: Section; locale: string }) {
    const data = section.cash_flow.map((point) => ({
        ...point,
        label: monthLabel(point.month, locale),
    }));

    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-lg font-semibold text-foreground">
                    Ingreso vs gasto ({section.currency})
                </h2>
                <p className="text-sm text-muted-foreground">
                    Últimos 12 meses. La línea es lo que quedó.
                </p>
            </div>

            <div className="rounded-xl border border-border bg-card p-4">
                <ChartContainer config={cashFlowConfig} className="h-64 w-full">
                    <ComposedChart
                        data={data}
                        margin={{ left: 4, right: 12, top: 8 }}
                    >
                        <CartesianGrid vertical={false} strokeOpacity={0.4} />
                        <XAxis
                            dataKey="label"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            minTickGap={16}
                        />
                        <YAxis
                            tickLine={false}
                            axisLine={false}
                            width={56}
                            tickFormatter={(value: number) =>
                                formatCurrencyValue(
                                    value,
                                    section.currency,
                                    locale,
                                )
                            }
                        />
                        <ChartTooltip
                            content={
                                <ChartTooltipContent
                                    formatter={(value, name) => (
                                        <span className="flex w-full justify-between gap-3">
                                            <span className="text-muted-foreground">
                                                {cashFlowConfig[
                                                    name as keyof typeof cashFlowConfig
                                                ]?.label ?? name}
                                            </span>
                                            <span className="tabular-nums">
                                                {formatCurrency(
                                                    Number(value),
                                                    section.currency,
                                                    locale,
                                                )}
                                            </span>
                                        </span>
                                    )}
                                />
                            }
                        />
                        <ChartLegend content={<ChartLegendContent />} />
                        <Bar
                            dataKey="income"
                            fill="var(--color-income)"
                            radius={[4, 4, 0, 0]}
                        />
                        <Bar
                            dataKey="expense"
                            fill="var(--color-expense)"
                            radius={[4, 4, 0, 0]}
                        />
                        <Line
                            type="monotone"
                            dataKey="net"
                            stroke="var(--color-net)"
                            strokeWidth={2}
                            dot={false}
                        />
                    </ComposedChart>
                </ChartContainer>
            </div>
        </section>
    );
}

function Compliance({
    section,
    locale,
    money,
}: {
    section: Section;
    locale: string;
    money: (value: number) => string;
}) {
    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-lg font-semibold text-foreground">
                    Racha de cumplimiento
                </h2>
                <p className="text-sm text-muted-foreground">
                    Un cuadro por ciclo cerrado, del más antiguo al más
                    reciente.
                </p>
            </div>

            <div className="space-y-4 rounded-xl border border-border bg-card p-4">
                {section.budgets.map((budget) => (
                    <div key={budget.id} className="space-y-2">
                        <div className="flex items-center justify-between gap-3">
                            <Link
                                href={`/budgets/${budget.uuid}`}
                                className="truncate text-sm font-medium text-foreground hover:underline"
                            >
                                <span aria-hidden>{budget.emoji}</span>{' '}
                                {budget.name}
                            </Link>
                            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                {budget.kept}/{budget.total} ciclos cumplidos
                            </span>
                        </div>

                        <div className="flex flex-wrap gap-1">
                            {budget.cycles.map((cycle) => (
                                <Link
                                    key={cycle.start}
                                    href={`/budgets/${budget.uuid}?cycle=${cycle.start}`}
                                    title={`${monthLabel(cycle.start.slice(0, 7), locale)} · ${STATUS_LABELS[cycle.status]} · ${money(cycle.spent)} de ${money(cycle.budgeted)}`}
                                    aria-label={`${monthLabel(cycle.start.slice(0, 7), locale)}: ${STATUS_LABELS[cycle.status]}`}
                                    className={cn(
                                        'size-6 rounded-sm transition-transform hover:scale-110',
                                        STATUS_STYLES[cycle.status],
                                    )}
                                />
                            ))}
                        </div>
                    </div>
                ))}

                <div className="flex flex-wrap gap-4 pt-1 text-xs text-muted-foreground">
                    {(
                        ['kept', 'tight', 'over'] as ComplianceCycle['status'][]
                    ).map((status) => (
                        <span
                            key={status}
                            className="flex items-center gap-1.5"
                        >
                            <span
                                className={cn(
                                    'size-3 rounded-sm',
                                    STATUS_STYLES[status],
                                )}
                                aria-hidden
                            />
                            {STATUS_LABELS[status]}
                        </span>
                    ))}
                </div>
            </div>
        </section>
    );
}

function Sparkline({ points }: { points: number[] }) {
    const max = Math.max(...points, 1);

    return (
        <div className="flex h-8 items-end gap-0.5" aria-hidden>
            {points.map((point, index) => (
                <span
                    key={index}
                    className="w-1.5 rounded-t-sm bg-chart-1"
                    style={{ height: `${Math.max((point / max) * 100, 4)}%` }}
                />
            ))}
        </div>
    );
}

function Trends({
    section,
    money,
}: {
    section: Section;
    money: (value: number) => string;
}) {
    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-lg font-semibold text-foreground">
                    Categorías que se están moviendo
                </h2>
                <p className="text-sm text-muted-foreground">
                    Gasto de los últimos 6 meses y desvío del último mes contra
                    su promedio.
                </p>
            </div>

            <div className="divide-y divide-border rounded-xl border border-border bg-card">
                {section.trends.map((trend) => (
                    <div
                        key={trend.name}
                        className="flex items-center justify-between gap-4 p-3"
                    >
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-foreground">
                                <span aria-hidden>{trend.emoji}</span>{' '}
                                {trend.name}
                            </p>
                            <p className="text-xs text-muted-foreground tabular-nums">
                                {money(trend.latest)} este mes · promedio{' '}
                                {money(trend.baseline)}
                            </p>
                        </div>

                        <div className="flex items-center gap-4">
                            <Sparkline points={trend.points} />
                            <span
                                className={cn(
                                    'w-24 shrink-0 text-right text-sm font-semibold tabular-nums',
                                    trend.delta > 0
                                        ? 'text-rose-600 dark:text-rose-500'
                                        : 'text-teal-600 dark:text-teal-400',
                                )}
                            >
                                {trend.delta > 0 ? '↑' : '↓'}{' '}
                                {money(Math.abs(trend.delta))}
                            </span>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function CapSuggestions({
    section,
    money,
}: {
    section: Section;
    money: (value: number) => string;
}) {
    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-lg font-semibold text-foreground">
                    Límites que no cuadran
                </h2>
                <p className="text-sm text-muted-foreground">
                    Lo que gastás en promedio por ciclo contra el límite que
                    tenés puesto hoy.
                </p>
            </div>

            <div className="divide-y divide-border rounded-xl border border-border bg-card">
                {section.cap_suggestions.map((suggestion) => (
                    <div
                        key={`${suggestion.budget_uuid}-${suggestion.category_name}`}
                        className="flex items-center justify-between gap-4 p-3"
                    >
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-foreground">
                                <span aria-hidden>
                                    {suggestion.category_emoji}
                                </span>{' '}
                                {suggestion.category_name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {suggestion.budget_name} · {suggestion.cycles}{' '}
                                ciclos
                            </p>
                        </div>

                        <p className="shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                            promedio{' '}
                            <span className="font-semibold text-foreground">
                                {money(suggestion.average)}
                            </span>{' '}
                            · límite {money(suggestion.cap)}
                        </p>
                    </div>
                ))}
            </div>
        </section>
    );
}
