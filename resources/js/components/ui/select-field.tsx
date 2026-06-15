import { ChevronsUpDownIcon } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import {
    ResponsivePopover,
    ResponsivePopoverContent,
    ResponsivePopoverTrigger,
} from '@/components/responsive-popover';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface SelectFieldProps {
    /** Rendered inside the trigger when there is a selection. */
    value?: ReactNode;
    placeholder: string;
    /** Accessible/drawer title. Defaults to `placeholder`. */
    title?: string;
    id?: string;
    disabled?: boolean;
    triggerClassName?: string;
    contentClassName?: string;
    align?: 'start' | 'center' | 'end';
    children: (helpers: { close: () => void }) => ReactNode;
}

/**
 * Combobox trigger + responsive popover wrapper for the form half of the
 * shared selector stack. Pair it with `SelectList` for the body; the render
 * prop hands back a `close` helper so single-select bodies can dismiss on pick.
 */
export function SelectField({
    value,
    placeholder,
    title,
    id,
    disabled,
    triggerClassName,
    contentClassName,
    align = 'start',
    children,
}: SelectFieldProps) {
    const [open, setOpen] = useState(false);
    const close = () => setOpen(false);

    return (
        <ResponsivePopover open={open} onOpenChange={setOpen} modal>
            <ResponsivePopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className={cn(
                        'w-full justify-between font-normal',
                        !value && 'text-muted-foreground',
                        triggerClassName,
                    )}
                >
                    {value ? (
                        <span className="flex min-w-0 items-center gap-2">
                            {value}
                        </span>
                    ) : (
                        <span className="text-sm">{placeholder}</span>
                    )}
                    <ChevronsUpDownIcon className="size-4 shrink-0 opacity-50" />
                </Button>
            </ResponsivePopoverTrigger>
            <ResponsivePopoverContent
                title={title ?? placeholder}
                align={align}
                flushBottom
                className={cn(
                    'w-[var(--radix-popover-trigger-width)] min-w-[260px] p-0',
                    contentClassName,
                )}
            >
                {children({ close })}
            </ResponsivePopoverContent>
        </ResponsivePopover>
    );
}
