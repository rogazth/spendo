import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaginatedResponse, PaymentMethod } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';

interface Props {
    paymentMethods: PaginatedResponse<PaymentMethod>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Métodos de Pago', href: '/payment-methods' },
];

function formatCurrency(amount: number, currency: string = 'CLP'): string {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
    }).format(amount / 100);
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

export default function PaymentMethodsIndex({ paymentMethods }: Props) {
    const handleDelete = (method: PaymentMethod) => {
        if (confirm(`¿Estás seguro de eliminar "${method.name}"?`)) {
            router.delete(`/payment-methods/${method.uuid}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Métodos de Pago" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Métodos de Pago</h1>
                    <Button asChild>
                        <Link href="/payment-methods/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Nuevo Método
                        </Link>
                    </Button>
                </div>

                {paymentMethods.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <CreditCard className="mb-4 h-12 w-12 text-muted-foreground" />
                            <p className="mb-4 text-muted-foreground">
                                No tienes métodos de pago registrados
                            </p>
                            <Button asChild>
                                <Link href="/payment-methods/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Agregar tu primer método
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {paymentMethods.data.map((method) => (
                            <Card key={method.uuid}>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-lg font-medium">
                                        <Link
                                            href={`/payment-methods/${method.uuid}`}
                                            className="hover:underline"
                                        >
                                            {method.name}
                                        </Link>
                                    </CardTitle>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="icon">
                                                <MoreHorizontal className="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href={`/payment-methods/${method.uuid}/edit`}
                                                >
                                                    <Pencil className="mr-2 h-4 w-4" />
                                                    Editar
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onClick={() =>
                                                    handleDelete(method)
                                                }
                                                className="text-destructive"
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                Eliminar
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        {getPaymentMethodTypeLabel(method.type)}
                                    </p>
                                    {method.last_four_digits && (
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            •••• {method.last_four_digits}
                                        </p>
                                    )}
                                    {method.type === 'credit_card' &&
                                        method.credit_limit && (
                                            <div className="mt-3">
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-muted-foreground">
                                                        Usado
                                                    </span>
                                                    <span className="text-muted-foreground">
                                                        Límite
                                                    </span>
                                                </div>
                                                <div className="mt-1 flex justify-between text-sm font-medium">
                                                    <span>
                                                        {formatCurrency(
                                                            method.current_debt ||
                                                                0,
                                                        )}
                                                    </span>
                                                    <span>
                                                        {formatCurrency(
                                                            method.credit_limit,
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="mt-2 h-2 rounded-full bg-muted">
                                                    <div
                                                        className="h-2 rounded-full bg-blue-500"
                                                        style={{
                                                            width: `${Math.min(
                                                                ((method.current_debt ||
                                                                    0) /
                                                                    method.credit_limit) *
                                                                    100,
                                                                100,
                                                            )}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    {method.linkedAccount && (
                                        <p className="mt-3 text-xs text-muted-foreground">
                                            Vinculado a:{' '}
                                            {method.linkedAccount.name}
                                        </p>
                                    )}
                                    {!method.is_active && (
                                        <span className="mt-2 inline-block rounded bg-yellow-100 px-2 py-1 text-xs text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            Inactivo
                                        </span>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
