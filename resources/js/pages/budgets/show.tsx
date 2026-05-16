import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeftIcon, MoreHorizontalIcon, PencilIcon, Trash2Icon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    BudgetItemCard,
    type BudgetItemCardEntry,
} from '@/components/budgets/budget-item-card';
import {
    BudgetSummaryCards,
    type BudgetSummaryEntry,
} from '@/components/budgets/budget-summary-cards';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { BudgetFormDialog } from '@/components/forms/budget-form-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import type { Account, BreadcrumbItem, Budget, Category } from '@/types';

interface ShowSummary extends BudgetSummaryEntry {
    percentage: number;
    current_cycle_start: string;
    current_cycle_end: string;
}

interface Props {
    budget: Budget;
    summary: ShowSummary;
    categoryProgress: BudgetItemCardEntry[];
    accounts: Account[];
    categories: Category[];
}

const FREQUENCY_LABELS: Record<string, string> = {
    weekly: 'Semanal',
    biweekly: 'Quincenal',
    monthly: 'Mensual',
    bimonthly: 'Bimensual',
};

function formatDateRange(start: string, end: string, locale: string): string {
    const startDate = new Date(`${start}T00:00:00`);
    const endDate = new Date(`${end}T00:00:00`);
    const options: Intl.DateTimeFormatOptions = {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    };
    return `${startDate.toLocaleDateString(locale, options)} — ${endDate.toLocaleDateString(locale, options)}`;
}

export default function BudgetShow({
    budget,
    summary,
    categoryProgress,
    accounts,
    categories,
}: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [confirmDeleteOpen, setConfirmDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const locale = summary.currency_locale ?? 'es-CL';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Budgets', href: '/budgets' },
        { title: budget.name, href: `/budgets/${budget.uuid}` },
    ];

    const summaryByCurrency: Record<string, BudgetSummaryEntry> = {
        [budget.currency]: summary,
    };

    const handleDelete = () => {
        setDeleting(true);
        router.delete(`/budgets/${budget.uuid}`, {
            preserveScroll: false,
            onSuccess: () => {
                toast.success('Budget eliminado');
            },
            onError: () => {
                toast.error('No se pudo eliminar el budget');
                setDeleting(false);
                setConfirmDeleteOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={budget.name} />

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                asChild
                                className="text-muted-foreground -ml-2 size-8"
                            >
                                <Link href="/budgets" aria-label="Volver a budgets">
                                    <ArrowLeftIcon />
                                </Link>
                            </Button>
                            <h1 className="text-foreground truncate text-2xl font-bold tracking-tight">
                                {budget.name}
                            </h1>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {FREQUENCY_LABELS[budget.frequency] ?? budget.frequency}
                            {' · '}
                            {formatDateRange(
                                summary.current_cycle_start,
                                summary.current_cycle_end,
                                locale,
                            )}
                        </p>
                        {budget.description && (
                            <p className="text-muted-foreground mt-2 max-w-2xl text-sm">
                                {budget.description}
                            </p>
                        )}
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="icon">
                                <span className="sr-only">Abrir menú</span>
                                <MoreHorizontalIcon />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => setEditOpen(true)}>
                                <PencilIcon />
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => setConfirmDeleteOpen(true)}
                            >
                                <Trash2Icon />
                                Eliminar
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <BudgetSummaryCards summary={summaryByCurrency} />

                <div className="space-y-3">
                    <h2 className="text-foreground text-lg font-semibold">
                        Categorías presupuestadas
                    </h2>

                    {categoryProgress.length === 0 ? (
                        <div className="bg-card border-border text-muted-foreground rounded-xl border p-6 text-center text-sm">
                            Este budget no tiene categorías.
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                            {categoryProgress.map((item) => (
                                <BudgetItemCard
                                    key={item.id}
                                    item={item}
                                    currency={budget.currency}
                                    locale={locale}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <BudgetFormDialog
                open={editOpen}
                onOpenChange={setEditOpen}
                accounts={accounts}
                categories={categories}
                budget={budget}
            />

            <ConfirmDialog
                open={confirmDeleteOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirmDeleteOpen(false);
                        setDeleting(false);
                    }
                }}
                title="¿Eliminar budget?"
                description={
                    <>
                        Se eliminará{' '}
                        <span className="font-semibold">{budget.name}</span> y
                        todas sus categorías presupuestadas. Esta acción no se
                        puede deshacer.
                    </>
                }
                variant="destructive"
                confirmLabel="Eliminar"
                onConfirm={handleDelete}
                loading={deleting}
            />
        </AppLayout>
    );
}
