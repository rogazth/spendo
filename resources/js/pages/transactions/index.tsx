import { Head, Link, router } from '@inertiajs/react';
import { Plus, MoreHorizontal, Pencil, Trash2, Filter } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { BreadcrumbItem, Transaction, Account, Category, PaginatedResponse } from '@/types';

interface Props {
    transactions: PaginatedResponse<Transaction>;
    accounts: Account[];
    categories: Category[];
    filters: {
        account_id?: number;
        category_id?: number;
        type?: string;
        date_from?: string;
        date_to?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Transacciones', href: '/transactions' },
];

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

function isDebitTransaction(type: string): boolean {
    return ['expense', 'transfer_out', 'settlement'].includes(type);
}

export default function TransactionsIndex({ transactions, accounts, categories, filters }: Props) {
    const handleDelete = (transaction: Transaction) => {
        if (confirm(`¿Estás seguro de eliminar esta transacción?`)) {
            router.delete(`/transactions/${transaction.uuid}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transacciones" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Transacciones</h1>
                    <div className="flex gap-2">
                        <Button variant="outline">
                            <Filter className="mr-2 h-4 w-4" />
                            Filtros
                        </Button>
                        <Button asChild>
                            <Link href="/transactions/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Nueva Transacción
                            </Link>
                        </Button>
                    </div>
                </div>

                {transactions.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="text-muted-foreground mb-4">
                                No tienes transacciones registradas
                            </p>
                            <Button asChild>
                                <Link href="/transactions/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Registrar tu primera transacción
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <div className="divide-y">
                                {transactions.data.map((transaction) => (
                                    <div
                                        key={transaction.uuid}
                                        className="flex items-center justify-between p-4"
                                    >
                                        <div className="flex items-center gap-4">
                                            {transaction.category && (
                                                <div
                                                    className="flex h-10 w-10 items-center justify-center rounded-full"
                                                    style={{ backgroundColor: transaction.category.color + '20' }}
                                                >
                                                    <span
                                                        className="h-5 w-5 rounded-full"
                                                        style={{ backgroundColor: transaction.category.color }}
                                                    />
                                                </div>
                                            )}
                                            <div>
                                                <p className="font-medium">{transaction.description}</p>
                                                <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                                    <span>{getTransactionTypeLabel(transaction.type)}</span>
                                                    <span>•</span>
                                                    <span>{formatDate(transaction.transaction_date)}</span>
                                                    {transaction.account && (
                                                        <>
                                                            <span>•</span>
                                                            <span>{transaction.account.name}</span>
                                                        </>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <p
                                                className={`text-lg font-medium ${
                                                    isDebitTransaction(transaction.type)
                                                        ? 'text-red-600'
                                                        : 'text-green-600'
                                                }`}
                                            >
                                                {isDebitTransaction(transaction.type) ? '-' : '+'}
                                                {formatCurrency(transaction.amount, transaction.currency)}
                                            </p>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon">
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/transactions/${transaction.uuid}/edit`}>
                                                            <Pencil className="mr-2 h-4 w-4" />
                                                            Editar
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => handleDelete(transaction)}
                                                        className="text-destructive"
                                                    >
                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                        Eliminar
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {transactions.meta.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {transactions.links.prev && (
                            <Button variant="outline" asChild>
                                <Link href={transactions.links.prev}>Anterior</Link>
                            </Button>
                        )}
                        <span className="text-muted-foreground flex items-center px-4 text-sm">
                            Página {transactions.meta.current_page} de {transactions.meta.last_page}
                        </span>
                        {transactions.links.next && (
                            <Button variant="outline" asChild>
                                <Link href={transactions.links.next}>Siguiente</Link>
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
