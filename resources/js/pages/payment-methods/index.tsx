import { Head, router } from '@inertiajs/react';
import { PlusIcon, CreditCardIcon } from 'lucide-react';
import { useState, useMemo } from 'react';
import { toast } from 'sonner';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { DataTable } from '@/components/data-table/data-table';
import { getPaymentMethodColumns } from '@/components/data-table/columns/payment-method-columns';
import { PaymentMethodFormDialog } from '@/components/forms/payment-method-form-dialog';
import type { BreadcrumbItem, PaginatedResponse, PaymentMethod, Account } from '@/types';

interface Props {
    paymentMethods: PaginatedResponse<PaymentMethod>;
    accounts?: Account[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Métodos de Pago', href: '/payment-methods' },
];

export default function PaymentMethodsIndex({ paymentMethods, accounts = [] }: Props) {
    const [formDialogOpen, setFormDialogOpen] = useState(false);
    const [editingPaymentMethod, setEditingPaymentMethod] = useState<PaymentMethod | undefined>();
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deletingPaymentMethod, setDeletingPaymentMethod] = useState<PaymentMethod | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleCreate = () => {
        setEditingPaymentMethod(undefined);
        setFormDialogOpen(true);
    };

    const handleEdit = (paymentMethod: PaymentMethod) => {
        setEditingPaymentMethod(paymentMethod);
        setFormDialogOpen(true);
    };

    const handleDeleteClick = (paymentMethod: PaymentMethod) => {
        setDeletingPaymentMethod(paymentMethod);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (!deletingPaymentMethod) return;

        setIsDeleting(true);
        router.delete(`/payment-methods/${deletingPaymentMethod.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteDialogOpen(false);
                toast.success('Método de pago eliminado');
            },
            onError: () => {
                toast.error('Error al eliminar el método de pago');
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    const handleDeleteDialogOpenChange = (open: boolean) => {
        setDeleteDialogOpen(open);
        if (!open) {
            setDeletingPaymentMethod(null);
        }
    };

    const handleToggleActive = (method: PaymentMethod) => {
        router.patch(`/payment-methods/${method.uuid}/toggle-active`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(method.is_active ? 'Método desactivado' : 'Método activado');
            },
            onError: () => {
                toast.error('Error al cambiar el estado');
            },
        });
    };

    const handleMakeDefault = (method: PaymentMethod) => {
        router.patch(`/payment-methods/${method.uuid}/make-default`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Método marcado como por defecto');
            },
            onError: () => {
                toast.error('Error al marcar el método');
            },
        });
    };

    const columns = useMemo(
        () => getPaymentMethodColumns({
            onEdit: handleEdit,
            onDelete: handleDeleteClick,
            onToggleActive: handleToggleActive,
            onMakeDefault: handleMakeDefault,
        }),
        []
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Métodos de Pago" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Métodos de Pago</h1>
                    <Button onClick={handleCreate}>
                        <PlusIcon className="h-4 w-4" />
                        Nuevo Método
                    </Button>
                </div>

                {paymentMethods.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <CreditCardIcon />
                            </EmptyMedia>
                            <EmptyTitle>No tienes métodos de pago registrados</EmptyTitle>
                            <EmptyDescription>
                                Agrega tu primer método de pago para registrar transacciones.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button onClick={handleCreate}>Agregar método</Button>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <DataTable
                        columns={columns}
                        data={paymentMethods.data}
                        searchKey="name"
                        searchPlaceholder="Buscar métodos de pago..."
                    />
                )}
            </div>

            <PaymentMethodFormDialog
                open={formDialogOpen}
                onOpenChange={setFormDialogOpen}
                paymentMethod={editingPaymentMethod}
                accounts={accounts}
            />

            <ConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={handleDeleteDialogOpenChange}
                title="Eliminar método de pago"
                description={
                    <>
                        ¿Estás seguro de eliminar <span className="font-semibold">{deletingPaymentMethod?.name}</span>? Esta acción no se puede deshacer.
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
