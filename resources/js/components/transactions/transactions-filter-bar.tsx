import { CheckIcon, WalletIcon } from 'lucide-react';
import { Fragment } from 'react';
import { DateFilterDropdown } from '@/components/date-filter-dropdown';
import { FilterPill } from '@/components/filter-pill';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { Account, Budget, Category } from '@/types';

const ALL = '__all__';

export interface TransactionFilters {
    budget_id: string;
    account_id: string;
    category_ids: string[];
    date_from: string;
    date_to: string;
}

interface TransactionsFilterBarProps {
    filters: TransactionFilters;
    accounts: Account[];
    budgets: Pick<Budget, 'id' | 'uuid' | 'name'>[];
    categories: Category[];
    onChange: (next: Partial<TransactionFilters>) => void;
    onClear: () => void;
    showClear: boolean;
}

interface FlatCategory {
    id: number;
    name: string;
    color: string;
    emoji: string | null;
    depth: 0 | 1;
}

function flattenCategories(categories: Category[]): FlatCategory[] {
    return categories.flatMap((category) => {
        const children =
            category.children?.map((child) => ({
                id: child.id,
                name: child.name,
                color: child.color,
                emoji: child.emoji,
                depth: 1 as const,
            })) ?? [];
        return [
            {
                id: category.id,
                name: category.name,
                color: category.color,
                emoji: category.emoji,
                depth: 0 as const,
            },
            ...children,
        ];
    });
}

interface CategoryAvatarProps {
    color: string;
    emoji: string | null;
    size?: 'sm' | 'md';
}

function CategoryAvatar({ color, emoji, size = 'sm' }: CategoryAvatarProps) {
    const sizeClass =
        size === 'sm' ? 'size-5 text-[11px]' : 'size-6 text-[13px]';

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-md border leading-none',
                sizeClass,
            )}
            style={{
                backgroundColor: `${color}20`,
                borderColor: color,
            }}
            aria-hidden
        >
            {emoji ?? (
                <span
                    className="size-1.5 rounded-full"
                    style={{ backgroundColor: color }}
                />
            )}
        </span>
    );
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

    const flatCategories = flattenCategories(categories);
    const selectedCategories = flatCategories.filter((category) =>
        filters.category_ids.includes(String(category.id)),
    );

    return (
        <div className="flex flex-wrap items-center gap-2">
            <FilterPill
                label="Cuenta"
                value={
                    selectedAccount ? (
                        <AccountValue account={selectedAccount} />
                    ) : undefined
                }
                contentClassName="w-[280px] p-0"
            >
                {({ close }) => (
                    <AccountPicker
                        accounts={accounts}
                        selectedId={filters.account_id}
                        onSelect={(id) => {
                            onChange({ account_id: id });
                            close();
                        }}
                    />
                )}
            </FilterPill>

            <FilterPill
                label="Presupuesto"
                value={selectedBudget?.name}
                onClear={() => onChange({ budget_id: ALL })}
                contentClassName="w-[260px] p-0"
            >
                {({ close }) => (
                    <BudgetPicker
                        budgets={budgets}
                        selectedId={filters.budget_id}
                        onSelect={(id) => {
                            onChange({ budget_id: id });
                            close();
                        }}
                    />
                )}
            </FilterPill>

            <FilterPill
                label="Categoría"
                value={
                    selectedCategories.length > 0 ? (
                        <CategoryValue selected={selectedCategories} />
                    ) : undefined
                }
                onClear={() => onChange({ category_ids: [] })}
                contentClassName="w-[280px] p-0"
            >
                {() => (
                    <CategoryPicker
                        categories={categories}
                        selectedIds={filters.category_ids}
                        onChange={(next) => onChange({ category_ids: next })}
                    />
                )}
            </FilterPill>

            <DateFilterDropdown
                dateFrom={filters.date_from}
                dateTo={filters.date_to}
                onChange={(next) =>
                    onChange({
                        date_from: next.dateFrom,
                        date_to: next.dateTo,
                    })
                }
            />

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
            <span
                className="bg-secondary text-secondary-foreground inline-flex size-5 shrink-0 items-center justify-center rounded-sm text-[12px] leading-none"
                aria-hidden
            >
                {account.emoji ?? <WalletIcon className="size-3 opacity-70" />}
            </span>
            <span>{account.name}</span>
        </>
    );
}

interface AccountPickerProps {
    accounts: Account[];
    selectedId: string;
    onSelect: (id: string) => void;
}

function AccountPicker({ accounts, selectedId, onSelect }: AccountPickerProps) {
    return (
        <Command>
            <CommandInput placeholder="Buscar cuenta..." />
            <CommandList>
                <CommandEmpty>Sin cuentas</CommandEmpty>
                <CommandGroup>
                    {accounts.map((account) => {
                        const isSelected = String(account.id) === selectedId;
                        return (
                            <CommandItem
                                key={account.id}
                                value={account.name}
                                onSelect={() => onSelect(String(account.id))}
                                className="gap-2.5"
                            >
                                <span
                                    className="bg-secondary text-secondary-foreground inline-flex size-7 shrink-0 items-center justify-center rounded-md text-[15px] leading-none"
                                    aria-hidden
                                >
                                    {account.emoji ?? (
                                        <WalletIcon className="size-3.5 opacity-70" />
                                    )}
                                </span>
                                <div className="flex min-w-0 flex-1 flex-col leading-tight">
                                    <span className="truncate text-sm font-medium">
                                        {account.name}
                                    </span>
                                    <span className="text-muted-foreground font-mono text-[11px] tabular-nums">
                                        {formatCurrency(
                                            account.current_balance ?? 0,
                                            account.currency,
                                            account.currency_locale ?? 'es-CL',
                                        )}
                                    </span>
                                </div>
                                {isSelected && (
                                    <CheckIcon className="text-foreground size-4 shrink-0" />
                                )}
                            </CommandItem>
                        );
                    })}
                </CommandGroup>
            </CommandList>
        </Command>
    );
}

interface BudgetPickerProps {
    budgets: Pick<Budget, 'id' | 'uuid' | 'name'>[];
    selectedId: string;
    onSelect: (id: string) => void;
}

function BudgetPicker({ budgets, selectedId, onSelect }: BudgetPickerProps) {
    return (
        <Command>
            <CommandInput placeholder="Buscar presupuesto..." />
            <CommandList>
                <CommandEmpty>Sin presupuestos</CommandEmpty>
                <CommandGroup>
                    {budgets.map((budget) => {
                        const isSelected = String(budget.id) === selectedId;
                        return (
                            <CommandItem
                                key={budget.id}
                                value={budget.name}
                                onSelect={() => onSelect(String(budget.id))}
                            >
                                <span className="flex-1 truncate">
                                    {budget.name}
                                </span>
                                {isSelected && (
                                    <CheckIcon className="text-foreground size-4 shrink-0" />
                                )}
                            </CommandItem>
                        );
                    })}
                </CommandGroup>
            </CommandList>
        </Command>
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

interface CategoryPickerProps {
    categories: Category[];
    selectedIds: string[];
    onChange: (next: string[]) => void;
}

function CategoryPicker({
    categories,
    selectedIds,
    onChange,
}: CategoryPickerProps) {
    const selected = new Set(selectedIds);

    const toggleParent = (parent: Category) => {
        const parentId = String(parent.id);
        const childIds = parent.children?.map((c) => String(c.id)) ?? [];
        const allChildrenSelected =
            childIds.length > 0 && childIds.every((id) => selected.has(id));
        const isChecked = selected.has(parentId) || allChildrenSelected;

        if (isChecked) {
            const remove = new Set([parentId, ...childIds]);
            onChange(selectedIds.filter((id) => !remove.has(id)));
        } else {
            onChange([...selectedIds.filter((id) => !childIds.includes(id)), parentId]);
        }
    };

    const toggleChild = (parent: Category, childId: string) => {
        const parentId = String(parent.id);
        const siblingIds = (parent.children ?? [])
            .map((c) => String(c.id))
            .filter((id) => id !== childId);

        if (selected.has(parentId)) {
            const next = selectedIds.filter((id) => id !== parentId);
            const merged = new Set([...next, ...siblingIds]);
            onChange([...merged]);
            return;
        }

        if (selected.has(childId)) {
            onChange(selectedIds.filter((id) => id !== childId));
        } else {
            onChange([...selectedIds, childId]);
        }
    };

    return (
        <Command>
            <CommandInput placeholder="Buscar categoría..." />
            <CommandList>
                <CommandEmpty>Sin categorías</CommandEmpty>
                <CommandGroup>
                    {categories.map((parent) => {
                        const parentId = String(parent.id);
                        const childIds =
                            parent.children?.map((c) => String(c.id)) ?? [];
                        const parentSelected = selected.has(parentId);
                        const allChildrenSelected =
                            childIds.length > 0 &&
                            childIds.every((id) => selected.has(id));
                        const parentChecked =
                            parentSelected || allChildrenSelected;

                        return (
                            <Fragment key={parent.id}>
                                <CommandItem
                                    value={`category-${parent.id}`}
                                    keywords={[parent.name]}
                                    onSelect={() => toggleParent(parent)}
                                    className="gap-2"
                                >
                                    <Checkbox
                                        checked={parentChecked}
                                        tabIndex={-1}
                                        aria-hidden
                                        className="pointer-events-none"
                                    />
                                    <CategoryAvatar
                                        color={parent.color}
                                        emoji={parent.emoji}
                                        size="md"
                                    />
                                    <span className="flex-1 truncate">
                                        {parent.name}
                                    </span>
                                </CommandItem>
                                {parent.children?.map((child) => {
                                    const childId = String(child.id);
                                    const childChecked =
                                        parentSelected || selected.has(childId);
                                    return (
                                        <CommandItem
                                            key={child.id}
                                            value={`category-${child.id}`}
                                            keywords={[child.name]}
                                            onSelect={() =>
                                                toggleChild(parent, childId)
                                            }
                                            className="gap-2 pl-6"
                                        >
                                            <Checkbox
                                                checked={childChecked}
                                                tabIndex={-1}
                                                aria-hidden
                                                className="pointer-events-none"
                                            />
                                            <CategoryAvatar
                                                color={child.color}
                                                emoji={child.emoji}
                                                size="md"
                                            />
                                            <span className="flex-1 truncate">
                                                {child.name}
                                            </span>
                                        </CommandItem>
                                    );
                                })}
                            </Fragment>
                        );
                    })}
                </CommandGroup>
            </CommandList>
        </Command>
    );
}

export { ALL as ALL_FILTER };
