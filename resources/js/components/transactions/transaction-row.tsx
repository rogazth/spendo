import {
    ArrowDownLeftIcon,
    ArrowLeftRightIcon,
    ArrowUpRightIcon,
    MoreHorizontalIcon,
    PencilIcon,
    Trash2Icon,
} from 'lucide-react';
import {
    ResponsiveActionMenu,
    isActionMenuBusy,
    type ResponsiveAction,
} from '@/components/responsive-action-menu';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { Transaction } from '@/types';
import { isTransfer as txIsTransfer } from '@/types/models';

interface TransactionRowProps {
    transaction: Transaction;
    onClick?: (transaction: Transaction) => void;
    onEdit?: (transaction: Transaction) => void;
    onDelete?: (transaction: Transaction) => void;
}

export function TransactionRow({
    transaction,
    onClick,
    onEdit,
    onDelete,
}: TransactionRowProps) {
    const isTransfer = txIsTransfer(transaction);
    const isDebit = transaction.amount < 0;
    const isIncome = !isTransfer && transaction.amount > 0;

    const category = transaction.category;
    const emoji = category?.emoji;
    const color = category?.color ?? '#94a3b8';

    const handleClick = () => {
        if (isActionMenuBusy()) return;
        onClick?.(transaction);
    };
    const handleKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            if (isActionMenuBusy()) return;
            onClick?.(transaction);
        }
    };

    const hasActions = !!onEdit || !!onDelete;
    const actions: ResponsiveAction[] = [];
    if (onEdit) {
        actions.push({
            label: 'Editar',
            icon: PencilIcon,
            onSelect: () => onEdit(transaction),
        });
    }
    if (onDelete) {
        actions.push({
            label: 'Eliminar',
            icon: Trash2Icon,
            variant: 'destructive',
            onSelect: () => onDelete(transaction),
        });
    }

    return (
        <div
            role={onClick ? 'button' : undefined}
            tabIndex={onClick ? 0 : undefined}
            onClick={onClick ? handleClick : undefined}
            onKeyDown={onClick ? handleKeyDown : undefined}
            className={cn(
                'group flex items-center gap-3 px-4 py-3 transition-colors',
                onClick &&
                    'cursor-pointer hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:outline-hidden',
            )}
        >
            <div
                className="flex size-10 flex-shrink-0 items-center justify-center rounded-full text-lg"
                style={
                    emoji
                        ? { backgroundColor: `${color}1A`, color }
                        : { backgroundColor: 'var(--muted)' }
                }
                aria-hidden
            >
                {emoji ? (
                    <span>{emoji}</span>
                ) : isTransfer ? (
                    <ArrowLeftRightIcon className="size-4 text-muted-foreground" />
                ) : isIncome ? (
                    <ArrowDownLeftIcon className="size-4 text-muted-foreground" />
                ) : (
                    <ArrowUpRightIcon className="size-4 text-muted-foreground" />
                )}
            </div>

            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <p className="truncate text-sm font-medium text-foreground">
                        {transaction.description ||
                            category?.name ||
                            'Sin descripción'}
                    </p>
                    {isTransfer && (
                        <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-medium tracking-wider text-muted-foreground uppercase">
                            Transferencia
                        </span>
                    )}
                </div>
                <p className="truncate text-xs text-muted-foreground">
                    {category?.name ? `${category.name}` : 'Sin categoría'}
                    {transaction.account?.name && (
                        <> · {transaction.account.name}</>
                    )}
                </p>
            </div>

            <div
                className={cn(
                    'font-mono text-base font-bold tracking-tight tabular-nums',
                    isDebit
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-emerald-600 dark:text-emerald-400',
                )}
            >
                {isDebit ? '-' : '+'}
                {formatCurrency(
                    Math.abs(transaction.amount),
                    transaction.currency,
                    transaction.currency_locale ?? 'es-CL',
                )}
            </div>

            {hasActions && (
                <ResponsiveActionMenu
                    title="Transacción"
                    actions={actions}
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            className="shrink-0 text-muted-foreground"
                            aria-label="Acciones de la transacción"
                            onClick={(event) => event.stopPropagation()}
                            onKeyDown={(event) => event.stopPropagation()}
                        >
                            <MoreHorizontalIcon className="size-4" />
                        </Button>
                    }
                />
            )}
        </div>
    );
}
