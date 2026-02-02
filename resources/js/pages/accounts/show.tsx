import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { PencilIcon, Trash2Icon, ArrowLeftIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { AccountFormDialog } from '@/components/forms/account-form-dialog';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import type {
    BreadcrumbItem,
    Account,
    Transaction,
    PaginatedResponse,
} from '@/types';

interface Props {
    account: Account;
    transactions: PaginatedResponse<Transaction>;
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
    };
    return labels[type] || type;
}

export default function AccountShow({ account, transactions }: Props) {
    const [formDialogOpen, setFormDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cuentas', href: '/accounts' },
        { title: account.name, href: `/accounts/${account.uuid}` },
    ];

    const handleEdit = () => {
        setFormDialogOpen(true);
    };

    const handleDeleteClick = () => {
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        setIsDeleting(true);
        router.delete(`/accounts/${account.uuid}`, {
            onSuccess: () => {
                toast.success('Cuenta eliminada');
            },
            onError: () => {
                toast.error('Error al eliminar la cuenta');
                setIsDeleting(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={account.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/accounts">
                                <ArrowLeftIcon className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-balance text-2xl font-bold">
                                    {account.name}
                                </h1>
                                {account.is_default && (
                                    <Badge variant="secondary">Por defecto</Badge>
                                )}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {getAccountTypeLabel(account.type)}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={handleEdit}>
                            <PencilIcon className="h-4 w-4" />
                            Editar
                        </Button>
                        <Button variant="outline" onClick={handleDeleteClick}>
                            <Trash2Icon className="h-4 w-4" />
                            Eliminar
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Balance Actual
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {formatCurrency(
                                    account.current_balance,
                                    account.currency,
                                    account.currency_locale,
                                )}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
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
                            <p className="py-8 text-center text-muted-foreground">
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
                                            <p className="font-medium">
                                                {transaction.description}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {getTransactionTypeLabel(
                                                    transaction.type,
                                                )}{' '}
                                                •{' '}
                                                {formatDate(
                                                    transaction.transaction_date,
                                                )}
                                            </p>
                                        </div>
                                        <p
                                            className={`font-medium ${
                                                [
                                                    'expense',
                                                    'transfer_out',
                                                    'settlement',
                                                ].includes(transaction.type)
                                                    ? 'text-red-600'
                                                    : 'text-green-600'
                                            }`}
                                        >
                                            {[
                                                'expense',
                                                'transfer_out',
                                                'settlement',
                                            ].includes(transaction.type)
                                                ? '-'
                                                : '+'}
                                            {formatCurrency(
                                                transaction.amount,
                                                transaction.currency,
                                                transaction.currency_locale,
                                            )}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <AccountFormDialog
                open={formDialogOpen}
                onOpenChange={setFormDialogOpen}
                account={account}
            />

            <ConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
                title="Eliminar cuenta"
                description={
                    <>
                        ¿Estás seguro de eliminar la cuenta <span className="font-semibold">{account.name}</span>? Esta acción no se puede deshacer.
                    </>
                }
                confirmLabel="Eliminar"
                variant="destructive"
                onConfirm={handleDeleteConfirm}
                loading={isDeleting}
            />
        </AppLayout>
    );
}
