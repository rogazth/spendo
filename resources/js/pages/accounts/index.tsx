import { Head, router, useForm } from '@inertiajs/react';
import { WalletIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { getAccountColumns } from '@/components/data-table/columns/account-columns';
import { DataTable } from '@/components/data-table/data-table';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { Account, BreadcrumbItem, PaginatedResponse } from '@/types';

interface Props {
    accounts: PaginatedResponse<Account>;
}

interface EditFormData {
    name: string;
    currency: string;
    is_active: boolean;
    is_default: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cuentas', href: '/accounts' },
];

export default function AccountsIndex({ accounts }: Props) {
    const [editingAccount, setEditingAccount] = useState<Account | null>(null);
    const [deletingAccount, setDeletingAccount] = useState<Account | null>(null);

    const { data, setData, put, processing, errors, reset } = useForm<EditFormData>({
        name: '',
        currency: '',
        is_active: true,
        is_default: false,
    });

    const handleEdit = (account: Account) => {
        setEditingAccount(account);
        setData({
            name: account.name,
            currency: account.currency,
            is_active: account.is_active,
            is_default: account.is_default,
        });
    };

    const handleEditClose = () => {
        setEditingAccount(null);
        reset();
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingAccount) {
            return;
        }
        put(`/accounts/${editingAccount.uuid}`, {
            onSuccess: handleEditClose,
        });
    };

    const handleDelete = (account: Account) => {
        setDeletingAccount(account);
    };

    const handleDeleteConfirm = () => {
        if (!deletingAccount) {
            return;
        }
        router.delete(`/accounts/${deletingAccount.uuid}`, {
            onSuccess: () => setDeletingAccount(null),
        });
    };

    const handleMakeDefault = (account: Account) => {
        router.post(`/accounts/${account.uuid}/default`);
    };

    const columns = useMemo(
        () =>
            getAccountColumns({
                onEdit: handleEdit,
                onDelete: handleDelete,
                onMakeDefault: handleMakeDefault,
            }),
        [],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cuentas" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-bold text-balance">Cuentas</h1>

                {accounts.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <WalletIcon />
                            </EmptyMedia>
                            <EmptyTitle>No tienes cuentas registradas</EmptyTitle>
                            <EmptyDescription>
                                Las cuentas se crean mediante el asistente de IA.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <DataTable
                        columns={columns}
                        data={accounts.data}
                        searchKey="name"
                        searchPlaceholder="Buscar cuenta..."
                    />
                )}

                {accounts.meta.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {accounts.links.prev && (
                            <Button variant="outline" asChild>
                                <a href={accounts.links.prev}>Anterior</a>
                            </Button>
                        )}
                        <span className="flex items-center px-4 text-sm text-muted-foreground tabular-nums">
                            Página {accounts.meta.current_page} de {accounts.meta.last_page}
                        </span>
                        {accounts.links.next && (
                            <Button variant="outline" asChild>
                                <a href={accounts.links.next}>Siguiente</a>
                            </Button>
                        )}
                    </div>
                )}
            </div>

            {/* Edit dialog */}
            <Dialog open={editingAccount !== null} onOpenChange={(open) => !open && handleEditClose()}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Editar cuenta</DialogTitle>
                    </DialogHeader>
                    <form id="edit-account-form" onSubmit={handleEditSubmit}>
                        <div className="space-y-4 py-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="account-name">Nombre</Label>
                                <Input
                                    id="account-name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    autoComplete="off"
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">{errors.name}</p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="account-currency">Moneda</Label>
                                <Input
                                    id="account-currency"
                                    value={data.currency}
                                    onChange={(e) => setData('currency', e.target.value)}
                                    maxLength={3}
                                    className="uppercase"
                                />
                                {errors.currency && (
                                    <p className="text-sm text-destructive">{errors.currency}</p>
                                )}
                            </div>
                        </div>
                    </form>
                    <DialogFooter>
                        <Button variant="outline" onClick={handleEditClose} disabled={processing}>
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            form="edit-account-form"
                            disabled={processing}
                        >
                            Guardar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete confirmation */}
            <AlertDialog
                open={deletingAccount !== null}
                onOpenChange={(open) => !open && setDeletingAccount(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>¿Eliminar cuenta?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Se eliminará{' '}
                            <span className="font-semibold">{deletingAccount?.name}</span>.
                            Esta acción no se puede deshacer.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDeleteConfirm}>
                            Eliminar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
