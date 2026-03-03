import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowDownIcon, ArrowUpDownIcon, ArrowUpIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/currency';
import { INSTRUMENT_TYPES } from '@/types';
import type { Instrument } from '@/types';

function getTypeLabel(type: string): string {
    return INSTRUMENT_TYPES.find((t) => t.id === type)?.label ?? type;
}

export function getInstrumentColumns(): ColumnDef<Instrument>[] {
    return [
        {
            accessorKey: 'name',
            header: ({ column }) => {
                const sorted = column.getIsSorted();
                return (
                    <Button variant="ghost" onClick={() => column.toggleSorting(sorted === 'asc')}>
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
                        href={`/transactions?instrument_ids[]=${row.original.id}`}
                        className="font-medium hover:underline"
                    >
                        {row.getValue('name')}
                    </Link>
                    {row.original.last_four_digits && (
                        <span className="text-xs text-muted-foreground">
                            •••• {row.original.last_four_digits}
                        </span>
                    )}
                    {row.original.is_default && (
                        <Badge variant="secondary">Por defecto</Badge>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'type',
            header: 'Tipo',
            cell: ({ row }) => getTypeLabel(row.getValue('type')),
        },
        {
            accessorKey: 'currency',
            header: 'Moneda',
        },
        {
            id: 'balance',
            header: ({ column }) => {
                const sorted = column.getIsSorted();
                return (
                    <div className="text-right">
                        <Button
                            variant="ghost"
                            onClick={() => column.toggleSorting(sorted === 'asc')}
                        >
                            Saldo / Deuda
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
            accessorFn: (row) =>
                row.type === 'credit_card' ? (row.current_debt ?? 0) : (row.current_balance ?? 0),
            cell: ({ row }) => {
                const isCredit = row.original.type === 'credit_card';
                const locale = row.original.currency_locale ?? 'es-CL';
                const value = isCredit
                    ? (row.original.current_debt ?? 0)
                    : (row.original.current_balance ?? 0);
                return (
                    <div className={`text-right tabular-nums font-medium ${isCredit && value > 0 ? 'text-red-600' : ''}`}>
                        {formatCurrency(value, row.original.currency, locale)}
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
    ];
}
