import { Head, router } from '@inertiajs/react';
import { ArrowLeftIcon, CalendarRangeIcon } from 'lucide-react';
import { useMemo } from 'react';
import { PolarAngleAxis, RadialBar, RadialBarChart } from 'recharts';
import { index, show } from '@/actions/App/Http/Controllers/BudgetController';
import { getTransactionColumns } from '@/components/data-table/columns/transaction-columns';
import { DataTable } from '@/components/data-table/data-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import type {
    BreadcrumbItem,
    Budget,
    PaginatedResponse,
    Transaction,
} from '@/types';

interface CategoryProgressItem {
    id: number;
    category_id: number;
    category_name: string;
    category_color: string;
    budgeted: number;
    spent: number;
    remaining: number;
    percentage: number;
}

interface Props {
    budget: Budget;
    summary: {
        budgeted: number;
        spent: number;
        remaining: number;
        percentage: number;
        current_cycle_start: string;
        current_cycle_end: string;
    };
    categoryProgress: CategoryProgressItem[];
    transactions: PaginatedResponse<Transaction>;
    scope: 'current' | 'history';
    range: {
        start: string;
        end: string;
    };
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function BudgetShow({
    budget,
    summary,
    categoryProgress,
    transactions,
    scope,
    range,
}: Props) {
    const budgetShowUrl = show.url(budget);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Budgets', href: index.url() },
        { title: budget.name, href: budgetShowUrl },
    ];

    const columns = useMemo(
        () =>
            getTransactionColumns({
                onEdit: () => undefined,
                onDelete: () => undefined,
                showActions: false,
            }),
        [],
    );

    const displayCurrency =
        budget.currency || budget.account?.currency || 'CLP';
    const displayLocale = budget.account?.currency_locale || 'es-CL';

    const isOverBudget = summary.remaining < 0;
    const mainChartData = [
        { value: summary.percentage, fill: 'hsl(var(--chart-2))' },
    ];
    const mainChartConfig = {
        value: { label: 'Uso', color: 'hsl(var(--chart-2))' },
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Budget ${budget.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="space-y-1">
                        <div className="flex items-center gap-1">
                            <Button variant="ghost" size="icon" asChild className="-ml-2">
                                <a href={index.url()}>
                                    <ArrowLeftIcon className="h-4 w-4" />
                                </a>
                            </Button>
                            <h1 className="text-2xl font-bold">{budget.name}</h1>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {budget.description || 'Sin descripción'}
                        </p>
                    </div>

                    <div className="rounded-lg border px-3 py-2 text-sm text-muted-foreground">
                        <span className="inline-flex items-center gap-2">
                            <CalendarRangeIcon className="h-4 w-4" />
                            {formatDate(range.start)} - {formatDate(range.end)}
                        </span>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-muted-foreground">
                                Presupuestado
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">
                                {formatCurrency(
                                    summary.budgeted,
                                    displayCurrency,
                                    displayLocale,
                                )}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-muted-foreground">
                                Gastado
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold text-red-600">
                                {formatCurrency(
                                    summary.spent,
                                    displayCurrency,
                                    displayLocale,
                                )}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-muted-foreground">
                                Disponible
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className={`text-2xl font-semibold ${isOverBudget ? 'text-red-600' : 'text-green-600'}`}
                            >
                                {formatCurrency(
                                    summary.remaining,
                                    displayCurrency,
                                    displayLocale,
                                )}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Resumen del presupuesto</CardTitle>
                    </CardHeader>
                    <CardContent className="flex items-center justify-center">
                        <ChartContainer
                            config={mainChartConfig}
                            className="aspect-square h-[280px] w-[280px]"
                        >
                            <RadialBarChart
                                data={mainChartData}
                                innerRadius="75%"
                                outerRadius="100%"
                                startAngle={90}
                                endAngle={-270}
                            >
                                <PolarAngleAxis
                                    type="number"
                                    domain={[0, 100]}
                                    angleAxisId={0}
                                    tick={false}
                                />
                                <RadialBar
                                    dataKey="value"
                                    angleAxisId={0}
                                    background={{ fill: 'hsl(var(--muted))' }}
                                    cornerRadius={8}
                                />
                                <ChartTooltip
                                    cursor={false}
                                    content={
                                        <ChartTooltipContent
                                            nameKey="value"
                                            formatter={(value) =>
                                                `${Number(value).toFixed(1)}%`
                                            }
                                        />
                                    }
                                />
                                <text
                                    x="50%"
                                    y="45%"
                                    textAnchor="middle"
                                    dominantBaseline="middle"
                                    className={`fill-foreground text-2xl font-bold ${isOverBudget ? 'fill-red-600' : ''}`}
                                >
                                    {formatCurrency(
                                        summary.remaining,
                                        displayCurrency,
                                        displayLocale,
                                    )}
                                </text>
                                <text
                                    x="50%"
                                    y="58%"
                                    textAnchor="middle"
                                    dominantBaseline="middle"
                                    className="fill-muted-foreground text-xs"
                                >
                                    Disponible
                                </text>
                            </RadialBarChart>
                        </ChartContainer>
                    </CardContent>
                </Card>

                {categoryProgress.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Detalle por categoría</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                                {categoryProgress.map((item) => {
                                    const itemOverBudget = item.remaining < 0;
                                    return (
                                        <div
                                            key={item.id}
                                            className="flex flex-col items-center gap-1"
                                        >
                                            <ChartContainer
                                                config={{
                                                    value: {
                                                        label: item.category_name,
                                                        color: item.category_color,
                                                    },
                                                }}
                                                className="aspect-square h-[120px] w-[120px]"
                                            >
                                                <RadialBarChart
                                                    data={[
                                                        {
                                                            value: item.percentage,
                                                            fill: item.category_color,
                                                        },
                                                    ]}
                                                    innerRadius="70%"
                                                    outerRadius="100%"
                                                    startAngle={90}
                                                    endAngle={-270}
                                                >
                                                    <PolarAngleAxis
                                                        type="number"
                                                        domain={[0, 100]}
                                                        angleAxisId={0}
                                                        tick={false}
                                                    />
                                                    <RadialBar
                                                        dataKey="value"
                                                        angleAxisId={0}
                                                        background={{
                                                            fill: 'hsl(var(--muted))',
                                                        }}
                                                        cornerRadius={6}
                                                    />
                                                    <ChartTooltip
                                                        cursor={false}
                                                        content={
                                                            <ChartTooltipContent
                                                                nameKey="value"
                                                                formatter={(value) =>
                                                                    `${Number(value).toFixed(1)}%`
                                                                }
                                                            />
                                                        }
                                                    />
                                                    <text
                                                        x="50%"
                                                        y="50%"
                                                        textAnchor="middle"
                                                        dominantBaseline="middle"
                                                        className="fill-foreground text-sm font-semibold"
                                                    >
                                                        {item.percentage.toFixed(0)}%
                                                    </text>
                                                </RadialBarChart>
                                            </ChartContainer>
                                            <span className="text-center text-sm font-medium">
                                                {item.category_name}
                                            </span>
                                            <span
                                                className={`text-center text-xs ${itemOverBudget ? 'text-red-600' : 'text-muted-foreground'}`}
                                            >
                                                {formatCurrency(
                                                    Math.abs(item.remaining),
                                                    displayCurrency,
                                                    displayLocale,
                                                )}{' '}
                                                {itemOverBudget
                                                    ? 'excedido'
                                                    : 'disponible'}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Transacciones del budget</CardTitle>
                        <div className="flex items-center gap-2">
                            <Button
                                variant={
                                    scope === 'current' ? 'default' : 'outline'
                                }
                                size="sm"
                                onClick={() =>
                                    router.get(
                                        show.url(budget, {
                                            query: { scope: 'current' },
                                        }),
                                        {},
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                            replace: true,
                                        },
                                    )
                                }
                            >
                                Ciclo actual
                            </Button>
                            <Button
                                variant={
                                    scope === 'history' ? 'default' : 'outline'
                                }
                                size="sm"
                                onClick={() =>
                                    router.get(
                                        show.url(budget, {
                                            query: { scope: 'history' },
                                        }),
                                        {},
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                            replace: true,
                                        },
                                    )
                                }
                            >
                                Histórico
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <DataTable columns={columns} data={transactions.data} />

                        {transactions.meta.last_page > 1 && (
                            <div className="flex justify-center gap-2">
                                {transactions.links.prev && (
                                    <Button variant="outline" asChild>
                                        <a href={transactions.links.prev}>
                                            Anterior
                                        </a>
                                    </Button>
                                )}
                                <span className="flex items-center px-4 text-sm text-muted-foreground">
                                    Página {transactions.meta.current_page} de{' '}
                                    {transactions.meta.last_page}
                                </span>
                                {transactions.links.next && (
                                    <Button variant="outline" asChild>
                                        <a href={transactions.links.next}>
                                            Siguiente
                                        </a>
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
