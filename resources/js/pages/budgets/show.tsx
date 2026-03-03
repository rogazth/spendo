import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { formatCurrency } from '@/lib/currency';
import type { BreadcrumbItem, Budget, Transaction } from '@/types';

interface CategoryProgress {
    id: number;
    category_id: number;
    category_name: string;
    category_color: string;
    budgeted: number;
    spent: number;
    remaining: number;
    percentage: number;
}

interface Summary {
    budgeted: number;
    spent: number;
    remaining: number;
    percentage: number;
    current_cycle_start: string;
    current_cycle_end: string;
}

interface Range {
    start: string;
    end: string;
}

interface Props {
    budget: Budget;
    summary: Summary;
    categoryProgress: CategoryProgress[];
    transactions: {
        data: Transaction[];
        meta: { total: number; current_page: number; last_page: number };
    };
    scope: 'current' | 'history';
    range: Range;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Presupuestos', href: '/budgets' },
    { title: 'Detalle', href: '#' },
];

export default function BudgetShow({ budget, summary, categoryProgress, transactions, scope, range }: Props) {
    const currency = budget.currency ?? 'CLP';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={budget.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">{budget.name}</h1>
                    <div className="flex gap-2">
                        <Link
                            href={`/budgets/${budget.uuid}`}
                            className={scope === 'current' ? 'font-semibold' : 'text-muted-foreground'}
                        >
                            Ciclo actual
                        </Link>
                        <Link
                            href={`/budgets/${budget.uuid}?scope=history`}
                            className={scope === 'history' ? 'font-semibold' : 'text-muted-foreground'}
                        >
                            Historial
                        </Link>
                    </div>
                </div>

                <div className="text-sm text-muted-foreground">
                    {range.start} — {range.end}
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Presupuestado</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">{formatCurrency(summary.budgeted, currency)}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Gastado</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">{formatCurrency(summary.spent, currency)}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Restante</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">{formatCurrency(summary.remaining, currency)}</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Por categoría</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {categoryProgress.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Sin categorías</p>
                        ) : (
                            <div className="space-y-3">
                                {categoryProgress.map((item) => (
                                    <div key={item.id} className="flex items-center justify-between gap-4">
                                        <div className="flex items-center gap-2 min-w-0">
                                            <div
                                                className="h-3 w-3 rounded-full flex-shrink-0"
                                                style={{ backgroundColor: item.category_color }}
                                            />
                                            <span className="text-sm truncate">{item.category_name}</span>
                                        </div>
                                        <div className="flex items-center gap-4 text-sm flex-shrink-0">
                                            <span>{formatCurrency(item.spent, currency)}</span>
                                            <span className="text-muted-foreground">/ {formatCurrency(item.budgeted, currency)}</span>
                                            <Badge variant={item.percentage >= 100 ? 'destructive' : 'secondary'}>
                                                {item.percentage}%
                                            </Badge>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Transacciones ({transactions.meta.total})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {transactions.data.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Sin transacciones</p>
                        ) : (
                            <div className="space-y-2">
                                {transactions.data.map((tx) => (
                                    <div key={tx.id} className="flex items-center justify-between text-sm">
                                        <div className="min-w-0">
                                            <p className="truncate">{tx.description ?? '—'}</p>
                                            <p className="text-muted-foreground">{tx.transaction_date}</p>
                                        </div>
                                        <span className="font-medium flex-shrink-0 ml-4">
                                            {formatCurrency(tx.amount, tx.currency)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
