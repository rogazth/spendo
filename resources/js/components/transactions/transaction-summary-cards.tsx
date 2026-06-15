import { ArrowDownLeftIcon, ArrowUpRightIcon, WalletIcon } from 'lucide-react';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { Account } from '@/types';

export interface TransactionSummaryEntry {
    income: number;
    expenses: number;
    net: number;
    currency_locale?: string;
}

export interface TransactionSummaryCardsProps {
    account: Account;
    entry?: TransactionSummaryEntry;
}

export function TransactionSummaryCards({
    account,
    entry,
}: TransactionSummaryCardsProps) {
    const currency = account.currency;
    const locale = account.currency_locale ?? entry?.currency_locale ?? 'es-CL';
    const balance = account.current_balance ?? 0;
    const income = entry?.income ?? 0;
    const expenses = entry?.expenses ?? 0;
    const negativeBalance = balance < 0;

    return (
        <div className="no-scrollbar -mx-4 flex snap-x snap-mandatory scroll-pl-4 gap-3 overflow-x-auto px-4 md:mx-0 md:grid md:scroll-pl-0 md:grid-cols-3 md:gap-4 md:overflow-visible md:px-0">
            <StatCard
                icon={<WalletIcon className="size-4" />}
                iconClass={
                    negativeBalance
                        ? 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-400'
                        : 'bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400'
                }
                label="Saldo"
                value={formatCurrency(balance, currency, locale)}
                valueClass={
                    negativeBalance
                        ? 'text-red-600 dark:text-red-400'
                        : undefined
                }
                sub={currency}
            />
            <StatCard
                icon={<ArrowUpRightIcon className="size-4" />}
                iconClass="bg-orange-50 text-orange-600 dark:bg-orange-950 dark:text-orange-400"
                label="Gastos"
                value={formatCurrency(expenses, currency, locale)}
            />
            <StatCard
                icon={<ArrowDownLeftIcon className="size-4" />}
                iconClass="bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400"
                label="Ingresos"
                value={formatCurrency(income, currency, locale)}
            />
        </div>
    );
}

interface StatCardProps {
    icon: React.ReactNode;
    iconClass: string;
    label: string;
    value: string;
    valueClass?: string;
    sub?: string;
}

function StatCard({
    icon,
    iconClass,
    label,
    value,
    valueClass,
    sub,
}: StatCardProps) {
    return (
        <div className="w-[72vw] shrink-0 snap-start rounded-xl border border-border bg-card p-3 shadow-sm sm:w-[56vw] md:w-auto md:shrink md:p-3">
            <div className="flex items-center gap-3">
                <div
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                        iconClass,
                    )}
                >
                    {icon}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {label}
                    </p>
                    <div className="flex items-baseline gap-1.5">
                        <span
                            className={cn(
                                'font-mono text-lg leading-tight font-bold tabular-nums',
                                valueClass,
                            )}
                        >
                            {value}
                        </span>
                        {sub && (
                            <span className="text-[10px] font-medium tracking-wider text-muted-foreground uppercase">
                                {sub}
                            </span>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
