import { Head, router } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { BudgetCard } from '@/components/budgets/budget-card';
import {
    BudgetSummaryCards,
    type BudgetSummaryEntry,
} from '@/components/budgets/budget-summary-cards';
import { CreateBudgetCard } from '@/components/budgets/create-budget-card';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { BudgetFormDialog } from '@/components/forms/budget-form-dialog';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { Account, BreadcrumbItem, Budget, Category } from '@/types';

interface Props {
    budgets: { data: Budget[] };
    summary: Record<string, BudgetSummaryEntry>;
    accounts: Account[];
    categories: Category[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Budgets', href: '/budgets' },
];

export default function BudgetsIndex({ budgets, summary, accounts, categories }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editingBudget, setEditingBudget] = useState<Budget | null>(null);
    const [deletingBudget, setDeletingBudget] = useState<Budget | null>(null);
    const [deleting, setDeleting] = useState(false);
    const items = budgets.data;
    const isEmpty = items.length === 0;

    const handleToggleActive = (budget: Budget) => {
        router.patch(`/budgets/${budget.uuid}/toggle-active`, undefined, {
            preserveScroll: true,
            onSuccess: () =>
                toast.success(
                    budget.is_active
                        ? 'Budget desactivado'
                        : 'Budget activado',
                ),
            onError: () => toast.error('No se pudo cambiar el estado'),
        });
    };

    const handleDeleteConfirm = () => {
        if (!deletingBudget) return;
        setDeleting(true);
        router.delete(`/budgets/${deletingBudget.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Budget eliminado');
                setDeletingBudget(null);
                setDeleting(false);
            },
            onError: () => {
                toast.error('No se pudo eliminar el budget');
                setDeleting(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Budgets" />

            <div className="flex flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-2xl font-bold tracking-tight">
                            Budgets
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Controla tus límites de gasto por categoría.
                        </p>
                    </div>
                    <Button onClick={() => setCreateOpen(true)}>
                        <PlusIcon />
                        Crear budget
                    </Button>
                </div>

                {!isEmpty && <BudgetSummaryCards summary={summary} />}

                <div className="space-y-3">
                    {!isEmpty && (
                        <h2 className="text-foreground text-lg font-semibold">
                            Tus budgets
                        </h2>
                    )}

                    <div className="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {items.map((budget) => (
                            <BudgetCard
                                key={budget.uuid}
                                budget={budget}
                                onEdit={setEditingBudget}
                                onToggleActive={handleToggleActive}
                                onDelete={setDeletingBudget}
                            />
                        ))}
                        <CreateBudgetCard onClick={() => setCreateOpen(true)} />
                    </div>
                </div>
            </div>

            <BudgetFormDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                accounts={accounts}
                categories={categories}
            />

            <BudgetFormDialog
                open={editingBudget !== null}
                onOpenChange={(open) => {
                    if (!open) setEditingBudget(null);
                }}
                accounts={accounts}
                categories={categories}
                budget={editingBudget}
            />

            <ConfirmDialog
                open={deletingBudget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeletingBudget(null);
                        setDeleting(false);
                    }
                }}
                title="¿Eliminar budget?"
                description={
                    <>
                        Se eliminará{' '}
                        <span className="font-semibold">
                            {deletingBudget?.name}
                        </span>{' '}
                        y todas sus categorías presupuestadas. Esta acción no se
                        puede deshacer.
                    </>
                }
                variant="destructive"
                confirmLabel="Eliminar"
                onConfirm={handleDeleteConfirm}
                loading={deleting}
            />
        </AppLayout>
    );
}
