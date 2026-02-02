import type { ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpDownIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    MoreHorizontalIcon,
    PencilIcon,
    Trash2Icon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatCurrency } from '@/lib/currency';
import type { Transaction } from '@/types';

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
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

function isDebitTransaction(type: string): boolean {
    return ['expense', 'transfer_out', 'settlement'].includes(type);
}

interface TransactionColumnsOptions {
    onEdit: (transaction: Transaction) => void;
    onDelete: (transaction: Transaction) => void;
}

export function getTransactionColumns({
    onEdit,
    onDelete,
}: TransactionColumnsOptions): ColumnDef<Transaction>[] {
    return [
        {
            accessorKey: 'description',
            header: ({ column }) => {
                const sorted = column.getIsSorted();
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(sorted === 'asc')}
                    >
                        Descripción
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
                const transaction = row.original;
                return (
                    <div className="flex items-center gap-3">
                        {transaction.category && (
                            <span
                                className="h-3 w-3 rounded-full"
                                style={{
                                    backgroundColor: transaction.category.color,
                                }}
                            />
                        )}
                        <div>
                            <p className="font-medium">
                                {transaction.description}
                            </p>
                            {transaction.category && (
                                <p className="text-xs text-muted-foreground">
                                    {transaction.category.name}
                                </p>
                            )}
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'type',
            header: 'Tipo',
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {getTransactionTypeLabel(row.getValue('type'))}
                </span>
            ),
        },
        {
            id: 'account',
            header: 'Cuenta',
            cell: ({ row }) => {
                const transaction = row.original;
                if (transaction.account) {
                    return (
                        <span className="text-muted-foreground">
                            {transaction.account.name}
                        </span>
                    );
                }
                return <span className="text-muted-foreground">-</span>;
            },
        },
        {
            accessorKey: 'transaction_date',
            header: ({ column }) => {
                const sorted = column.getIsSorted();
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(sorted === 'asc')}
                    >
                        Fecha
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
                <span className="text-muted-foreground">
                    {formatDate(row.getValue('transaction_date'))}
                </span>
            ),
        },
        {
            accessorKey: 'amount',
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
                            Monto
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
            cell: ({ row }) => {
                const transaction = row.original;
                const isDebit = isDebitTransaction(transaction.type);
                return (
                    <div
                        className={`text-right font-medium ${
                            isDebit ? 'text-red-600' : 'text-green-600'
                        }`}
                    >
                        {isDebit ? '-' : '+'}
                        {formatCurrency(
                            row.getValue('amount'),
                            transaction.currency,
                            transaction.currency_locale,
                        )}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const transaction = row.original;
                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="h-8 w-8 p-0">
                                <span className="sr-only">Abrir menú</span>
                                <MoreHorizontalIcon className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onClick={() => onEdit(transaction)}
                            >
                                <PencilIcon className="h-4 w-4" />
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => onDelete(transaction)}
                            >
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
