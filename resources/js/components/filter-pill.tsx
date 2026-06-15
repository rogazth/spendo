import { PlusIcon, XIcon } from 'lucide-react';
import * as React from 'react';
import {
    ResponsivePopover,
    ResponsivePopoverContent,
    ResponsivePopoverTrigger,
} from '@/components/responsive-popover';
import { cn } from '@/lib/utils';

interface FilterPillRenderProps {
    close: () => void;
}

interface FilterPillProps {
    label: string;
    value?: React.ReactNode;
    onClear?: () => void;
    align?: 'start' | 'center' | 'end';
    contentClassName?: string;
    /** Let full-bleed list content reach the bottom edge of the mobile drawer. */
    flushBottom?: boolean;
    children:
        | React.ReactNode
        | ((helpers: FilterPillRenderProps) => React.ReactNode);
}

export function FilterPill({
    label,
    value,
    onClear,
    align = 'start',
    contentClassName,
    flushBottom,
    children,
}: FilterPillProps) {
    const [open, setOpen] = React.useState(false);
    const isActive =
        value !== undefined &&
        value !== null &&
        value !== '' &&
        value !== false;

    const close = React.useCallback(() => setOpen(false), []);

    return (
        <ResponsivePopover open={open} onOpenChange={setOpen}>
            <div
                className={cn(
                    'inline-flex h-8 shrink-0 items-center rounded-full border text-sm transition-colors',
                    isActive
                        ? 'border-border bg-card shadow-xs'
                        : 'border-dashed border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground',
                )}
            >
                <ResponsivePopoverTrigger asChild>
                    <button
                        type="button"
                        className={cn(
                            'inline-flex h-full cursor-pointer items-center gap-1.5 rounded-full px-3 outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            isActive && 'pr-2',
                        )}
                    >
                        {!isActive && <PlusIcon className="size-3.5" />}
                        <span
                            className={cn(isActive && 'text-muted-foreground')}
                        >
                            {label}
                            {isActive && (
                                <span className="text-muted-foreground">:</span>
                            )}
                        </span>
                        {isActive && (
                            <span className="inline-flex items-center gap-1.5 font-medium text-foreground">
                                {value}
                            </span>
                        )}
                    </button>
                </ResponsivePopoverTrigger>

                {isActive && onClear && (
                    <button
                        type="button"
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            onClear();
                        }}
                        className="mr-1 inline-flex size-6 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        aria-label={`Limpiar ${label}`}
                    >
                        <XIcon className="size-3.5" />
                    </button>
                )}
            </div>

            <ResponsivePopoverContent
                title={label}
                align={align}
                flushBottom={flushBottom}
                className={cn('w-auto p-2', contentClassName)}
            >
                {typeof children === 'function'
                    ? children({ close })
                    : children}
            </ResponsivePopoverContent>
        </ResponsivePopover>
    );
}
