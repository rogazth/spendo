import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { MobileBottomToolbar } from '@/components/mobile-bottom-toolbar';
import { CreateTransactionProvider } from '@/components/transactions/create-transaction-provider';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <CreateTransactionProvider>
                    <div className="flex flex-1 flex-col pb-[calc(4.5rem+env(safe-area-inset-bottom))] md:pb-0">
                        {children}
                    </div>
                    <MobileBottomToolbar />
                </CreateTransactionProvider>
            </AppContent>
        </AppShell>
    );
}
