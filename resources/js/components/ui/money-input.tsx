import type { ComponentProps } from 'react';
import { NumericFormat } from 'react-number-format';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import {
    getCurrencyFractionDigits,
    getNumberSeparators,
} from '@/lib/currency';
import { cn } from '@/lib/utils';

interface MoneyInputProps
    extends Omit<
        ComponentProps<'input'>,
        'type' | 'value' | 'onChange' | 'defaultValue'
    > {
    currency: string;
    locale?: string;
    value: number | null;
    onValueChange: (value: number | null) => void;
    groupClassName?: string;
    addonClassName?: string;
}

export function MoneyInput({
    currency,
    locale = 'es-CL',
    className,
    groupClassName,
    addonClassName,
    value,
    onValueChange,
    ...props
}: MoneyInputProps) {
    const fractionDigits = getCurrencyFractionDigits(locale, currency);
    const { group, decimal } = getNumberSeparators(locale);

    return (
        <InputGroup className={groupClassName}>
            <InputGroupAddon className={cn('tabular-nums', addonClassName)}>
                {currency}
            </InputGroupAddon>
            <NumericFormat
                customInput={InputGroupInput}
                inputMode={fractionDigits > 0 ? 'decimal' : 'numeric'}
                thousandSeparator={group}
                decimalSeparator={decimal}
                decimalScale={fractionDigits}
                fixedDecimalScale={fractionDigits > 0}
                allowNegative
                allowLeadingZeros={false}
                value={value ?? ''}
                onValueChange={({ floatValue, value: rawValue }) => {
                    if (!rawValue) {
                        onValueChange(null);
                        return;
                    }
                    onValueChange(floatValue ?? null);
                }}
                className={cn('text-right tabular-nums', className)}
                {...props}
            />
        </InputGroup>
    );
}
