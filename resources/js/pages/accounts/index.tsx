import { Head, router } from '@inertiajs/react';
import { PlusIcon, WalletIcon } from 'lucide-react';
import { useState, useMemo } from 'react';
import { toast } from 'sonner';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { DataTable } from '@/components/data-table/data-table';
import { getAccountColumns } from '@/components/data-table/columns/account-columns';
import { AccountFormDialog } from '@/components/forms/account-form-dialog';
import type { BreadcrumbItem, Account, PaginatedResponse } from '@/types';

interface Props {
    accounts: PaginatedResponse<Account>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cuentas', href: '/accounts' },
];

export default function AccountsIndex({ accounts }: Props) {
    const [formDialogOpen, setFormDialogOpen] = useState(false);
    const [editingAccount, setEditingAccount] = useState<Account | undefined>();
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deletingAccount, setDeletingAccount] = useState<Account | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleCreate = () => {
        setEditingAccount(undefined);
        setFormDialogOpen(true);
    };

    const handleEdit = (account: Account) => {
        setEditingAccount(account);
        setFormDialogOpen(true);
    };

    const handleDeleteClick = (account: Account) => {
        setDeletingAccount(account);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (!deletingAccount) return;

        setIsDeleting(true);
        router.delete(`/accounts/${deletingAccount.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteDialogOpen(false);
                toast.success('Cuenta eliminada');
            },
            onError: () => {
                toast.error('Error al eliminar la cuenta');
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    const handleMakeDefault = (account: Account) => {
        router.patch(`/accounts/${account.uuid}/make-default`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Cuenta marcada como por defecto');
            },
            onError: () => {
                toast.error('Error al marcar la cuenta');
            },
        });
    };

    const handleDeleteDialogOpenChange = (open: boolean) => {
        setDeleteDialogOpen(open);
        if (!open) {
            setDeletingAccount(null);
        }
    };

    const columns = useMemo(
        () => getAccountColumns({
            onEdit: handleEdit,
            onDelete: handleDeleteClick,
            onMakeDefault: handleMakeDefault,
        }),
        []
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cuentas" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Cuentas</h1>
                    <Button onClick={handleCreate}>
                        <PlusIcon className="h-4 w-4" />
                        Nueva Cuenta
                    </Button>
                </div>

                {accounts.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <WalletIcon />
                            </EmptyMedia>
                            <EmptyTitle>No tienes cuentas registradas</EmptyTitle>
                            <EmptyDescription>
                                Crea tu primera cuenta para comenzar a administrar tus finanzas.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button onClick={handleCreate}>Crear cuenta</Button>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <DataTable
                        columns={columns}
                        data={accounts.data}
                        searchKey="name"
                        searchPlaceholder="Buscar cuentas..."
                    />
                )}
            </div>

            <AccountFormDialog
                open={formDialogOpen}
                onOpenChange={setFormDialogOpen}
                account={editingAccount}
            />

            <ConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={handleDeleteDialogOpenChange}
                title="Eliminar cuenta"
                description={
                    <>
                        ¿Estás seguro de eliminar la cuenta <span className="font-semibold">{deletingAccount?.name}</span>? Esta acción no se puede deshacer.
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
