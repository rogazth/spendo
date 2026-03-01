import { Head } from '@inertiajs/react';
import { PiggyBankIcon, PlusIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { getBudgetColumns } from '@/components/data-table/columns/budget-columns';
import { DataTable } from '@/components/data-table/data-table';
import { BudgetFormDialog } from '@/components/forms/budget-form-dialog';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import AppLayout from '@/layouts/app-layout';
import type {
    Account,
    BreadcrumbItem,
    Budget,
    Category,
    PaginatedResponse,
} from '@/types';

interface Props {
    budgets: PaginatedResponse<Budget>;
    accounts: Account[];
    categories: Category[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Budgets', href: '/budgets' },
];

export default function BudgetsIndex({ budgets, accounts, categories }: Props) {
    const [formDialogOpen, setFormDialogOpen] = useState(false);
    const columns = useMemo(() => getBudgetColumns(), []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Budgets" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Budgets</h1>
                    <Button onClick={() => setFormDialogOpen(true)}>
                        <PlusIcon className="h-4 w-4" />
                        Nuevo budget
                    </Button>
                </div>

                {budgets.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <PiggyBankIcon />
                            </EmptyMedia>
                            <EmptyTitle>No tienes budgets creados</EmptyTitle>
                            <EmptyDescription>
                                Crea un budget para controlar el gasto por
                                categoría y ciclo.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button onClick={() => setFormDialogOpen(true)}>
                                Crear budget
                            </Button>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <DataTable columns={columns} data={budgets.data} />
                )}

                {budgets.meta.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {budgets.links.prev && (
                            <Button variant="outline" asChild>
                                <a href={budgets.links.prev}>Anterior</a>
                            </Button>
                        )}
                        <span className="flex items-center px-4 text-sm text-muted-foreground">
                            Página {budgets.meta.current_page} de{' '}
                            {budgets.meta.last_page}
                        </span>
                        {budgets.links.next && (
                            <Button variant="outline" asChild>
                                <a href={budgets.links.next}>Siguiente</a>
                            </Button>
                        )}
                    </div>
                )}
            </div>

            <BudgetFormDialog
                open={formDialogOpen}
                onOpenChange={setFormDialogOpen}
                accounts={accounts}
                categories={categories}
            />
        </AppLayout>
    );
}
