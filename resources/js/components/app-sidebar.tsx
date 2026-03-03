import { Link } from '@inertiajs/react';
import { LayoutGrid, Wallet, ArrowLeftRight, Tag, CreditCard, PiggyBank } from 'lucide-react';
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
        title: 'Instrumentos',
        href: '/instruments',
        icon: CreditCard,
    },
    {
        title: 'Budgets',
        href: '/budgets',
        icon: PiggyBank,
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

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
