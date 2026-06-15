import type { LucideIcon } from 'lucide-react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

export interface ResponsiveAction {
    label: string;
    icon?: LucideIcon;
    onSelect: () => void;
    variant?: 'default' | 'destructive';
}

interface ResponsiveActionMenuProps {
    actions: ResponsiveAction[];
    trigger: ReactNode;
    title?: string;
    align?: 'start' | 'center' | 'end';
}

/**
 * While a menu is open — and briefly after it closes — an outside tap on the
 * overlay can "fall through" to the row underneath and trigger its click. We
 * track open menus directly (so clicks are ignored for the whole time a menu is
 * open, regardless of event ordering) plus a short grace window after close to
 * absorb the stray fall-through tap.
 */
let actionMenuOpenCount = 0;
let actionMenuBusyUntil = 0;

export function markActionMenuBusy(): void {
    actionMenuBusyUntil = Date.now() + 350;
}

export function isActionMenuBusy(): boolean {
    return actionMenuOpenCount > 0 || Date.now() < actionMenuBusyUntil;
}

/**
 * Renders contextual actions as a dropdown menu on desktop and a bottom drawer
 * on mobile. On mobile the selected action is deferred until the drawer has
 * closed, so opening a dialog/alert afterwards doesn't fight the drawer.
 */
export function ResponsiveActionMenu({
    actions,
    trigger,
    title = 'Acciones',
    align = 'end',
}: ResponsiveActionMenuProps) {
    const isMobile = useIsMobile();
    const [open, setOpen] = useState(false);

    const handleOpenChange = useCallback((next: boolean) => {
        setOpen(next);
        markActionMenuBusy();
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }
        actionMenuOpenCount += 1;
        return () => {
            actionMenuOpenCount -= 1;
            markActionMenuBusy();
        };
    }, [open]);

    if (isMobile) {
        return (
            <Drawer open={open} onOpenChange={handleOpenChange}>
                <DrawerTrigger asChild>{trigger}</DrawerTrigger>
                <DrawerContent>
                    <DrawerTitle className="sr-only">{title}</DrawerTitle>
                    <div className="flex flex-col px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                        {actions.map((action) => {
                            const Icon = action.icon;
                            return (
                                <DrawerClose asChild key={action.label}>
                                    <Button
                                        variant="ghost"
                                        className={cn(
                                            'h-12 justify-start gap-3 text-base font-normal',
                                            action.variant === 'destructive' &&
                                                'text-destructive hover:text-destructive',
                                        )}
                                        onClick={() =>
                                            window.setTimeout(
                                                action.onSelect,
                                                200,
                                            )
                                        }
                                    >
                                        {Icon && <Icon className="size-5" />}
                                        {action.label}
                                    </Button>
                                </DrawerClose>
                            );
                        })}
                    </div>
                </DrawerContent>
            </Drawer>
        );
    }

    return (
        <DropdownMenu open={open} onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
            <DropdownMenuContent align={align}>
                {actions.map((action) => {
                    const Icon = action.icon;
                    return (
                        <DropdownMenuItem
                            key={action.label}
                            variant={action.variant}
                            onSelect={action.onSelect}
                        >
                            {Icon && <Icon />}
                            {action.label}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
