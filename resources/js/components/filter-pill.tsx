import { PlusIcon, XIcon } from 'lucide-react';
import * as React from 'react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
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
    children,
}: FilterPillProps) {
    const [open, setOpen] = React.useState(false);
    const isActive =
        value !== undefined && value !== null && value !== '' && value !== false;

    const close = React.useCallback(() => setOpen(false), []);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <div
                className={cn(
                    'inline-flex h-8 items-center rounded-full border text-sm transition-colors',
                    isActive
                        ? 'border-border bg-card shadow-xs'
                        : 'border-dashed border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground',
                )}
            >
                <PopoverTrigger asChild>
                    <button
                        type="button"
                        className={cn(
                            'inline-flex h-full items-center gap-1.5 rounded-full px-3 outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer',
                            isActive && 'pr-2',
                        )}
                    >
                        {!isActive && <PlusIcon className="size-3.5" />}
                        <span className={cn(isActive && 'text-muted-foreground')}>
                            {label}
                            {isActive && (
                                <span className="text-muted-foreground">:</span>
                            )}
                        </span>
                        {isActive && (
                            <span className="text-foreground inline-flex items-center gap-1.5 font-medium">
                                {value}
                            </span>
                        )}
                    </button>
                </PopoverTrigger>
                {isActive && onClear && (
                    <button
                        type="button"
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            onClear();
                        }}
                        className="text-muted-foreground hover:bg-muted hover:text-foreground mr-1 inline-flex size-6 cursor-pointer items-center justify-center rounded-full transition-colors"
                        aria-label={`Limpiar ${label}`}
                    >
                        <XIcon className="size-3.5" />
                    </button>
                )}
            </div>
            <PopoverContent
                align={align}
                className={cn('w-auto p-2', contentClassName)}
            >
                {typeof children === 'function'
                    ? children({ close })
                    : children}
            </PopoverContent>
        </Popover>
    );
}
