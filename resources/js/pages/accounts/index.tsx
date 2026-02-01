import { Head, Link, router } from '@inertiajs/react';
import { Plus, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { BreadcrumbItem, Account, PaginatedResponse } from '@/types';

interface Props {
    accounts: PaginatedResponse<Account>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cuentas', href: '/accounts' },
];

function formatCurrency(amount: number, currency: string = 'CLP'): string {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
    }).format(amount / 100);
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

export default function AccountsIndex({ accounts }: Props) {
    const handleDelete = (account: Account) => {
        if (confirm(`¿Estás seguro de eliminar la cuenta "${account.name}"?`)) {
            router.delete(`/accounts/${account.uuid}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cuentas" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Cuentas</h1>
                    <Button asChild>
                        <Link href="/accounts/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Nueva Cuenta
                        </Link>
                    </Button>
                </div>

                {accounts.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="text-muted-foreground mb-4">
                                No tienes cuentas registradas
                            </p>
                            <Button asChild>
                                <Link href="/accounts/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Crear tu primera cuenta
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {accounts.data.map((account) => (
                            <Card key={account.uuid}>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-lg font-medium">
                                        <Link
                                            href={`/accounts/${account.uuid}`}
                                            className="hover:underline"
                                        >
                                            {account.name}
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
                                                <Link href={`/accounts/${account.uuid}/edit`}>
                                                    <Pencil className="mr-2 h-4 w-4" />
                                                    Editar
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onClick={() => handleDelete(account)}
                                                className="text-destructive"
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                Eliminar
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center gap-2">
                                        <span
                                            className="inline-block h-3 w-3 rounded-full"
                                            style={{ backgroundColor: account.color }}
                                        />
                                        <span className="text-muted-foreground text-sm">
                                            {getAccountTypeLabel(account.type)}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-2xl font-bold">
                                        {formatCurrency(account.current_balance, account.currency)}
                                    </p>
                                    {!account.is_active && (
                                        <span className="mt-2 inline-block rounded bg-yellow-100 px-2 py-1 text-xs text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            Inactiva
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
