import * as React from 'react';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

const ResponsivePopoverContext = React.createContext<{ isMobile: boolean }>({
    isMobile: false,
});

interface ResponsivePopoverProps {
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    modal?: boolean;
    children: React.ReactNode;
}

/**
 * Drop-in replacement for the shadcn Popover compound that renders a Radix
 * Popover on desktop and a vaul bottom Drawer on mobile. Same controlled
 * `open`/`onOpenChange` API; desktop-only props (align, width classes) are
 * ignored on mobile.
 */
function ResponsivePopover({
    open,
    onOpenChange,
    modal,
    children,
}: ResponsivePopoverProps) {
    const isMobile = useIsMobile();

    return (
        <ResponsivePopoverContext.Provider value={{ isMobile }}>
            {isMobile ? (
                <Drawer open={open} onOpenChange={onOpenChange}>
                    {children}
                </Drawer>
            ) : (
                <Popover open={open} onOpenChange={onOpenChange} modal={modal}>
                    {children}
                </Popover>
            )}
        </ResponsivePopoverContext.Provider>
    );
}

function ResponsivePopoverTrigger(
    props: React.ComponentProps<typeof PopoverTrigger>,
) {
    const { isMobile } = React.useContext(ResponsivePopoverContext);
    return isMobile ? (
        <DrawerTrigger {...props} />
    ) : (
        <PopoverTrigger {...props} />
    );
}

interface ResponsivePopoverContentProps extends React.ComponentProps<
    typeof PopoverContent
> {
    title?: string;
    description?: string;
    drawerClassName?: string;
    /**
     * Let full-bleed list content reach the drawer's bottom edge, keeping only
     * the iOS safe-area inset instead of the default comfortable padding.
     */
    flushBottom?: boolean;
}

function ResponsivePopoverContent({
    className,
    title,
    description,
    drawerClassName,
    flushBottom = false,
    children,
    ...props
}: ResponsivePopoverContentProps) {
    const { isMobile } = React.useContext(ResponsivePopoverContext);

    if (isMobile) {
        return (
            <DrawerContent className={cn('max-h-[92vh]', drawerClassName)}>
                <DrawerTitle className="sr-only">
                    {title ?? 'Opciones'}
                </DrawerTitle>

                {description && (
                    <DrawerDescription className="sr-only">
                        {description}
                    </DrawerDescription>
                )}

                <div
                    className={cn(
                        'flex max-h-[78vh] min-h-0 flex-1 flex-col overflow-y-auto pt-2',
                        flushBottom
                            ? 'pb-[env(safe-area-inset-bottom)]'
                            : 'pb-[calc(1rem+env(safe-area-inset-bottom))]',
                    )}
                >
                    {children}
                </div>
            </DrawerContent>
        );
    }

    return (
        <PopoverContent className={className} {...props}>
            {children}
        </PopoverContent>
    );
}

export {
    ResponsivePopover,
    ResponsivePopoverTrigger,
    ResponsivePopoverContent,
};
