import {
    ArrowDownLeftIcon,
    ArrowLeftRightIcon,
    ArrowUpRightIcon,
} from 'lucide-react';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { Transaction } from '@/types';
import { isTransfer as txIsTransfer } from '@/types/models';

interface TransactionRowProps {
    transaction: Transaction;
    onClick?: (transaction: Transaction) => void;
}

export function TransactionRow({ transaction, onClick }: TransactionRowProps) {
    const isTransfer = txIsTransfer(transaction);
    const isDebit = transaction.amount < 0;
    const isIncome = !isTransfer && transaction.amount > 0;

    const category = transaction.category;
    const emoji = category?.emoji;
    const color = category?.color ?? '#94a3b8';

    const handleClick = () => onClick?.(transaction);
    const handleKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            onClick?.(transaction);
        }
    };

    return (
        <div
            role={onClick ? 'button' : undefined}
            tabIndex={onClick ? 0 : undefined}
            onClick={onClick ? handleClick : undefined}
            onKeyDown={onClick ? handleKeyDown : undefined}
            className={cn(
                'group flex items-center gap-3 px-4 py-3 transition-colors',
                onClick &&
                    'hover:bg-muted/40 focus-visible:bg-muted/40 cursor-pointer focus-visible:outline-hidden',
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
                    <ArrowLeftRightIcon className="text-muted-foreground size-4" />
                ) : isIncome ? (
                    <ArrowDownLeftIcon className="text-muted-foreground size-4" />
                ) : (
                    <ArrowUpRightIcon className="text-muted-foreground size-4" />
                )}
            </div>

            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <p className="text-foreground truncate text-sm font-medium">
                        {transaction.description ||
                            category?.name ||
                            'Sin descripción'}
                    </p>
                    {isTransfer && (
                        <span className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 font-mono text-[10px] font-medium tracking-wider uppercase">
                            Transferencia
                        </span>
                    )}
                </div>
                <p className="text-muted-foreground truncate text-xs">
                    {category?.name ? `${category.name}` : 'Sin categoría'}
                    {transaction.account?.name && (
                        <> · {transaction.account.name}</>
                    )}
                </p>
            </div>

            <div
                className={cn(
                    'font-mono text-base font-bold tabular-nums tracking-tight',
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
        </div>
    );
}
