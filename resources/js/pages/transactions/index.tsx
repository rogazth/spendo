import { Head, router } from '@inertiajs/react';
import { ReceiptIcon } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { DateFilterDropdown } from '@/components/date-filter-dropdown';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatCurrency } from '@/lib/currency';
import type {
    Account,
    BreadcrumbItem,
    Budget,
    Instrument,
    Transaction,
    PaginatedResponse,
} from '@/types';

interface Props {
    transactions: PaginatedResponse<Transaction>;
    accounts: Account[];
    instruments: Instrument[];
    budgets: Pick<Budget, 'id' | 'uuid' | 'name' | 'account_id'>[];
    filters: {
        budget_id?: number | null;
        budget_account_id?: number | null;
        instrument_ids?: number[];
        account_ids?: number[];
        date_from?: string;
        date_to?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Transacciones', href: '/transactions' },
];

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getTransactionTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        expense: 'Gasto',
        income: 'Ingreso',
        transfer_out: 'Transferencia Saliente',
        transfer_in: 'Transferencia Entrante',
        settlement: 'Liquidación TDC',
    };
    return labels[type] ?? type;
}

function isDebitTransaction(type: string): boolean {
    return ['expense', 'transfer_out', 'settlement'].includes(type);
}

function applyFilters(params: Record<string, string | string[]>) {
    router.get('/transactions', params, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

export default function TransactionsIndex({
    transactions,
    accounts,
    instruments,
    budgets,
    filters,
}: Props) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const selectedBudgetId = filters.budget_id ? String(filters.budget_id) : '';
    const selectedAccountId = filters.account_ids?.[0] ? String(filters.account_ids[0]) : '';
    const selectedInstrumentId = filters.instrument_ids?.[0]
        ? String(filters.instrument_ids[0])
        : '';

    // Budget has a scoped account — the account filter is locked
    const budgetLocksAccount = !!(filters.budget_id && filters.budget_account_id);

    function buildParams(overrides: Record<string, string>) {
        const params: Record<string, string | string[]> = {};
        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;
        if (selectedBudgetId) params.budget_id = selectedBudgetId;
        if (selectedAccountId && !budgetLocksAccount) params['account_ids[]'] = selectedAccountId;
        if (selectedInstrumentId) params['instrument_ids[]'] = selectedInstrumentId;
        return { ...params, ...overrides };
    }

    const handleDateChange = (next: { dateFrom: string; dateTo: string }) => {
        setDateFrom(next.dateFrom);
        setDateTo(next.dateTo);
        const params: Record<string, string | string[]> = {};
        if (next.dateFrom) params.date_from = next.dateFrom;
        if (next.dateTo) params.date_to = next.dateTo;
        if (selectedBudgetId) params.budget_id = selectedBudgetId;
        if (selectedAccountId && !budgetLocksAccount) params['account_ids[]'] = selectedAccountId;
        if (selectedInstrumentId) params['instrument_ids[]'] = selectedInstrumentId;
        applyFilters(params);
    };

    const handleBudgetChange = (value: string) => {
        const params: Record<string, string | string[]> = {};
        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;
        if (value) params.budget_id = value;
        // Clear account filter when switching budgets
        if (selectedInstrumentId) params['instrument_ids[]'] = selectedInstrumentId;
        applyFilters(params);
    };

    const handleAccountChange = (value: string) => {
        const next = buildParams(value ? { 'account_ids[]': value } : {});
        if (!value) delete next['account_ids[]'];
        applyFilters(next);
    };

    const handleInstrumentChange = (value: string) => {
        const next = buildParams(value ? { 'instrument_ids[]': value } : {});
        if (!value) delete next['instrument_ids[]'];
        applyFilters(next);
    };

    const hasActiveFilters =
        selectedBudgetId || selectedAccountId || selectedInstrumentId || dateFrom || dateTo;

    const clearAllFilters = () => {
        setDateFrom('');
        setDateTo('');
        applyFilters({});
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transacciones" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-balance">Transacciones</h1>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {/* Budget filter */}
                    <Select value={selectedBudgetId} onValueChange={handleBudgetChange}>
                        <SelectTrigger className="w-auto min-w-[160px] border-dashed">
                            <SelectValue placeholder="Presupuesto" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Todos los presupuestos</SelectItem>
                            {budgets.map((b) => (
                                <SelectItem key={b.id} value={String(b.id)}>
                                    {b.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Account filter — disabled when budget locks an account */}
                    <Select
                        value={budgetLocksAccount ? String(filters.budget_account_id) : selectedAccountId}
                        onValueChange={handleAccountChange}
                        disabled={budgetLocksAccount}
                    >
                        <SelectTrigger className="w-auto min-w-[160px] border-dashed">
                            <SelectValue placeholder="Cuenta" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Todas las cuentas</SelectItem>
                            {accounts.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {a.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Instrument filter */}
                    <Select value={selectedInstrumentId} onValueChange={handleInstrumentChange}>
                        <SelectTrigger className="w-auto min-w-[160px] border-dashed">
                            <SelectValue placeholder="Instrumento" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Todos los instrumentos</SelectItem>
                            {instruments.map((i) => (
                                <SelectItem key={i.id} value={String(i.id)}>
                                    {i.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Date filter */}
                    <DateFilterDropdown
                        dateFrom={dateFrom}
                        dateTo={dateTo}
                        onChange={handleDateChange}
                    />

                    {hasActiveFilters && (
                        <Button variant="ghost" size="sm" onClick={clearAllFilters}>
                            Limpiar filtros
                        </Button>
                    )}
                </div>

                {transactions.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ReceiptIcon />
                            </EmptyMedia>
                            <EmptyTitle>No tienes transacciones registradas</EmptyTitle>
                            <EmptyDescription>
                                Las transacciones se crean mediante el asistente de IA.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <div className="rounded-lg border">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">
                                            Fecha
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">
                                            Descripción
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">
                                            Tipo
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">
                                            Cuenta
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">
                                            Instrumento
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium text-muted-foreground">
                                            Monto
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transactions.data.map((transaction) => {
                                        const isDebit = isDebitTransaction(transaction.type);
                                        return (
                                            <tr
                                                key={transaction.uuid}
                                                className="border-b last:border-0 hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {formatDate(transaction.transaction_date)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        {transaction.category && (
                                                            <span
                                                                className="h-2.5 w-2.5 shrink-0 rounded-full"
                                                                style={{
                                                                    backgroundColor:
                                                                        transaction.category.color,
                                                                }}
                                                            />
                                                        )}
                                                        <div>
                                                            <p className="font-medium">
                                                                {transaction.description ?? '-'}
                                                            </p>
                                                            {transaction.category && (
                                                                <p className="text-xs text-muted-foreground">
                                                                    {transaction.category.name}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {getTransactionTypeLabel(transaction.type)}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {transaction.account?.name ?? '-'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {transaction.instrument?.name ?? '-'}
                                                </td>
                                                <td
                                                    className={`px-4 py-3 text-right font-medium tabular-nums ${
                                                        isDebit
                                                            ? 'text-red-600'
                                                            : 'text-green-600'
                                                    }`}
                                                >
                                                    {isDebit ? '-' : '+'}
                                                    {formatCurrency(
                                                        transaction.amount,
                                                        transaction.currency,
                                                        transaction.currency_locale ?? 'es-CL',
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {transactions.meta.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {transactions.links.prev && (
                            <Button variant="outline" asChild>
                                <a href={transactions.links.prev}>Anterior</a>
                            </Button>
                        )}
                        <span className="flex items-center px-4 text-sm text-muted-foreground tabular-nums">
                            Página {transactions.meta.current_page} de{' '}
                            {transactions.meta.last_page}
                        </span>
                        {transactions.links.next && (
                            <Button variant="outline" asChild>
                                <a href={transactions.links.next}>Siguiente</a>
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
