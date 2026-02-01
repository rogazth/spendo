import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2, ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem, Account, Transaction, PaginatedResponse } from '@/types';

interface Props {
    account: Account;
    transactions: PaginatedResponse<Transaction>;
}

function formatCurrency(amount: number, currency: string = 'CLP'): string {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
    }).format(amount / 100);
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getAccountTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        checking: 'Cuenta Corriente',
        savings: 'Cuenta de Ahorro',
        cash: 'Efectivo',
        investment: 'Inversión',
    };
    return labels[type] || type;
}

function getTransactionTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        expense: 'Gasto',
        income: 'Ingreso',
        transfer_out: 'Transferencia Saliente',
        transfer_in: 'Transferencia Entrante',
        settlement: 'Liquidación TDC',
        initial_balance: 'Balance Inicial',
    };
    return labels[type] || type;
}

export default function AccountShow({ account, transactions }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cuentas', href: '/accounts' },
        { title: account.name, href: `/accounts/${account.uuid}` },
    ];

    const handleDelete = () => {
        if (confirm(`¿Estás seguro de eliminar la cuenta "${account.name}"?`)) {
            router.delete(`/accounts/${account.uuid}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={account.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/accounts">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">{account.name}</h1>
                            <p className="text-muted-foreground text-sm">
                                {getAccountTypeLabel(account.type)}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/accounts/${account.uuid}/edit`}>
                                <Pencil className="mr-2 h-4 w-4" />
                                Editar
                            </Link>
                        </Button>
                        <Button variant="destructive" onClick={handleDelete}>
                            <Trash2 className="mr-2 h-4 w-4" />
                            Eliminar
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">
                                Balance Actual
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {formatCurrency(account.current_balance, account.currency)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">
                                Balance Inicial
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {formatCurrency(account.initial_balance, account.currency)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">
                                Estado
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <span
                                className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                    account.is_active
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                }`}
                            >
                                {account.is_active ? 'Activa' : 'Inactiva'}
                            </span>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Transacciones Recientes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {transactions.data.length === 0 ? (
                            <p className="text-muted-foreground py-8 text-center">
                                No hay transacciones para esta cuenta
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {transactions.data.map((transaction) => (
                                    <div
                                        key={transaction.uuid}
                                        className="flex items-center justify-between border-b pb-4 last:border-0"
                                    >
                                        <div>
                                            <p className="font-medium">{transaction.description}</p>
                                            <p className="text-muted-foreground text-sm">
                                                {getTransactionTypeLabel(transaction.type)} •{' '}
                                                {formatDate(transaction.transaction_date)}
                                            </p>
                                        </div>
                                        <p
                                            className={`font-medium ${
                                                ['expense', 'transfer_out', 'settlement'].includes(
                                                    transaction.type
                                                )
                                                    ? 'text-red-600'
                                                    : 'text-green-600'
                                            }`}
                                        >
                                            {['expense', 'transfer_out', 'settlement'].includes(
                                                transaction.type
                                            )
                                                ? '-'
                                                : '+'}
                                            {formatCurrency(transaction.amount, transaction.currency)}
                                        </p>
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
