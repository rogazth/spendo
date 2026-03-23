import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ArrowDownIcon,
    ArrowUpDownIcon,
    ArrowUpIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { formatCurrency } from '@/lib/currency';
import type { Budget } from '@/types';

const FREQUENCY_LABELS: Record<string, string> = {
    weekly: 'Semanal',
    biweekly: 'Quincenal',
    monthly: 'Mensual',
    bimonthly: 'Bimensual',
};

function formatDate(date?: string | null): string {
    if (!date) {
        return 'Sin fin';
    }

    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export function getBudgetColumns(): ColumnDef<Budget>[] {
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
                        Budget
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
                const budget = row.original;
                return (
                    <div className="space-y-0.5">
                        <Link
                            href={`/budgets/${budget.uuid}`}
                            className="font-medium hover:underline"
                        >
                            {budget.name}
                        </Link>
                        {budget.description && (
                            <p className="line-clamp-1 text-xs text-muted-foreground">
                                {budget.description}
                            </p>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'frequency',
            header: 'Frecuencia',
            cell: ({ row }) => {
                const frequency = row.getValue('frequency') as string;
                return (
                    <span className="text-sm text-muted-foreground">
                        {FREQUENCY_LABELS[frequency] ?? frequency}
                    </span>
                );
            },
        },
        {
            accessorKey: 'ends_at',
            header: 'Finaliza',
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {formatDate(row.getValue('ends_at') as string | null)}
                </span>
            ),
        },
        {
            id: 'progress',
            header: 'Gasto actual',
            cell: ({ row }) => {
                const budget = row.original;
                const spent = budget.current_cycle_spent ?? 0;
                const total = budget.total_budgeted ?? 0;
                const percentage = budget.current_cycle_percentage ?? 0;

                return (
                    <div className="w-[220px] space-y-1">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span>
                                {formatCurrency(
                                    spent,
                                    budget.currency,
                                    'es-CL',
                                )}
                            </span>
                            <span>
                                {formatCurrency(
                                    total,
                                    budget.currency,
                                    'es-CL',
                                )}
                            </span>
                        </div>
                        <Progress value={Math.min(100, percentage)} />
                    </div>
                );
            },
        },
    ];
}
