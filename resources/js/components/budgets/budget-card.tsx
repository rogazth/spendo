import { router } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    MoreHorizontalIcon,
    PencilIcon,
    PowerIcon,
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
import type { Budget, BudgetFrequency } from '@/types';

const FREQUENCY_RESETS: Record<BudgetFrequency, string> = {
    weekly: 'Resets weekly',
    biweekly: 'Resets biweekly',
    monthly: 'Resets monthly',
    bimonthly: 'Resets bimonthly',
};

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

export interface BudgetCardProps {
    budget: Budget;
    onEdit?: (budget: Budget) => void;
    onToggleActive?: (budget: Budget) => void;
    onDelete?: (budget: Budget) => void;
}

export function BudgetCard({
    budget,
    onEdit,
    onToggleActive,
    onDelete,
}: BudgetCardProps) {
    const spent = budget.current_cycle_spent ?? 0;
    const total = budget.total_budgeted ?? 0;
    const remaining = total - spent;
    const percentage = budget.current_cycle_percentage ?? 0;
    const tier = resolveTier(percentage);

    const emoji = budget.emoji ?? budget.items?.[0]?.category?.emoji ?? '💰';
    const color = budget.color ?? budget.items?.[0]?.category?.color ?? '#94a3b8';

    const overBudget = percentage >= 100;
    const nearLimit = percentage >= 80 && percentage < 100;
    const isActive = budget.is_active;

    const actions: ResponsiveAction[] = [];
    if (onEdit) {
        actions.push({
            label: 'Editar',
            icon: PencilIcon,
            onSelect: () => onEdit(budget),
        });
    }
    if (onToggleActive) {
        actions.push({
            label: isActive ? 'Desactivar' : 'Activar',
            icon: PowerIcon,
            onSelect: () => onToggleActive(budget),
        });
    }
    if (onDelete) {
        actions.push({
            label: 'Eliminar',
            icon: Trash2Icon,
            variant: 'destructive',
            onSelect: () => onDelete(budget),
        });
    }

    const navigate = () => {
        if (isActionMenuBusy()) return;
        router.visit(`/budgets/${budget.uuid}`);
    };
    const handleKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            if (isActionMenuBusy()) return;
            router.visit(`/budgets/${budget.uuid}`);
        }
    };

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={navigate}
            onKeyDown={handleKeyDown}
            className={cn(
                'group bg-card border-border hover:border-foreground/20 hover:shadow-md focus-visible:border-foreground/20 focus-visible:outline-hidden relative block cursor-pointer overflow-hidden rounded-xl border shadow-sm transition-all',
                !isActive && 'opacity-65',
            )}
        >
            {actions.length > 0 && (
                <div className="absolute top-3 right-3 z-10">
                    <ResponsiveActionMenu
                        title="Budget"
                        actions={actions}
                        trigger={
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                className="text-muted-foreground shrink-0"
                                aria-label="Acciones del budget"
                                onClick={(event) => event.stopPropagation()}
                                onKeyDown={(event) => event.stopPropagation()}
                            >
                                <MoreHorizontalIcon className="size-4" />
                            </Button>
                        }
                    />
                </div>
            )}

            <div className="p-5">
                <div className="mb-4 flex items-start gap-3 pr-9">
                    <div
                        className="flex size-10 flex-shrink-0 items-center justify-center rounded-full text-lg"
                        style={{ backgroundColor: `${color}1A`, color }}
                    >
                        <span aria-hidden>{emoji}</span>
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <h3 className="text-foreground truncate font-semibold">
                                {budget.name}
                            </h3>
                            {!isActive && <InactiveBadge />}
                        </div>
                        <p className="text-muted-foreground text-xs">
                            {FREQUENCY_RESETS[budget.frequency] ?? 'Custom cycle'}
                        </p>
                    </div>
                </div>

                <div className="mb-2 flex items-end justify-between gap-2">
                    <span className="text-foreground font-mono text-2xl font-bold tabular-nums">
                        {formatCurrency(spent, budget.currency)}
                        <span className="text-muted-foreground ml-1 font-sans text-sm font-normal">
                            / {formatCurrency(total, budget.currency)}
                        </span>
                    </span>
                    <span
                        className={cn(
                            'rounded px-2 py-1 font-mono text-xs font-semibold tabular-nums',
                            tier.badge,
                        )}
                    >
                        {Math.round(percentage)}%
                    </span>
                </div>

                <div className="bg-muted mb-4 h-2 w-full overflow-hidden rounded-full">
                    <div
                        className={cn('h-full rounded-full transition-all', tier.bar)}
                        style={{ width: `${Math.min(100, percentage)}%` }}
                    />
                </div>

                <div className="border-border flex items-center justify-between border-t pt-3 text-xs">
                    <span className={cn('flex items-center gap-1 font-medium', tier.text)}>
                        {(nearLimit || overBudget) && (
                            <AlertTriangleIcon className="size-3.5" />
                        )}
                        {overBudget
                            ? 'Over budget'
                            : nearLimit
                                ? 'Near limit'
                                : 'Left to spend'}
                    </span>
                    <span
                        className={cn(
                            'font-mono font-medium tabular-nums',
                            overBudget ? 'text-red-600 dark:text-red-400' : 'text-foreground',
                        )}
                    >
                        {formatCurrency(remaining, budget.currency)}
                    </span>
                </div>
            </div>
        </div>
    );
}

function InactiveBadge() {
    return (
        <span className="text-muted-foreground bg-muted shrink-0 rounded px-1.5 py-0.5 font-mono text-[10px] font-bold tracking-wider uppercase">
            inactivo
        </span>
    );
}
