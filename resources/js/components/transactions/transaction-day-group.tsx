import { format, isSameDay, isToday, isYesterday, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { Transaction } from '@/types';
import { isTransfer } from '@/types/models';
import { TransactionRow } from './transaction-row';

interface TransactionDayGroupProps {
    date: string;
    transactions: Transaction[];
    onSelect?: (transaction: Transaction) => void;
}

function formatDayHeading(date: Date): string {
    if (isToday(date)) return 'Hoy';
    if (isYesterday(date)) return 'Ayer';
    return format(date, "EEEE d 'de' MMMM", { locale: es });
}

interface CurrencyNet {
    currency: string;
    locale: string;
    net: number;
}

function computeDayNets(transactions: Transaction[]): CurrencyNet[] {
    const map = new Map<string, CurrencyNet>();

    for (const tx of transactions) {
        if (isTransfer(tx)) {
            continue;
        }
        const entry = map.get(tx.currency) ?? {
            currency: tx.currency,
            locale: tx.currency_locale ?? 'es-CL',
            net: 0,
        };
        entry.net += tx.amount;
        map.set(tx.currency, entry);
    }

    return Array.from(map.values()).sort((a, b) =>
        a.currency.localeCompare(b.currency),
    );
}

export function TransactionDayGroup({
    date,
    transactions,
    onSelect,
}: TransactionDayGroupProps) {
    const dayDate = parseISO(date);
    const heading = formatDayHeading(dayDate);
    const nets = computeDayNets(transactions);

    return (
        <div>
            <div className="bg-muted/40 border-border flex items-center justify-between rounded-t-lg border border-b-0 px-4 py-2">
                <span className="text-foreground text-sm font-semibold capitalize">
                    {heading}
                </span>
                <div className="flex items-center gap-3">
                    {nets.map((net) => (
                        <span
                            key={net.currency}
                            className={cn(
                                'font-mono text-xs font-semibold tabular-nums',
                                net.net < 0
                                    ? 'text-red-600 dark:text-red-400'
                                    : net.net > 0
                                      ? 'text-emerald-600 dark:text-emerald-400'
                                      : 'text-muted-foreground',
                            )}
                        >
                            {net.net > 0 ? '+' : ''}
                            {formatCurrency(net.net, net.currency, net.locale)}
                        </span>
                    ))}
                </div>
            </div>
            <div className="bg-card border-border divide-border divide-y rounded-b-lg border">
                {transactions.map((transaction) => (
                    <TransactionRow
                        key={transaction.uuid}
                        transaction={transaction}
                        onClick={onSelect}
                    />
                ))}
            </div>
        </div>
    );
}

export function groupTransactionsByDay(
    transactions: Transaction[],
): { date: string; transactions: Transaction[] }[] {
    const groups: { date: string; transactions: Transaction[] }[] = [];

    for (const tx of transactions) {
        const dayKey = tx.transaction_date.slice(0, 10);
        const last = groups[groups.length - 1];
        if (last && last.date === dayKey) {
            last.transactions.push(tx);
        } else if (last && isSameDay(parseISO(last.date), parseISO(dayKey))) {
            last.transactions.push(tx);
        } else {
            groups.push({ date: dayKey, transactions: [tx] });
        }
    }

    return groups;
}
