import { Head, InfiniteScroll, router, usePage } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, ReceiptIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    TransactionDayGroup,
    groupTransactionsByDay,
} from '@/components/transactions/transaction-day-group';
import {
    TransactionSummaryCards,
    type TransactionSummaryEntry,
} from '@/components/transactions/transaction-summary-cards';
import {
    ALL_FILTER,
    TransactionsFilterBar,
    type TransactionFilters,
} from '@/components/transactions/transactions-filter-bar';
import { TransactionFormDialog } from '@/components/forms/transaction-form-dialog';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import AppLayout from '@/layouts/app-layout';
import type {
    Account,
    BreadcrumbItem,
    Budget,
    Category,
    PaginatedResponse,
    Transaction,
} from '@/types';

interface Props {
    transactions: PaginatedResponse<Transaction>;
    summary: Record<string, TransactionSummaryEntry>;
    accounts: Account[];
    budgets: Pick<Budget, 'id' | 'uuid' | 'name'>[];
    categories: Category[];
    filters: {
        budget_id?: number | null;
        account_ids?: number[];
        category_ids?: number[];
        date_from?: string | null;
        date_to?: string | null;
        dates?: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Transacciones', href: '/transactions' },
];

function applyFilters(params: Record<string, string | string[]>) {
    router.get('/transactions', params, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function paramsFromFilters(next: TransactionFilters): Record<string, string | string[]> {
    const params: Record<string, string | string[]> = {};
    if (next.dates_all) {
        params.dates = 'all';
    } else {
        if (next.date_from) params.date_from = next.date_from;
        if (next.date_to) params.date_to = next.date_to;
    }
    if (next.budget_id !== ALL_FILTER) params.budget_id = next.budget_id;
    if (next.account_id !== ALL_FILTER) params['account_ids[]'] = next.account_id;
    if (next.category_ids.length > 0) params.category_ids = next.category_ids;
    return params;
}

export default function TransactionsIndex({
    transactions,
    summary,
    accounts,
    budgets,
    categories,
    filters,
}: Props) {
    const { url } = usePage();
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<Transaction | null>(null);

    const hasUserFilters = useMemo(() => {
        const queryString = url.split('?')[1] ?? '';
        if (!queryString) return false;
        const params = new URLSearchParams(queryString);
        return (
            params.has('date_from') ||
            params.has('date_to') ||
            params.has('dates') ||
            params.has('budget_id') ||
            params.has('account_ids[]') ||
            params.has('account_ids') ||
            params.has('category_ids[]') ||
            params.has('category_ids')
        );
    }, [url]);

    const currentFilters: TransactionFilters = {
        budget_id: filters.budget_id ? String(filters.budget_id) : ALL_FILTER,
        account_id: filters.account_ids?.[0] ? String(filters.account_ids[0]) : ALL_FILTER,
        category_ids: filters.category_ids?.map(String) ?? [],
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        dates_all: filters.dates === 'all',
    };

    const selectedAccount = useMemo(
        () =>
            accounts.find(
                (account) => String(account.id) === currentFilters.account_id,
            ) ?? null,
        [accounts, currentFilters.account_id],
    );

    const groups = useMemo(
        () => groupTransactionsByDay(transactions.data),
        [transactions.data],
    );

    const handleFilterChange = (next: Partial<TransactionFilters>) => {
        const merged = { ...currentFilters, ...next };
        applyFilters(paramsFromFilters(merged));
    };

    const clearAllFilters = () => {
        applyFilters({});
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transacciones" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 flex-1 space-y-1">
                        <h1 className="text-foreground text-2xl font-bold tracking-tight">
                            Transacciones
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Revisa y filtra tus movimientos por cuenta, presupuesto o fecha.
                        </p>
                    </div>
                    <Button onClick={() => setCreateOpen(true)}>
                        <PlusIcon />
                        Nueva transacción
                    </Button>
                </div>

                <TransactionsFilterBar
                    filters={currentFilters}
                    accounts={accounts}
                    budgets={budgets}
                    categories={categories}
                    onChange={handleFilterChange}
                    onClear={clearAllFilters}
                    showClear={hasUserFilters}
                />

                {selectedAccount && (
                    <TransactionSummaryCards
                        account={selectedAccount}
                        entry={summary[selectedAccount.currency]}
                    />
                )}

                {transactions.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ReceiptIcon />
                            </EmptyMedia>
                            <EmptyTitle>No hay transacciones para mostrar</EmptyTitle>
                            <EmptyDescription>
                                Crea una nueva o ajusta los filtros para ver más resultados.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <InfiniteScroll
                        data="transactions"
                        className="space-y-4"
                        loading={
                            <div className="text-muted-foreground flex items-center justify-center gap-2 py-4 text-xs">
                                <Loader2Icon className="size-4 animate-spin" />
                                Cargando más transacciones…
                            </div>
                        }
                    >
                        {groups.map((group) => (
                            <TransactionDayGroup
                                key={group.date}
                                date={group.date}
                                transactions={group.transactions}
                                onSelect={(tx) => setEditing(tx)}
                            />
                        ))}
                    </InfiniteScroll>
                )}
            </div>

            <TransactionFormDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                accounts={accounts}
                categories={categories}
            />

            <TransactionFormDialog
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) setEditing(null);
                }}
                transaction={editing ?? undefined}
                accounts={accounts}
                categories={categories}
            />
        </AppLayout>
    );
}
