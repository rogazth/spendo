import { cn } from '@/lib/utils';

export interface AmountDisplayProps {
    /** Magnitude in major units (always non-negative). */
    magnitude: number;
    currency: string;
    locale: string;
    /** Sign affects color and prefix. 'positive' | 'negative' | 'neutral'. */
    sign: 'positive' | 'negative' | 'neutral';
    fractionDigits: number;
    placeholder?: string;
}

function formatMagnitude(value: number, locale: string, fractionDigits: number): string {
    return new Intl.NumberFormat(locale, {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(value);
}

export function AmountDisplay({
    magnitude,
    currency,
    locale,
    sign,
    fractionDigits,
    placeholder = '0',
}: AmountDisplayProps) {
    const isEmpty = magnitude === 0;
    const formatted = isEmpty
        ? placeholder
        : formatMagnitude(magnitude, locale, fractionDigits);

    const effectiveSign: AmountDisplayProps['sign'] = isEmpty ? 'neutral' : sign;

    const colorClass =
        effectiveSign === 'negative'
            ? 'text-red-600 dark:text-red-400'
            : effectiveSign === 'positive'
              ? 'text-emerald-600 dark:text-emerald-400'
              : 'text-foreground';

    const prefix =
        effectiveSign === 'negative' ? '−' : effectiveSign === 'positive' ? '+' : '';

    return (
        <div className="flex items-baseline justify-center gap-2 transition-colors">
            <span
                className={cn(
                    'font-mono text-4xl font-bold tracking-tight tabular-nums transition-colors',
                    colorClass,
                    isEmpty && 'text-muted-foreground',
                )}
            >
                {prefix}${formatted}
            </span>
            <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                {currency}
            </span>
        </div>
    );
}
