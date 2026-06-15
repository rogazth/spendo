import { Head } from '@inertiajs/react';
import { CurrencySectionF } from '@/components/dashboard/currency-section-f';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface DashboardBudget {
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

interface CurrencySummary {
    currency: string;
    currency_locale: string;
    cash_on_hand: number;
    total_reserved: number;
    ready_to_assign: number;
    total_budgeted: number;
    total_spent: number;
    total_overspend: number;
    budgets: DashboardBudget[];
}

interface Props {
    currencySummaries: CurrencySummary[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

export default function Dashboard({ currencySummaries }: Props) {
    const hasAnyData = currencySummaries.some(
        (summary) => summary.cash_on_hand !== 0 || summary.budgets.length > 0,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div>
                    <h1 className="text-foreground text-2xl font-bold tracking-tight">
                        Dashboard
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Cómo van tus finanzas justo ahora.
                    </p>
                </div>

                {!hasAnyData ? (
                    <EmptyDashboard />
                ) : (
                    currencySummaries.map((summary) => (
                        <CurrencySectionF
                            key={summary.currency}
                            currency={summary.currency}
                            currencyLocale={summary.currency_locale}
                            cashOnHand={summary.cash_on_hand}
                            totalReserved={summary.total_reserved}
                            readyToAssign={summary.ready_to_assign}
                            totalBudgeted={summary.total_budgeted}
                            totalSpent={summary.total_spent}
                            totalOverspend={summary.total_overspend}
                            budgets={summary.budgets}
                        />
                    ))
                )}
            </div>
        </AppLayout>
    );
}

function EmptyDashboard() {
    return (
        <div className="bg-card border-border flex flex-col items-center justify-center gap-2 rounded-2xl border p-12 text-center shadow-sm">
            <p className="text-foreground font-medium">Sin datos todavía</p>
            <p className="text-muted-foreground text-sm">
                Agregá una cuenta o creá un budget para empezar.
            </p>
        </div>
    );
}
