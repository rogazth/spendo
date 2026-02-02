import type { Currency } from '@/types';

const formatterCache = new Map<string, Intl.NumberFormat>();
const separatorCache = new Map<string, { group: string; decimal: string }>();

function getFormatter(locale: string, options: Intl.NumberFormatOptions): Intl.NumberFormat {
    const key = `${locale}:${JSON.stringify(options)}`;
    const existing = formatterCache.get(key);
    if (existing) return existing;

    const formatter = new Intl.NumberFormat(locale, options);
    formatterCache.set(key, formatter);
    return formatter;
}

export function getCurrencyLocale(
    currency: string,
    currencies?: Currency[],
    fallback: string = 'es-CL',
): string {
    if (!currencies?.length) return fallback;
    const match = currencies.find((item) => item.code === currency);
    return match?.locale ?? fallback;
}

export function getCurrencyFractionDigits(locale: string, currency: string): number {
    return getFormatter(locale, {
        style: 'currency',
        currency,
    }).resolvedOptions().maximumFractionDigits ?? 2;
}

export function getNumberSeparators(locale: string): { group: string; decimal: string } {
    const cached = separatorCache.get(locale);
    if (cached) return cached;

    const parts = new Intl.NumberFormat(locale).formatToParts(12345.6);
    const group = parts.find((part) => part.type === 'group')?.value ?? ',';
    const decimal = parts.find((part) => part.type === 'decimal')?.value ?? '.';
    const value = { group, decimal };
    separatorCache.set(locale, value);
    return value;
}

export function formatCurrency(
    amount: number,
    currency: string,
    locale: string = 'es-CL',
): string {
    const fractionDigits = getCurrencyFractionDigits(locale, currency);
    return getFormatter(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: fractionDigits,
    }).format(amount);
}

export function formatCurrencyValue(
    amount: number,
    currency: string,
    locale: string = 'es-CL',
): string {
    const fractionDigits = getCurrencyFractionDigits(locale, currency);
    return getFormatter(locale, {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: fractionDigits,
    }).format(amount);
}

export function parseCurrencyInput(
    rawValue: string,
    currency: string,
    locale: string = 'es-CL',
): number | null {
    const trimmed = rawValue.trim();
    if (!trimmed) return null;

    const fractionDigits = getCurrencyFractionDigits(locale, currency);
    const normalized = trimmed.replace(/[^\d.,\-\s]/g, '');
    if (!normalized) return null;

    const isNegative = normalized.includes('-');
    const { group, decimal } = getNumberSeparators(locale);
    let sanitized = normalized.replace(/\s/g, '').replace(/-/g, '');

    if (fractionDigits === 0) {
        const integerDigits = sanitized
            .split(group)
            .join('')
            .replace(/[^\d]/g, '');
        if (!integerDigits) return null;
        const integerValue = parseInt(integerDigits, 10);
        return isNegative ? -integerValue : integerValue;
    }

    sanitized = sanitized.split(group).join('');
    if (decimal !== '.') {
        sanitized = sanitized.replaceAll(decimal, '.');
    }

    const firstDecimalIndex = sanitized.indexOf('.');
    if (firstDecimalIndex !== -1) {
        const left = sanitized.slice(0, firstDecimalIndex + 1);
        const right = sanitized.slice(firstDecimalIndex + 1).replace(/\./g, '');
        sanitized = left + right;
    }

    let [integerPart, fractionPart = ''] = sanitized.split('.');
    integerPart = integerPart.replace(/[^\d]/g, '');
    fractionPart = fractionPart.replace(/[^\d]/g, '').slice(0, fractionDigits);

    if (!integerPart && !fractionPart) return null;

    const integerValue = integerPart ? parseInt(integerPart, 10) : 0;
    const fractionValue = fractionPart
        ? parseInt(fractionPart, 10) / Math.pow(10, fractionPart.length)
        : 0;

    const value = integerValue + fractionValue;
    return isNegative ? -value : value;
}
