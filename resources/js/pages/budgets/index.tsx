import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useState } from 'react';
import { BudgetCard } from '@/components/budgets/budget-card';
import {
    BudgetSummaryCards,
    type BudgetSummaryEntry,
} from '@/components/budgets/budget-summary-cards';
import { CreateBudgetCard } from '@/components/budgets/create-budget-card';
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
    const items = budgets.data;
    const isEmpty = items.length === 0;

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
                            Active budgets
                        </h2>
                    )}

                    <div className="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {items.map((budget) => (
                            <BudgetCard key={budget.uuid} budget={budget} />
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
        </AppLayout>
    );
}
