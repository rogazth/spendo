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
import { formatCurrency } from '@/lib/currency';
import type {
    Account,
    BreadcrumbItem,
    Instrument,
    Transaction,
    PaginatedResponse,
    Category,
} from '@/types';

interface Props {
    transactions: PaginatedResponse<Transaction>;
    accounts: Account[];
    instruments: Instrument[];
    categories: Category[];
    filters: {
        instrument_ids?: number[];
        account_ids?: number[];
        category_ids?: number[];
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

export default function TransactionsIndex({
    transactions,
    filters,
}: Props) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const handleDateChange = (next: { dateFrom: string; dateTo: string }) => {
        setDateFrom(next.dateFrom);
        setDateTo(next.dateTo);

        const params: Record<string, string> = {};
        if (next.dateFrom) {
            params.date_from = next.dateFrom;
        }
        if (next.dateTo) {
            params.date_to = next.dateTo;
        }

        router.get('/transactions', params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transacciones" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Transacciones</h1>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <DateFilterDropdown
                        dateFrom={dateFrom}
                        dateTo={dateTo}
                        onChange={handleDateChange}
                    />
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
                        <span className="flex items-center px-4 text-sm text-muted-foreground">
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
