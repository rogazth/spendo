import { Head, router } from '@inertiajs/react';
import { PlusIcon, ReceiptIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { getTransactionColumns } from '@/components/data-table/columns/transaction-columns';
import { DataTable } from '@/components/data-table/data-table';
import { DateFilterDropdown } from '@/components/date-filter-dropdown';
import { FilterDropdown } from '@/components/filter-dropdown';
import { TransactionFormDialog } from '@/components/forms/transaction-form-dialog';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { useDebounce } from '@/hooks/use-debounce';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type {
    Account,
    BreadcrumbItem,
    Transaction,
    PaginatedResponse,
    PaymentMethod,
    Category,
} from '@/types';

interface Props {
    transactions: PaginatedResponse<Transaction>;
    accounts: Account[];
    paymentMethods: PaymentMethod[];
    categories: Category[];
    filters: {
        payment_method_ids?: number[];
        account_ids?: number[];
        category_ids?: number[];
        date_from?: string;
        date_to?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Transacciones', href: '/transactions' },
];

export default function TransactionsIndex({
    transactions,
    accounts,
    paymentMethods,
    categories,
    filters,
}: Props) {
    const [formDialogOpen, setFormDialogOpen] = useState(false);
    const [editingTransaction, setEditingTransaction] = useState<
        Transaction | undefined
    >();
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deletingTransaction, setDeletingTransaction] =
        useState<Transaction | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const debouncedSearchQuery = useDebounce(searchQuery, 250);
    const [selectedAccountIds, setSelectedAccountIds] = useState<string[]>(
        filters.account_ids?.map(String) ?? [],
    );
    const [selectedPaymentMethodIds, setSelectedPaymentMethodIds] = useState<
        string[]
    >(filters.payment_method_ids?.map(String) ?? []);
    const [selectedCategoryIds, setSelectedCategoryIds] = useState<string[]>(
        filters.category_ids?.map(String) ?? [],
    );
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const didInitFilters = useRef(false);
    const lastAppliedKey = useRef('');

    const handleCreate = () => {
        setEditingTransaction(undefined);
        setFormDialogOpen(true);
    };

    const handleEdit = (transaction: Transaction) => {
        setEditingTransaction(transaction);
        setFormDialogOpen(true);
    };

    const handleDeleteClick = (transaction: Transaction) => {
        setDeletingTransaction(transaction);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (!deletingTransaction) return;

        setIsDeleting(true);
        router.delete(`/transactions/${deletingTransaction.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteDialogOpen(false);
                toast.success('Transacción eliminada');
            },
            onError: () => {
                toast.error('Error al eliminar la transacción');
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    const handleDeleteDialogOpenChange = (open: boolean) => {
        setDeleteDialogOpen(open);
        if (!open) {
            setDeletingTransaction(null);
        }
    };

    const columns = useMemo(
        () =>
            getTransactionColumns({
                onEdit: handleEdit,
                onDelete: handleDeleteClick,
            }),
        [],
    );

    const accountOptions = useMemo(
        () =>
            accounts.map((account) => ({
                id: account.id.toString(),
                name: account.name,
                is_default: account.is_default,
            })),
        [accounts],
    );

    const categoryOptions = useMemo(() => {
        return categories.flatMap((category) => {
            const parents = [
                {
                    id: category.id.toString(),
                    name: category.name,
                    color: category.color,
                    depth: 0,
                },
            ];
            const children = (category.children ?? []).map((child) => ({
                id: child.id.toString(),
                name: child.name,
                color: child.color,
                depth: 1,
            }));
            return [...parents, ...children];
        });
    }, [categories]);

    const paymentMethodOptions = useMemo(
        () =>
            paymentMethods.map((method) => ({
                id: method.id.toString(),
                name: method.name,
                is_default: method.is_default,
            })),
        [paymentMethods],
    );

    const handleApplyFilters = () => {
        const params: Record<string, string | string[]> = {};

        if (selectedPaymentMethodIds.length > 0) {
            params.payment_method_ids = selectedPaymentMethodIds;
        }

        if (selectedAccountIds.length > 0) {
            params.account_ids = selectedAccountIds;
        }

        if (selectedCategoryIds.length > 0) {
            params.category_ids = selectedCategoryIds;
        }

        if (dateFrom) {
            params.date_from = dateFrom;
        }

        if (dateTo) {
            params.date_to = dateTo;
        }

        router.get('/transactions', params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    useEffect(() => {
        if (!didInitFilters.current) {
            didInitFilters.current = true;
            const initialKey = [
                [...selectedPaymentMethodIds].sort().join(','),
                [...selectedAccountIds].sort().join(','),
                [...selectedCategoryIds].sort().join(','),
                dateFrom,
                dateTo,
            ].join('|');
            lastAppliedKey.current = initialKey;
            return;
        }

        const nextKey = [
            [...selectedPaymentMethodIds].sort().join(','),
            [...selectedAccountIds].sort().join(','),
            [...selectedCategoryIds].sort().join(','),
            dateFrom,
            dateTo,
        ].join('|');

        if (nextKey === lastAppliedKey.current) {
            return;
        }

        const timeout = window.setTimeout(() => {
            lastAppliedKey.current = nextKey;
            handleApplyFilters();
        }, 250);

        return () => window.clearTimeout(timeout);
    }, [
        selectedPaymentMethodIds,
        selectedAccountIds,
        selectedCategoryIds,
        dateFrom,
        dateTo,
    ]);

    const filteredTransactions = useMemo(() => {
        if (!debouncedSearchQuery.trim()) return transactions.data;
        const query = debouncedSearchQuery.toLowerCase();
        return transactions.data.filter((transaction) =>
            (transaction.description ?? '').toLowerCase().includes(query),
        );
    }, [transactions.data, debouncedSearchQuery]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transacciones" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Transacciones</h1>

                    {transactions.data.length > 0 && (
                        <Button onClick={handleCreate}>
                            <PlusIcon className="h-4 w-4" />
                            Nueva Transacción
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        value={searchQuery}
                        onChange={(event) => setSearchQuery(event.target.value)}
                        placeholder="Buscar..."
                        className="w-full sm:w-[220px]"
                    />
                    <FilterDropdown
                        title="Métodos de Pago"
                        options={paymentMethodOptions.map((method) => ({
                            value: method.id,
                            label: (
                                <span className="flex items-center gap-2">
                                    <span>{method.name}</span>
                                    {method.is_default && (
                                        <span className="text-xs text-muted-foreground">
                                            (Por defecto)
                                        </span>
                                    )}
                                </span>
                            ),
                        }))}
                        selectedValues={new Set(selectedPaymentMethodIds)}
                        onChange={(next) =>
                            setSelectedPaymentMethodIds(Array.from(next))
                        }
                        searchPlaceholder="Buscar métodos de pago..."
                        emptyLabel="No hay métodos"
                    />
                    <FilterDropdown
                        title="Cuentas"
                        options={accountOptions.map((account) => ({
                            value: account.id,
                            label: (
                                <span className="flex items-center gap-2">
                                    <span>{account.name}</span>
                                    {account.is_default && (
                                        <span className="text-xs text-muted-foreground">
                                            (Por defecto)
                                        </span>
                                    )}
                                </span>
                            ),
                        }))}
                        selectedValues={new Set(selectedAccountIds)}
                        onChange={(next) =>
                            setSelectedAccountIds(Array.from(next))
                        }
                        searchPlaceholder="Buscar cuentas..."
                        emptyLabel="No hay cuentas"
                    />
                    <FilterDropdown
                        title="Categorías"
                        options={categoryOptions.map((category) => ({
                            value: category.id,
                            label: (
                                <span
                                    className={cn(
                                        'flex items-center gap-2',
                                        category.depth === 1 && 'pl-4',
                                    )}
                                >
                                    <span
                                        className="h-2.5 w-2.5 rounded-full"
                                        style={{
                                            backgroundColor: category.color,
                                        }}
                                    />
                                    {category.name}
                                </span>
                            ),
                        }))}
                        selectedValues={new Set(selectedCategoryIds)}
                        onChange={(next) =>
                            setSelectedCategoryIds(Array.from(next))
                        }
                        searchPlaceholder="Buscar categorías..."
                        emptyLabel="No hay categorías"
                    />
                    <DateFilterDropdown
                        dateFrom={dateFrom}
                        dateTo={dateTo}
                        onChange={(next) => {
                            setDateFrom(next.dateFrom);
                            setDateTo(next.dateTo);
                        }}
                    />
                </div>

                {transactions.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ReceiptIcon />
                            </EmptyMedia>
                            <EmptyTitle>
                                No tienes transacciones registradas
                            </EmptyTitle>
                            <EmptyDescription>
                                Registra tu primera transacción para comenzar a
                                rastrear tus gastos e ingresos.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button onClick={handleCreate}>
                                Crear transacción
                            </Button>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <DataTable columns={columns} data={filteredTransactions} />
                )}

                {transactions.meta.last_page > 1 &&
                    !debouncedSearchQuery.trim() && (
                    <div className="flex justify-center gap-2">
                        {transactions.links.prev && (
                            <Button variant="outline" asChild>
                                <a href={transactions.links.prev}>Anterior</a>
                            </Button>
                        )}
                        <span className="flex items-center px-4 text-sm text-muted-foreground">
                            Página {transactions.meta.current_page} de{' '}
                            {transactions.meta.last_page}
                        </span>
                        {transactions.links.next && (
                            <Button variant="outline" asChild>
                                <a href={transactions.links.next}>Siguiente</a>
                            </Button>
                        )}
                    </div>
                )}
            </div>

            <TransactionFormDialog
                open={formDialogOpen}
                onOpenChange={setFormDialogOpen}
                transaction={editingTransaction}
                accounts={accounts}
                paymentMethods={paymentMethods}
                categories={categories}
            />

            <ConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={handleDeleteDialogOpenChange}
                title="Eliminar transacción"
                description={
                    <>
                        ¿Estás seguro de eliminar{' '}
                        <span className="font-semibold">
                            {deletingTransaction?.description}
                        </span>
                        ? Esta acción no se puede deshacer.
                    </>
                }
                confirmLabel="Eliminar"
                variant="destructive"
                onConfirm={handleDeleteConfirm}
                loading={isDeleting}
            />
        </AppLayout>
    );
}
