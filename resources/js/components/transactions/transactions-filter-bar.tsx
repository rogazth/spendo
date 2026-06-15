import { AccountAvatar, accountToOption } from '@/components/accounts/account-select';
import { CategoryAvatar } from '@/components/categories/category-avatar';
import { CategoryMultiSelect } from '@/components/categories/category-picker';
import { DateFilterDropdown } from '@/components/date-filter-dropdown';
import { FilterPill } from '@/components/filter-pill';
import { Button } from '@/components/ui/button';
import { SelectList, type SelectOption } from '@/components/ui/select-list';
import type { Account, Budget, Category } from '@/types';

const ALL = '__all__';

export interface TransactionFilters {
    budget_id: string;
    account_id: string;
    category_ids: string[];
    date_from: string;
    date_to: string;
    dates_all: boolean;
}

interface TransactionsFilterBarProps {
    filters: TransactionFilters;
    accounts: Account[];
    budgets: Pick<Budget, 'id' | 'uuid' | 'name' | 'color' | 'emoji'>[];
    categories: Category[];
    onChange: (next: Partial<TransactionFilters>) => void;
    onClear: () => void;
    showClear: boolean;
}

type BudgetOption = Pick<Budget, 'id' | 'uuid' | 'name' | 'color' | 'emoji'>;

interface FlatCategory {
    id: number;
    name: string;
    color: string;
    emoji: string | null;
}

function flattenCategories(categories: Category[]): FlatCategory[] {
    return categories.flatMap((category) => [
        {
            id: category.id,
            name: category.name,
            color: category.color,
            emoji: category.emoji,
        },
        ...(category.children?.map((child) => ({
            id: child.id,
            name: child.name,
            color: child.color,
            emoji: child.emoji,
        })) ?? []),
    ]);
}

function budgetToOption(budget: BudgetOption): SelectOption {
    return {
        id: budget.id,
        label: budget.name,
        keywords: [budget.name],
        leading: (
            <CategoryAvatar
                color={budget.color}
                emoji={budget.emoji}
                size="md"
            />
        ),
    };
}

export function TransactionsFilterBar({
    filters,
    accounts,
    budgets,
    categories,
    onChange,
    onClear,
    showClear,
}: TransactionsFilterBarProps) {
    const selectedAccount = accounts.find(
        (account) => String(account.id) === filters.account_id,
    );
    const selectedBudget = budgets.find(
        (budget) => String(budget.id) === filters.budget_id,
    );

    const selectedCategories = flattenCategories(categories).filter((category) =>
        filters.category_ids.includes(String(category.id)),
    );

    return (
        <div className="no-scrollbar -mx-4 flex flex-nowrap items-center gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:overflow-visible md:px-0">
            <FilterPill
                label="Cuenta"
                value={
                    selectedAccount ? (
                        <AccountValue account={selectedAccount} />
                    ) : undefined
                }
                contentClassName="w-[280px] p-0"
                flushBottom
            >
                {({ close }) => (
                    <SelectList
                        mode="single"
                        options={accounts.map((account) =>
                            accountToOption(account, { showBalance: true }),
                        )}
                        value={
                            filters.account_id
                                ? Number(filters.account_id)
                                : null
                        }
                        onChange={(id) =>
                            onChange({
                                account_id: id === null ? '' : String(id),
                            })
                        }
                        onSelect={close}
                        searchPlaceholder="Buscar cuenta..."
                        emptyMessage="Sin cuentas"
                    />
                )}
            </FilterPill>

            <DateFilterDropdown
                dateFrom={filters.date_from}
                dateTo={filters.date_to}
                datesAll={filters.dates_all}
                onChange={(next) =>
                    onChange({
                        date_from: next.dateFrom,
                        date_to: next.dateTo,
                        dates_all: false,
                    })
                }
                onClearDates={() =>
                    onChange({
                        date_from: '',
                        date_to: '',
                        dates_all: true,
                    })
                }
            />

            <FilterPill
                label="Categoría"
                value={
                    selectedCategories.length > 0 ? (
                        <CategoryValue selected={selectedCategories} />
                    ) : undefined
                }
                onClear={() => onChange({ category_ids: [] })}
                contentClassName="w-[280px] p-0"
                flushBottom
            >
                {() => (
                    <CategoryMultiSelect
                        categories={categories}
                        value={filters.category_ids.map(Number)}
                        onChange={(ids) =>
                            onChange({ category_ids: ids.map(String) })
                        }
                    />
                )}
            </FilterPill>

            <FilterPill
                label="Presupuesto"
                value={
                    selectedBudget ? (
                        <BudgetValue budget={selectedBudget} />
                    ) : undefined
                }
                onClear={() => onChange({ budget_id: ALL })}
                contentClassName="w-[260px] p-0"
                flushBottom
            >
                {({ close }) => (
                    <SelectList
                        mode="single"
                        options={budgets.map(budgetToOption)}
                        value={
                            filters.budget_id && filters.budget_id !== ALL
                                ? Number(filters.budget_id)
                                : null
                        }
                        onChange={(id) =>
                            onChange({
                                budget_id: id === null ? ALL : String(id),
                            })
                        }
                        onSelect={close}
                        searchPlaceholder="Buscar presupuesto..."
                        emptyMessage="Sin presupuestos"
                    />
                )}
            </FilterPill>

            {showClear && (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={onClear}
                    className="text-muted-foreground hover:text-foreground"
                >
                    Limpiar
                </Button>
            )}
        </div>
    );
}

function AccountValue({ account }: { account: Account }) {
    return (
        <>
            <AccountAvatar emoji={account.emoji} />
            <span>{account.name}</span>
        </>
    );
}

function BudgetValue({ budget }: { budget: BudgetOption }) {
    return (
        <>
            <CategoryAvatar color={budget.color} emoji={budget.emoji} />
            <span className="truncate">{budget.name}</span>
        </>
    );
}

function CategoryValue({ selected }: { selected: FlatCategory[] }) {
    if (selected.length === 1) {
        const [only] = selected;
        return (
            <>
                <CategoryAvatar color={only.color} emoji={only.emoji} />
                <span className="truncate">{only.name}</span>
            </>
        );
    }

    return <span>{selected.length} seleccionadas</span>;
}

export { ALL as ALL_FILTER };
