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
import { formatCurrency, formatCurrencyValue } from '@/lib/currency';

export interface CashFlowPoint {
    start: string;
    end: string;
    income: number;
    expense: number;
    net: number;
}

const config = {
    income: { label: 'Ingreso', color: 'var(--chart-2)' },
    expense: { label: 'Gasto', color: 'var(--chart-1)' },
    net: { label: 'Ahorro neto', color: 'var(--muted-foreground)' },
} satisfies ChartConfig;

/** A cycle is named after the month it ends in: 29 jun–28 jul reads as "jul". */
function monthLabel(end: string, locale: string): string {
    return new Date(`${end}T00:00:00`).toLocaleDateString(locale, {
        month: 'short',
    });
}

function rangeLabel(start: string, end: string, locale: string): string {
    const options: Intl.DateTimeFormatOptions = {
        day: 'numeric',
        month: 'short',
    };

    return `${new Date(`${start}T00:00:00`).toLocaleDateString(locale, options)} — ${new Date(`${end}T00:00:00`).toLocaleDateString(locale, options)}`;
}

export function YearChart({
    points,
    currency,
    locale,
}: {
    points: CashFlowPoint[];
    currency: string;
    locale: string;
}) {
    const data = points.map((point) => ({
        ...point,
        label: monthLabel(point.end, locale),
    }));

    return (
        <div className="rounded-xl border border-border bg-card p-4">
            <ChartContainer config={config} className="h-64 w-full">
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
                        minTickGap={8}
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
                                labelFormatter={(_label, payload) => {
                                    const point = payload?.[0]?.payload as
                                        CashFlowPoint | undefined;

                                    return point
                                        ? rangeLabel(
                                              point.start,
                                              point.end,
                                              locale,
                                          )
                                        : null;
                                }}
                                formatter={(value, name) => (
                                    <span className="flex w-full justify-between gap-3">
                                        <span className="text-muted-foreground">
                                            {config[name as keyof typeof config]
                                                ?.label ?? name}
                                        </span>
                                        <span className="tabular-nums">
                                            {formatCurrency(
                                                Number(value),
                                                currency,
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
    );
}
