import { CheckIcon, DeleteIcon, Loader2Icon } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface NumpadProps {
    onDigit: (digit: number) => void;
    onBackspace: () => void;
    onSubmit: () => void;
    disabled?: boolean;
    submitting?: boolean;
}

interface KeyConfig {
    label: React.ReactNode;
    onPress: () => void;
    variant?: 'default' | 'accent';
    className: string;
    ariaLabel: string;
}

export function Numpad({
    onDigit,
    onBackspace,
    onSubmit,
    disabled,
    submitting,
}: NumpadProps) {
    const digit = (n: number, className: string): KeyConfig => ({
        label: n,
        onPress: () => onDigit(n),
        className,
        ariaLabel: `Dígito ${n}`,
    });

    const keys: KeyConfig[] = [
        digit(7, 'col-start-1 row-start-1'),
        digit(8, 'col-start-2 row-start-1'),
        digit(9, 'col-start-3 row-start-1'),
        {
            label: <DeleteIcon className="size-5" />,
            onPress: onBackspace,
            className: 'col-start-4 row-start-1',
            ariaLabel: 'Borrar último dígito',
        },
        digit(4, 'col-start-1 row-start-2'),
        digit(5, 'col-start-2 row-start-2'),
        digit(6, 'col-start-3 row-start-2'),
        {
            label: submitting ? (
                <Loader2Icon className="size-5 animate-spin" />
            ) : (
                <CheckIcon className="size-6" />
            ),
            onPress: onSubmit,
            variant: 'accent',
            className: 'col-start-4 row-start-2 row-span-2',
            ariaLabel: 'Confirmar',
        },
        digit(1, 'col-start-1 row-start-3'),
        digit(2, 'col-start-2 row-start-3'),
        digit(3, 'col-start-3 row-start-3'),
        digit(0, 'col-start-1 row-start-4 col-span-3'),
    ];

    return (
        <div className="grid grid-cols-4 grid-rows-4 gap-2">
            {keys.map((key, index) => (
                <button
                    key={index}
                    type="button"
                    aria-label={key.ariaLabel}
                    disabled={disabled || (submitting && key.variant !== 'accent')}
                    onClick={key.onPress}
                    className={cn(
                        'flex h-12 items-center justify-center rounded-xl text-xl font-semibold transition-all',
                        'active:scale-95 disabled:opacity-50 disabled:active:scale-100',
                        'tabular-nums',
                        key.variant === 'accent'
                            ? 'bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 active:bg-primary/95'
                            : 'bg-muted text-foreground hover:bg-muted/70 active:bg-muted',
                        key.className,
                    )}
                >
                    {key.label}
                </button>
            ))}
        </div>
    );
}
