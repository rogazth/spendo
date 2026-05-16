import { AlertTriangleIcon } from 'lucide-react';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';

interface Tier {
    badge: string;
    bar: string;
    text: string;
}

const TIERS: { safe: Tier; warning: Tier; danger: Tier; over: Tier } = {
    safe: {
        badge: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400',
        bar: 'bg-emerald-500',
        text: 'text-muted-foreground',
    },
    warning: {
        badge: 'bg-yellow-50 text-yellow-600 dark:bg-yellow-950 dark:text-yellow-400',
        bar: 'bg-yellow-500',
        text: 'text-muted-foreground',
    },
    danger: {
        badge: 'bg-orange-50 text-orange-600 dark:bg-orange-950 dark:text-orange-400',
        bar: 'bg-orange-500',
        text: 'text-orange-600 dark:text-orange-400',
    },
    over: {
        badge: 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-400',
        bar: 'bg-red-500',
        text: 'text-red-600 dark:text-red-400',
    },
};

function resolveTier(percentage: number): Tier {
    if (percentage >= 100) return TIERS.over;
    if (percentage >= 80) return TIERS.danger;
    if (percentage >= 50) return TIERS.warning;
    return TIERS.safe;
}

export interface BudgetItemCardEntry {
    id: number;
    category_id: number;
    category_name: string;
    category_color: string;
    category_emoji: string;
    budgeted: number;
    spent: number;
    remaining: number;
    percentage: number;
}

export interface BudgetItemCardProps {
    item: BudgetItemCardEntry;
    currency: string;
    locale?: string;
}

export function BudgetItemCard({ item, currency, locale }: BudgetItemCardProps) {
    const tier = resolveTier(item.percentage);
    const overBudget = item.percentage >= 100;
    const nearLimit = item.percentage >= 80 && item.percentage < 100;

    return (
        <div className="bg-card border-border overflow-hidden rounded-xl border shadow-sm">
            <div className="p-5">
                <div className="mb-4 flex items-start gap-3">
                    <div
                        className="flex size-10 flex-shrink-0 items-center justify-center rounded-full text-lg"
                        style={{
                            backgroundColor: `${item.category_color}1A`,
                            color: item.category_color,
                        }}
                    >
                        <span aria-hidden>{item.category_emoji}</span>
                    </div>
                    <div className="min-w-0 flex-1">
                        <h3 className="text-foreground truncate font-semibold">
                            {item.category_name}
                        </h3>
                        <p className="text-muted-foreground text-xs">
                            Categoría presupuestada
                        </p>
                    </div>
                </div>

                <div className="mb-2 flex items-end justify-between gap-2">
                    <span className="text-foreground font-mono text-2xl font-bold tabular-nums">
                        {formatCurrency(item.spent, currency, locale)}
                        <span className="text-muted-foreground ml-1 font-sans text-sm font-normal">
                            / {formatCurrency(item.budgeted, currency, locale)}
                        </span>
                    </span>
                    <span
                        className={cn(
                            'rounded px-2 py-1 font-mono text-xs font-semibold tabular-nums',
                            tier.badge,
                        )}
                    >
                        {Math.round(item.percentage)}%
                    </span>
                </div>

                <div className="bg-muted mb-4 h-2 w-full overflow-hidden rounded-full">
                    <div
                        className={cn('h-full rounded-full transition-all', tier.bar)}
                        style={{ width: `${Math.min(100, item.percentage)}%` }}
                    />
                </div>

                <div className="border-border flex items-center justify-between border-t pt-3 text-xs">
                    <span className={cn('flex items-center gap-1 font-medium', tier.text)}>
                        {(nearLimit || overBudget) && (
                            <AlertTriangleIcon className="size-3.5" />
                        )}
                        {overBudget
                            ? 'Sobre el límite'
                            : nearLimit
                                ? 'Cerca del límite'
                                : 'Disponible'}
                    </span>
                    <span
                        className={cn(
                            'font-mono font-medium tabular-nums',
                            overBudget ? 'text-red-600 dark:text-red-400' : 'text-foreground',
                        )}
                    >
                        {formatCurrency(item.remaining, currency, locale)}
                    </span>
                </div>
            </div>
        </div>
    );
}
