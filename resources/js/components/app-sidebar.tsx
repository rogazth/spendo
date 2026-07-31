import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ChartLine,
    LayoutGrid,
    PiggyBank,
    Tag,
    Wallet,
} from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Cuentas',
        href: '/accounts',
        icon: Wallet,
    },
    {
        title: 'Transacciones',
        href: '/transactions',
        icon: ArrowLeftRight,
    },
    {
        title: 'Categorías',
        href: '/categories',
        icon: Tag,
    },
    {
        title: 'Budgets',
        href: '/budgets',
        icon: PiggyBank,
    },
    {
        title: 'Insights',
        href: '/insights',
        icon: ChartLine,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <div
                role="separator"
                aria-orientation="horizontal"
                className="-mx-2 h-px shrink-0 bg-sidebar-border"
            />
            <SidebarFooter className="pb-0">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
