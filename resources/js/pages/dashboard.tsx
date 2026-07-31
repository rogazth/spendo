import { Head, Link } from '@inertiajs/react';
import { AlertTriangleIcon, CheckCircle2Icon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface DashboardAccount {
    uuid: string;
    name: string;
    emoji: string | null;
    color: string | null;
    balance: number;
    is_default: boolean;
}

interface DashboardAlert {
    uuid: string;
    name: string;
    emoji: string;
    budgeted: number;
    spent: number;
    overspend_amount: number;
    percentage: number;
    status: 'over' | 'tight';
    cycle_end: string;
}

interface CurrencySummary {
    currency: string;
    currency_locale: string;
    total: number;
    reserved: number;
    available: number;
    accounts: DashboardAccount[];
    alerts: DashboardAlert[];
}

interface Props {
    currencySummaries: CurrencySummary[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

export default function Dashboard({ currencySummaries }: Props) {
    const hasAnyData = currencySummaries.some(
        (summary) => summary.accounts.length > 0 || summary.alerts.length > 0,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-8 px-4 py-6 md:px-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        Dashboard
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Cuánta plata hay y cuánta queda libre.
                    </p>
                </div>

                {!hasAnyData ? (
                    <EmptyDashboard />
                ) : (
                    currencySummaries.map((summary) => (
                        <CurrencyBlock
                            key={summary.currency}
                            summary={summary}
                        />
                    ))
                )}
            </div>
        </AppLayout>
    );
}

function CurrencyBlock({ summary }: { summary: CurrencySummary }) {
    const locale = summary.currency_locale ?? 'es-CL';
    const money = (value: number) =>
        formatCurrency(value, summary.currency, locale);

    const overCount = summary.alerts.filter(
        (alert) => alert.status === 'over',
    ).length;
    const tightCount = summary.alerts.length - overCount;
    const reservedShare =
        summary.total > 0
            ? Math.min(
                  100,
                  Math.max(0, (summary.reserved / summary.total) * 100),
              )
            : 0;

    return (
        <section className="space-y-4">
            <div className="rounded-2xl border border-border bg-card p-5 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                Disponible
                            </p>
                            <Badge variant="outline" className="font-mono">
                                {summary.currency}
                            </Badge>
                        </div>
                        <p
                            className={cn(
                                'text-3xl font-bold tracking-tight tabular-nums',
                                summary.available < 0
                                    ? 'text-rose-600 dark:text-rose-500'
                                    : 'text-foreground',
                            )}
                        >
                            {money(summary.available)}
                        </p>
                    </div>

                    <StatusBadge
                        overCount={overCount}
                        tightCount={tightCount}
                    />
                </div>

                <div className="mt-5 space-y-2">
                    <div
                        className="flex h-2 gap-0.5 overflow-hidden rounded-full bg-muted"
                        role="img"
                        aria-label={`${money(summary.reserved)} reservado de ${money(summary.total)}`}
                    >
                        <span
                            className="rounded-full bg-indigo-500 dark:bg-indigo-400"
                            style={{ width: `${reservedShare}%` }}
                        />
                        <span
                            className="rounded-full bg-teal-500"
                            style={{ width: `${100 - reservedShare}%` }}
                        />
                    </div>

                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1.5">
                            <span
                                className="size-2 rounded-full bg-indigo-500 dark:bg-indigo-400"
                                aria-hidden
                            />
                            {money(summary.reserved)} reservado en budgets
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span
                                className="size-2 rounded-full bg-teal-500"
                                aria-hidden
                            />
                            {money(summary.available)} libre
                        </span>
                        <span className="ml-auto tabular-nums">
                            {money(summary.total)} en cuentas
                        </span>
                    </div>
                </div>
            </div>

            {summary.accounts.length > 0 && (
                <div className="divide-y divide-border rounded-2xl border border-border bg-card shadow-sm">
                    {summary.accounts.map((account) => (
                        <Link
                            key={account.uuid}
                            href="/accounts"
                            className="flex items-center justify-between gap-3 p-4 transition-colors first:rounded-t-2xl last:rounded-b-2xl hover:bg-muted/50"
                        >
                            <span className="flex min-w-0 items-center gap-3">
                                <span
                                    className="flex size-9 shrink-0 items-center justify-center rounded-full text-base ring-2"
                                    style={{
                                        backgroundColor: `${account.color ?? '#6366F1'}1A`,
                                        ['--tw-ring-color' as string]: `${account.color ?? '#6366F1'}40`,
                                    }}
                                    aria-hidden
                                >
                                    {account.emoji ?? '🏦'}
                                </span>
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-medium text-foreground">
                                        {account.name}
                                    </span>
                                    {account.is_default && (
                                        <span className="text-xs text-muted-foreground">
                                            Cuenta principal
                                        </span>
                                    )}
                                </span>
                            </span>

                            <span
                                className={cn(
                                    'shrink-0 text-sm font-semibold tabular-nums',
                                    account.balance < 0
                                        ? 'text-rose-600 dark:text-rose-500'
                                        : 'text-foreground',
                                )}
                            >
                                {money(account.balance)}
                            </span>
                        </Link>
                    ))}
                </div>
            )}

            {summary.alerts.length > 0 && (
                <div className="space-y-2">
                    <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                        <AlertTriangleIcon className="size-4 text-amber-500" />
                        Budgets que necesitan atención
                    </h2>

                    <div className="divide-y divide-border rounded-2xl border border-border bg-card shadow-sm">
                        {summary.alerts.map((alert) => (
                            <Link
                                key={alert.uuid}
                                href={`/budgets/${alert.uuid}`}
                                className="flex items-center justify-between gap-3 p-4 transition-colors first:rounded-t-2xl last:rounded-b-2xl hover:bg-muted/50"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-medium text-foreground">
                                        <span aria-hidden>{alert.emoji}</span>{' '}
                                        {alert.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {money(alert.spent)} de{' '}
                                        {money(alert.budgeted)}
                                    </span>
                                </span>

                                <Badge
                                    className={cn(
                                        'shrink-0 tabular-nums',
                                        alert.status === 'over'
                                            ? 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-400'
                                            : 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-400',
                                    )}
                                >
                                    {alert.status === 'over'
                                        ? `+${money(alert.overspend_amount)}`
                                        : `${alert.percentage}%`}
                                </Badge>
                            </Link>
                        ))}
                    </div>
                </div>
            )}
        </section>
    );
}

function StatusBadge({
    overCount,
    tightCount,
}: {
    overCount: number;
    tightCount: number;
}) {
    if (overCount > 0) {
        return (
            <Badge className="border-rose-500/20 bg-rose-500/10 text-rose-700 dark:text-rose-400">
                <AlertTriangleIcon />
                {overCount}{' '}
                {overCount === 1 ? 'budget excedido' : 'budgets excedidos'}
            </Badge>
        );
    }

    if (tightCount > 0) {
        return (
            <Badge className="border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-400">
                <AlertTriangleIcon />
                {tightCount} al límite
            </Badge>
        );
    }

    return (
        <Badge className="border-teal-500/20 bg-teal-500/10 text-teal-700 dark:text-teal-400">
            <CheckCircle2Icon />
            Todo al día
        </Badge>
    );
}

function EmptyDashboard() {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-2xl border border-border bg-card p-12 text-center shadow-sm">
            <p className="font-medium text-foreground">Sin datos todavía</p>
            <p className="text-sm text-muted-foreground">
                Agregá una cuenta o creá un budget para empezar.
            </p>
        </div>
    );
}
