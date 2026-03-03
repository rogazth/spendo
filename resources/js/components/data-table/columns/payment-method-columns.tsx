import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
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
import { INSTRUMENT_TYPES } from '@/types';
import type { Instrument } from '@/types';

function getInstrumentTypeLabel(type: string): string {
    const match = INSTRUMENT_TYPES.find((t) => t.id === type);
    return match?.label ?? type;
}

interface PaymentMethodColumnsOptions {
    onEdit: (instrument: Instrument) => void;
    onDelete: (instrument: Instrument) => void;
    onToggleActive: (instrument: Instrument) => void;
    onMakeDefault: (instrument: Instrument) => void;
}

export function getPaymentMethodColumns({
    onEdit,
    onDelete,
    onToggleActive,
    onMakeDefault,
}: PaymentMethodColumnsOptions): ColumnDef<Instrument>[] {
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
                const instrument = row.original;
                return (
                    <div className="flex items-center gap-2">
                        <span
                            className="h-3 w-3 rounded-full"
                            style={{ backgroundColor: instrument.color }}
                        />
                        <Link
                            href={`/transactions?instrument_ids[]=${instrument.id}`}
                            className="font-medium hover:underline"
                        >
                            {row.getValue('name')}
                        </Link>
                        {instrument.is_default && (
                            <Badge variant="secondary">Por defecto</Badge>
                        )}
                        {instrument.last_four_digits && (
                            <span className="text-muted-foreground text-xs">
                                •••• {instrument.last_four_digits}
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
                    {getInstrumentTypeLabel(row.getValue('type'))}
                </span>
            ),
        },
        {
            id: 'credit_info',
            header: 'Crédito',
            cell: ({ row }) => {
                const instrument = row.original;
                if (instrument.type !== 'credit_card' || !instrument.credit_limit) {
                    return <span className="text-muted-foreground">-</span>;
                }
                return (
                    <div className="text-sm">
                        <p>
                            {formatCurrency(
                                instrument.current_debt || 0,
                                instrument.currency,
                                instrument.currency_locale,
                            )}{' '}
                            /{' '}
                            {formatCurrency(
                                instrument.credit_limit,
                                instrument.currency,
                                instrument.currency_locale,
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
                const instrument = row.original;
                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="h-8 w-8 p-0">
                                <span className="sr-only">Abrir menú</span>
                                <MoreHorizontalIcon className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {!instrument.is_default && (
                                <DropdownMenuItem onClick={() => onMakeDefault(instrument)}>
                                    <StarIcon className="h-4 w-4" />
                                    Marcar como por defecto
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem onClick={() => onEdit(instrument)}>
                                <PencilIcon className="h-4 w-4" />
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => onToggleActive(instrument)}>
                                {instrument.is_active ? (
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
                            <DropdownMenuItem onClick={() => onDelete(instrument)}>
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
