import * as React from 'react';
import { SidebarInset } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

type Props = React.ComponentProps<'main'> & {
    variant?: 'header' | 'sidebar';
};

export function AppContent({ variant = 'header', children, className, ...props }: Props) {
    if (variant === 'sidebar') {
        return (
            <SidebarInset className={cn('relative', className)} {...props}>
                <div
                    aria-hidden
                    className="bg-halftone pointer-events-none absolute inset-0 z-0 opacity-40"
                />
                <div className="relative z-10 flex h-full flex-1 flex-col">
                    {children}
                </div>
            </SidebarInset>
        );
    }

    return (
        <main
            className={cn(
                'mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl',
                className,
            )}
            {...props}
        >
            {children}
        </main>
    );
}
