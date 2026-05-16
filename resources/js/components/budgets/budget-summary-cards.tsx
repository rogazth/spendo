import { ShoppingBagIcon, PiggyBankIcon, WalletIcon } from 'lucide-react';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';

export interface BudgetSummaryEntry {
    budgeted: number;
    spent: number;
    remaining: number;
    currency_locale?: string;
}

export interface BudgetSummaryCardsProps {
    summary: Record<string, BudgetSummaryEntry>;
}

export function BudgetSummaryCards({ summary }: BudgetSummaryCardsProps) {
    const currencies = Object.keys(summary).sort();

    if (currencies.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3">
            {currencies.map((currency) => (
                <CurrencyRow
                    key={currency}
                    currency={currency}
                    entry={summary[currency]}
                    showLabel={currencies.length > 1}
                />
            ))}
        </div>
    );
}

interface CurrencyRowProps {
    currency: string;
    entry: BudgetSummaryEntry;
    showLabel: boolean;
}

function CurrencyRow({ currency, entry, showLabel }: CurrencyRowProps) {
    const locale = entry.currency_locale ?? 'es-CL';
    const percent = entry.budgeted > 0
        ? Math.round((entry.spent / entry.budgeted) * 100)
        : 0;
    const overBudget = entry.remaining < 0;

    return (
        <div className="space-y-2">
            {showLabel && (
                <p className="font-mono text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                    {currency}
                </p>
            )}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <StatCard
                    icon={<WalletIcon className="size-5" />}
                    iconClass="bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400"
                    tag={currency}
                    label="Total budgeted"
                    value={formatCurrency(entry.budgeted, currency, locale)}
                />
                <StatCard
                    icon={<ShoppingBagIcon className="size-5" />}
                    iconClass="bg-orange-50 text-orange-600 dark:bg-orange-950 dark:text-orange-400"
                    tag="CURRENT"
                    label="Total spent"
                    value={formatCurrency(entry.spent, currency, locale)}
                    sub={entry.budgeted > 0 ? `${percent}% of total` : undefined}
                />
                <StatCard
                    icon={<PiggyBankIcon className="size-5" />}
                    iconClass={
                        overBudget
                            ? 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-400'
                            : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400'
                    }
                    tag={overBudget ? 'OVER' : 'SAFE'}
                    tagClass={
                        overBudget
                            ? 'text-red-600 dark:text-red-400'
                            : undefined
                    }
                    label="Remaining"
                    value={formatCurrency(entry.remaining, currency, locale)}
                    valueClass={overBudget ? 'text-red-600 dark:text-red-400' : undefined}
                    sub={overBudget ? 'Over budget' : 'Available'}
                />
            </div>
        </div>
    );
}

interface StatCardProps {
    icon: React.ReactNode;
    iconClass: string;
    tag: string;
    tagClass?: string;
    label: string;
    value: string;
    valueClass?: string;
    sub?: string;
}

function StatCard({ icon, iconClass, tag, tagClass, label, value, valueClass, sub }: StatCardProps) {
    return (
        <div className="bg-card border-border rounded-xl border p-5 shadow-sm">
            <div className="mb-4 flex items-start justify-between">
                <div
                    className={cn(
                        'flex size-9 items-center justify-center rounded-lg',
                        iconClass,
                    )}
                >
                    {icon}
                </div>
                <span
                    className={cn(
                        'bg-muted text-muted-foreground rounded px-2 py-0.5 font-mono text-[10px] font-medium tracking-wider',
                        tagClass,
                    )}
                >
                    {tag}
                </span>
            </div>
            <p className="text-muted-foreground text-sm font-medium">{label}</p>
            <div className="mt-1.5 flex items-baseline gap-2">
                <span
                    className={cn(
                        'font-mono text-2xl font-bold tabular-nums',
                        valueClass,
                    )}
                >
                    {value}
                </span>
                {sub && (
                    <span className="text-muted-foreground text-xs">{sub}</span>
                )}
            </div>
        </div>
    );
}
