import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRightIcon,
    LayoutGridIcon,
    MenuIcon,
    PiggyBankIcon,
    PlusIcon,
    type LucideIcon,
} from 'lucide-react';
import { useCreateTransaction } from '@/components/transactions/create-transaction-provider';
import { useSidebar } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

/**
 * Mobile-only quick-access bar fixed to the bottom of the screen. Replaces the
 * top navigation on small screens: primary destinations plus the burger that
 * opens the full sidebar menu, with "new transaction" as the central CTA.
 */
export function MobileBottomToolbar() {
    const { url } = usePage();
    const { toggleSidebar } = useSidebar();
    const { open } = useCreateTransaction();

    const isActive = (href: string) =>
        url === href ||
        url.startsWith(`${href}?`) ||
        url.startsWith(`${href}/`);

    return (
        <nav
            aria-label="Accesos rápidos"
            className="fixed inset-x-0 bottom-0 z-40 border-t border-sidebar-border/60 bg-background/95 pb-[max(env(safe-area-inset-bottom),0.5rem)] backdrop-blur md:hidden"
        >
            <div className="grid grid-cols-5 items-end px-1 pt-1.5">
                <ToolbarLink
                    href="/dashboard"
                    icon={LayoutGridIcon}
                    label="Inicio"
                    active={isActive('/dashboard')}
                />
                <ToolbarLink
                    href="/transactions"
                    icon={ArrowLeftRightIcon}
                    label="Transacciones"
                    active={isActive('/transactions')}
                />
                <div className="flex justify-center">
                    <button
                        type="button"
                        onClick={open}
                        aria-label="Nueva transacción"
                        className="-mt-5 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg shadow-primary/30 transition-transform active:scale-95"
                    >
                        <PlusIcon className="size-6" />
                    </button>
                </div>
                <ToolbarLink
                    href="/budgets"
                    icon={PiggyBankIcon}
                    label="Budgets"
                    active={isActive('/budgets')}
                />
                <ToolbarButton
                    icon={MenuIcon}
                    label="Menú"
                    onClick={toggleSidebar}
                />
            </div>
        </nav>
    );
}

interface ToolbarItemProps {
    icon: LucideIcon;
    label: string;
    active?: boolean;
}

function ToolbarLink({
    href,
    icon: Icon,
    label,
    active,
}: ToolbarItemProps & { href: string }) {
    return (
        <Link
            href={href}
            className={cn(
                'flex flex-col items-center gap-0.5 rounded-md py-1.5 text-[10px] font-medium transition-colors',
                active ? 'text-primary' : 'text-muted-foreground',
            )}
        >
            <Icon className="size-5 shrink-0" />
            <span className="w-full truncate text-center leading-none">
                {label}
            </span>
        </Link>
    );
}

function ToolbarButton({
    icon: Icon,
    label,
    onClick,
}: ToolbarItemProps & { onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex flex-col items-center gap-0.5 rounded-md py-1.5 text-[10px] font-medium text-muted-foreground transition-colors"
        >
            <Icon className="size-5 shrink-0" />
            <span className="w-full truncate text-center leading-none">
                {label}
            </span>
        </button>
    );
}
