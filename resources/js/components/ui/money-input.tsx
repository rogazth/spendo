import * as React from 'react';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import {
    formatCurrencyValue,
    getCurrencyFractionDigits,
    getNumberSeparators,
    parseCurrencyInput,
} from '@/lib/currency';
import { cn } from '@/lib/utils';

interface MoneyInputProps
    extends Omit<React.ComponentProps<'input'>, 'type' | 'value' | 'onChange'> {
    currency: string;
    locale?: string;
    value: number | null;
    onValueChange: (value: number | null) => void;
}

export function MoneyInput({
    currency,
    locale = 'es-CL',
    className,
    value,
    onValueChange,
    onBlur,
    onFocus,
    ...props
}: MoneyInputProps) {
    const [displayValue, setDisplayValue] = React.useState('');
    const isFocusedRef = React.useRef(false);

    React.useEffect(() => {
        if (isFocusedRef.current) return;
        if (value === null || Number.isNaN(value)) {
            setDisplayValue('');
            return;
        }
        setDisplayValue(formatCurrencyValue(value, currency, locale));
    }, [currency, locale, value]);

    return (
        <InputGroup>
            <InputGroupAddon className="tabular-nums">
                {currency}
            </InputGroupAddon>
            <InputGroupInput
                type="text"
                inputMode="decimal"
                className={cn('text-right tabular-nums', className)}
                value={displayValue}
                onChange={(event) => {
                    const nextValue = event.target.value;
                    const parsed = parseCurrencyInput(
                        nextValue,
                        currency,
                        locale,
                    );

                    onValueChange(parsed);

                    if (!nextValue.trim() || parsed === null) {
                        setDisplayValue('');
                        return;
                    }

                    const fractionDigits = getCurrencyFractionDigits(
                        locale,
                        currency,
                    );
                    const { decimal } = getNumberSeparators(locale);
                    const hasDecimal =
                        fractionDigits > 0 && nextValue.includes(decimal);

                    if (hasDecimal) {
                        const [rawInteger, rawFraction = ''] =
                            nextValue.split(decimal);
                        const parsedInteger =
                            parseCurrencyInput(rawInteger, currency, locale) ?? 0;
                        const formattedInteger = formatCurrencyValue(
                            parsedInteger,
                            currency,
                            locale,
                        );
                        const sanitizedFraction = rawFraction
                            .replace(/[^\d]/g, '')
                            .slice(0, fractionDigits);

                        if (nextValue.trim().endsWith(decimal)) {
                            setDisplayValue(`${formattedInteger}${decimal}`);
                            return;
                        }

                        setDisplayValue(
                            `${formattedInteger}${decimal}${sanitizedFraction}`,
                        );
                        return;
                    }

                    setDisplayValue(
                        formatCurrencyValue(parsed, currency, locale),
                    );
                }}
                onFocus={(event) => {
                    isFocusedRef.current = true;
                    onFocus?.(event);
                }}
                onBlur={(event) => {
                    isFocusedRef.current = false;
                    if (!displayValue.trim()) {
                        onValueChange(null);
                        setDisplayValue('');
                        onBlur?.(event);
                        return;
                    }
                    const parsed = parseCurrencyInput(
                        displayValue,
                        currency,
                        locale,
                    );
                    onValueChange(parsed);
                    if (parsed !== null) {
                        setDisplayValue(
                            formatCurrencyValue(parsed, currency, locale),
                        );
                    }
                    onBlur?.(event);
                }}
                {...props}
            />
        </InputGroup>
    );
}
