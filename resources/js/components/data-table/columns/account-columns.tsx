import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpDownIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    MoreHorizontalIcon,
    PencilIcon,
    Trash2Icon,
    StarIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatCurrency } from '@/lib/currency';
import type { Account } from '@/types';

function getAccountTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        checking: 'Cuenta Corriente',
        savings: 'Cuenta de Ahorro',
        cash: 'Efectivo',
        investment: 'Inversión',
    };
    return labels[type] || type;
}

interface AccountColumnsOptions {
    onEdit: (account: Account) => void;
    onDelete: (account: Account) => void;
    onMakeDefault: (account: Account) => void;
}

export function getAccountColumns({
    onEdit,
    onDelete,
    onMakeDefault,
}: AccountColumnsOptions): ColumnDef<Account>[] {
    return [
        {
            accessorKey: 'name',
            header: ({ column }) => {
                const sorted = column.getIsSorted();
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(sorted === 'asc')}
                    >
                        Nombre
                        {sorted === 'asc' ? (
                            <ArrowUpIcon className="ml-2 h-4 w-4" />
                        ) : sorted === 'desc' ? (
                            <ArrowDownIcon className="ml-2 h-4 w-4" />
                        ) : (
                            <ArrowUpDownIcon className="ml-2 h-4 w-4 opacity-50" />
                        )}
                    </Button>
                );
            },
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span
                        className="h-3 w-3 rounded-full"
                        style={{ backgroundColor: row.original.color }}
                    />
                    <Link
                        href={`/accounts/${row.original.uuid}`}
                        className="font-medium hover:underline"
                    >
                        {row.getValue('name')}
                    </Link>
                    {row.original.is_default && (
                        <Badge variant="secondary">Por defecto</Badge>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'type',
            header: 'Tipo',
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {getAccountTypeLabel(row.getValue('type'))}
                </span>
            ),
        },
        {
            accessorKey: 'current_balance',
            header: ({ column }) => {
                const sorted = column.getIsSorted();
                return (
                    <div className="text-right">
                        <Button
                            variant="ghost"
                            onClick={() =>
                                column.toggleSorting(sorted === 'asc')
                            }
                        >
                            Balance
                            {sorted === 'asc' ? (
                                <ArrowUpIcon className="ml-2 h-4 w-4" />
                            ) : sorted === 'desc' ? (
                                <ArrowDownIcon className="ml-2 h-4 w-4" />
                            ) : (
                                <ArrowUpDownIcon className="ml-2 h-4 w-4 opacity-50" />
                            )}
                        </Button>
                    </div>
                );
            },
            cell: ({ row }) => (
                <div className="text-right font-medium">
                    {formatCurrency(
                        row.getValue('current_balance'),
                        row.original.currency,
                        row.original.currency_locale,
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'is_active',
            header: 'Estado',
            cell: ({ row }) => (
                <span
                    className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                        row.getValue('is_active')
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                            : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                    }`}
                >
                    {row.getValue('is_active') ? 'Activa' : 'Inactiva'}
                </span>
            ),
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const account = row.original;
                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="h-8 w-8 p-0">
                                <span className="sr-only">Abrir menú</span>
                                <MoreHorizontalIcon className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {!account.is_default && (
                                <DropdownMenuItem
                                    onClick={() => onMakeDefault(account)}
                                >
                                    <StarIcon className="h-4 w-4" />
                                    Marcar como por defecto
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem onClick={() => onEdit(account)}>
                                <PencilIcon className="h-4 w-4" />
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => onDelete(account)}>
                                <Trash2Icon className="h-4 w-4" />
                                Eliminar
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];
}
