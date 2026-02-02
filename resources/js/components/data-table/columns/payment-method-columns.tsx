import { ColumnDef } from '@tanstack/react-table';
import { Link } from '@inertiajs/react';
import {
    ArrowUpDownIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    MoreHorizontalIcon,
    PencilIcon,
    Trash2Icon,
    EyeIcon,
    EyeOffIcon,
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
import type { PaymentMethod } from '@/types';

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

interface PaymentMethodColumnsOptions {
    onEdit: (paymentMethod: PaymentMethod) => void;
    onDelete: (paymentMethod: PaymentMethod) => void;
    onToggleActive: (paymentMethod: PaymentMethod) => void;
    onMakeDefault: (paymentMethod: PaymentMethod) => void;
}

export function getPaymentMethodColumns({
    onEdit,
    onDelete,
    onToggleActive,
    onMakeDefault,
}: PaymentMethodColumnsOptions): ColumnDef<PaymentMethod>[] {
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
            cell: ({ row }) => {
                const method = row.original;
                return (
                    <div className="flex items-center gap-2">
                        <span
                            className="h-3 w-3 rounded-full"
                            style={{ backgroundColor: method.color }}
                        />
                        <Link
                            href={`/payment-methods/${method.uuid}`}
                            className="font-medium hover:underline"
                        >
                            {row.getValue('name')}
                        </Link>
                        {method.is_default && (
                            <Badge variant="secondary">Por defecto</Badge>
                        )}
                        {method.last_four_digits && (
                            <span className="text-muted-foreground text-xs">
                                •••• {method.last_four_digits}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'type',
            header: 'Tipo',
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {getPaymentMethodTypeLabel(row.getValue('type'))}
                </span>
            ),
        },
        {
            id: 'credit_info',
            header: 'Crédito',
            cell: ({ row }) => {
                const method = row.original;
                if (method.type !== 'credit_card' || !method.credit_limit) {
                    return <span className="text-muted-foreground">-</span>;
                }
                return (
                    <div className="text-sm">
                        <p>
                            {formatCurrency(
                                method.current_debt || 0,
                                method.currency,
                                method.currency_locale,
                            )}{' '}
                            /{' '}
                            {formatCurrency(
                                method.credit_limit,
                                method.currency,
                                method.currency_locale,
                            )}
                        </p>
                    </div>
                );
            },
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
                    {row.getValue('is_active') ? 'Activo' : 'Inactivo'}
                </span>
            ),
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const method = row.original;
                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="h-8 w-8 p-0">
                                <span className="sr-only">Abrir menú</span>
                                <MoreHorizontalIcon className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {!method.is_default && (
                                <DropdownMenuItem onClick={() => onMakeDefault(method)}>
                                    <StarIcon className="h-4 w-4" />
                                    Marcar como por defecto
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem onClick={() => onEdit(method)}>
                                <PencilIcon className="h-4 w-4" />
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => onToggleActive(method)}>
                                {method.is_active ? (
                                    <>
                                        <EyeOffIcon className="h-4 w-4" />
                                        Desactivar
                                    </>
                                ) : (
                                    <>
                                        <EyeIcon className="h-4 w-4" />
                                        Activar
                                    </>
                                )}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => onDelete(method)}>
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
