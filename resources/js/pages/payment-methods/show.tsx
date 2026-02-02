import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { PencilIcon, Trash2Icon, ArrowLeftIcon } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PaymentMethodFormDialog } from '@/components/forms/payment-method-form-dialog';
import { formatCurrency } from '@/lib/currency';
import type { BreadcrumbItem, PaymentMethod, Transaction, PaginatedResponse, Account } from '@/types';

interface Props {
    paymentMethod: PaymentMethod;
    transactions: PaginatedResponse<Transaction>;
    accounts?: Account[];
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getPaymentMethodTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        credit_card: 'Tarjeta de Crédito',
        debit_card: 'Tarjeta de Débito',
        prepaid_card: 'Tarjeta Prepago',
        cash: 'Efectivo',
        transfer: 'Transferencia',
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

export default function PaymentMethodShow({ paymentMethod, transactions, accounts = [] }: Props) {
    const [formDialogOpen, setFormDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Métodos de Pago', href: '/payment-methods' },
        { title: paymentMethod.name, href: `/payment-methods/${paymentMethod.uuid}` },
    ];

    const handleEdit = () => {
        setFormDialogOpen(true);
    };

    const handleDeleteClick = () => {
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        setIsDeleting(true);
        router.delete(`/payment-methods/${paymentMethod.uuid}`, {
            onSuccess: () => {
                toast.success('Método de pago eliminado');
            },
            onError: () => {
                toast.error('Error al eliminar el método de pago');
                setIsDeleting(false);
            },
        });
    };

    const isCreditCard = paymentMethod.type === 'credit_card';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={paymentMethod.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/payment-methods">
                                <ArrowLeftIcon className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-balance text-2xl font-bold">{paymentMethod.name}</h1>
                                {paymentMethod.is_default && (
                                    <Badge variant="secondary">Por defecto</Badge>
                                )}
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {getPaymentMethodTypeLabel(paymentMethod.type)}
                                {paymentMethod.last_four_digits && ` • •••• ${paymentMethod.last_four_digits}`}
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

                <div className="grid gap-4 md:grid-cols-3">
                    {isCreditCard && paymentMethod.credit_limit && (
                        <>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-muted-foreground text-sm font-medium">
                                        Usado
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-2xl font-bold">
                                        {formatCurrency(
                                            paymentMethod.current_debt || 0,
                                            paymentMethod.currency,
                                            paymentMethod.currency_locale,
                                        )}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-muted-foreground text-sm font-medium">
                                        Disponible
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-2xl font-bold text-green-600">
                                        {formatCurrency(
                                            paymentMethod.available_credit ||
                                                (paymentMethod.credit_limit -
                                                    (paymentMethod.current_debt ||
                                                        0)),
                                            paymentMethod.currency,
                                            paymentMethod.currency_locale,
                                        )}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-muted-foreground text-sm font-medium">
                                        Límite
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-2xl font-bold">
                                        {formatCurrency(
                                            paymentMethod.credit_limit,
                                            paymentMethod.currency,
                                            paymentMethod.currency_locale,
                                        )}
                                    </p>
                                </CardContent>
                            </Card>
                        </>
                    )}

                    {paymentMethod.linkedAccount && (
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    Cuenta Vinculada
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Link
                                    href={`/accounts/${paymentMethod.linkedAccount.uuid}`}
                                    className="text-lg font-medium hover:underline"
                                >
                                    {paymentMethod.linkedAccount.name}
                                </Link>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">
                                Estado
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <span
                                className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                    paymentMethod.is_active
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                }`}
                            >
                                {paymentMethod.is_active ? 'Activo' : 'Inactivo'}
                            </span>
                        </CardContent>
                    </Card>
                </div>

                {isCreditCard && paymentMethod.billing_cycle_day && paymentMethod.payment_due_day && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Fechas de Facturación</CardTitle>
                        </CardHeader>
                        <CardContent className="flex gap-8">
                            <div>
                                <p className="text-muted-foreground text-sm">Día de Facturación</p>
                                <p className="text-lg font-medium">Día {paymentMethod.billing_cycle_day}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-sm">Día de Pago</p>
                                <p className="text-lg font-medium">Día {paymentMethod.payment_due_day}</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Transacciones Recientes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {transactions.data.length === 0 ? (
                            <p className="text-muted-foreground py-8 text-center">
                                No hay transacciones con este método de pago
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

            <PaymentMethodFormDialog
                open={formDialogOpen}
                onOpenChange={setFormDialogOpen}
                paymentMethod={paymentMethod}
                accounts={accounts}
            />

            <ConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
                title="Eliminar método de pago"
                description={
                    <>
                        ¿Estás seguro de eliminar <span className="font-semibold">{paymentMethod.name}</span>? Esta acción no se puede deshacer.
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
