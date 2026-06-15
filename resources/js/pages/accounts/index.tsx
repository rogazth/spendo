import { Head, router } from '@inertiajs/react';
import {
    MoreHorizontalIcon,
    PencilIcon,
    StarIcon,
    Trash2Icon,
    WalletIcon,
} from 'lucide-react';
import { useState } from 'react';
import { AccountFormDialog } from '@/components/forms/account-form-dialog';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { Account, BreadcrumbItem } from '@/types';

interface AccountRow {
    id: number;
    uuid: string;
    name: string;
    currency: string;
    currency_locale: string;
    current_balance: number;
    color: string;
    emoji: string | null;
    is_active: boolean;
    is_default: boolean;
    available: number;
}

interface BudgetGroup {
    budget: {
        uuid: string;
        name: string;
        color: string;
        emoji: string | null;
    };
    budgeted: number;
    spent: number;
    reserved: number;
    overspend: number;
    percentage: number;
    total: number;
    available: number;
    accounts: AccountRow[];
}

interface CurrencySummary {
    currency: string;
    currency_locale: string;
    accounts_count: number;
    total: number;
    budgeted_total: number;
    reserved_total: number;
    available: number;
    budget_groups: BudgetGroup[];
    unbudgeted_accounts: AccountRow[];
}

interface Totals {
    accounts: number;
    budgeted: number;
    currencies: number;
    default_name: string | null;
}

interface Props {
    currencySummaries: CurrencySummary[];
    totals: Totals;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cuentas', href: '/accounts' },
];

export default function AccountsIndex({ currencySummaries }: Props) {
    const [editingAccount, setEditingAccount] = useState<Account | null>(null);
    const [deletingAccount, setDeletingAccount] = useState<AccountRow | null>(
        null,
    );

    const handleEdit = (row: AccountRow) => {
        setEditingAccount({
            id: row.id,
            uuid: row.uuid,
            name: row.name,
            currency: row.currency,
            currency_locale: row.currency_locale,
            current_balance: row.current_balance,
            color: row.color,
            emoji: row.emoji,
            is_active: row.is_active,
            is_default: row.is_default,
            user_id: 0,
            created_at: '',
            updated_at: '',
        });
    };

    const handleMakeDefault = (row: AccountRow) => {
        router.patch(`/accounts/${row.uuid}/make-default`, undefined, {
            preserveScroll: true,
        });
    };

    const handleDeleteConfirm = () => {
        if (!deletingAccount) return;
        router.delete(`/accounts/${deletingAccount.uuid}`, {
            preserveScroll: true,
            onSuccess: () => setDeletingAccount(null),
        });
    };

    const isEmpty = currencySummaries.length === 0;
    const multiCurrency = currencySummaries.length > 1;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cuentas" />

            <div className="flex flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div>
                    <h1 className="text-foreground text-2xl font-bold tracking-tight">
                        Cuentas
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Lo que te queda disponible por cuenta, después de lo
                        presupuestado.
                    </p>
                </div>

                {isEmpty ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <WalletIcon />
                            </EmptyMedia>
                            <EmptyTitle>
                                No tienes cuentas registradas
                            </EmptyTitle>
                            <EmptyDescription>
                                Las cuentas se crean mediante el asistente de IA.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    currencySummaries.map((summary) => (
                        <CurrencyBlock
                            key={summary.currency}
                            summary={summary}
                            showCurrencyLabel={multiCurrency}
                            onEdit={handleEdit}
                            onMakeDefault={handleMakeDefault}
                            onDelete={setDeletingAccount}
                        />
                    ))
                )}
            </div>

            <AccountFormDialog
                open={editingAccount !== null}
                onOpenChange={(open) => {
                    if (!open) setEditingAccount(null);
                }}
                account={editingAccount ?? undefined}
            />

            <AlertDialog
                open={deletingAccount !== null}
                onOpenChange={(open) => !open && setDeletingAccount(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>¿Eliminar cuenta?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Se eliminará{' '}
                            <span className="font-semibold">
                                {deletingAccount?.name}
                            </span>
                            . Esta acción no se puede deshacer.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDeleteConfirm}>
                            Eliminar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}

interface RowHandlers {
    onEdit: (row: AccountRow) => void;
    onMakeDefault: (row: AccountRow) => void;
    onDelete: (row: AccountRow) => void;
}

function CurrencyBlock({
    summary,
    showCurrencyLabel,
    onEdit,
    onMakeDefault,
    onDelete,
}: {
    summary: CurrencySummary;
    showCurrencyLabel: boolean;
} & RowHandlers) {
    const fmt = (n: number) =>
        formatCurrency(n, summary.currency, summary.currency_locale);

    return (
        <section className="flex flex-col gap-3">
            <AvailableHero
                summary={summary}
                fmt={fmt}
                showCurrencyLabel={showCurrencyLabel}
            />

            {summary.budget_groups.map((group) => (
                <BudgetGroupCard
                    key={group.budget.uuid}
                    group={group}
                    fmt={fmt}
                    onEdit={onEdit}
                    onMakeDefault={onMakeDefault}
                    onDelete={onDelete}
                />
            ))}

            {summary.unbudgeted_accounts.map((account) => (
                <UnbudgetedAccountCard
                    key={account.uuid}
                    account={account}
                    fmt={fmt}
                    onEdit={onEdit}
                    onMakeDefault={onMakeDefault}
                    onDelete={onDelete}
                />
            ))}
        </section>
    );
}

function AvailableHero({
    summary,
    fmt,
    showCurrencyLabel,
}: {
    summary: CurrencySummary;
    fmt: (n: number) => string;
    showCurrencyLabel: boolean;
}) {
    return (
        <div className="bg-card border-border rounded-2xl border px-6 py-5 shadow-sm">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="text-muted-foreground flex items-center gap-2 font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                        Disponible
                        {showCurrencyLabel && (
                            <span className="text-foreground bg-muted rounded px-1.5 py-0.5">
                                {summary.currency}
                            </span>
                        )}
                    </p>
                    <p
                        className={cn(
                            'mt-1 font-mono text-3xl font-bold tabular-nums',
                            summary.available < 0
                                ? 'text-red-600 dark:text-red-400'
                                : 'text-foreground',
                        )}
                    >
                        {fmt(summary.available)}
                    </p>
                </div>
                <div className="text-muted-foreground flex gap-5 font-mono text-[11px]">
                    <span>
                        saldo{' '}
                        <span className="text-foreground font-semibold tabular-nums">
                            {fmt(summary.total)}
                        </span>
                    </span>
                    <span>
                        presupuestado{' '}
                        <span className="text-foreground font-semibold tabular-nums">
                            {fmt(summary.budgeted_total)}
                        </span>
                    </span>
                </div>
            </div>
        </div>
    );
}

function BudgetGroupCard({
    group,
    fmt,
    onEdit,
    onMakeDefault,
    onDelete,
}: {
    group: BudgetGroup;
    fmt: (n: number) => string;
} & RowHandlers) {
    const overspent = group.overspend > 0;

    return (
        <div className="bg-card border-border overflow-hidden rounded-2xl border shadow-sm">
            <div
                className="border-border flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4"
                style={{
                    borderLeft: `3px solid ${group.budget.color}`,
                }}
            >
                <div className="flex items-center gap-2.5">
                    <span
                        className="flex size-8 flex-shrink-0 items-center justify-center rounded-lg border text-sm"
                        style={{
                            backgroundColor: group.budget.color + '20',
                            borderColor: group.budget.color,
                        }}
                    >
                        {group.budget.emoji ?? '🎯'}
                    </span>
                    <div>
                        <p className="text-foreground text-sm font-semibold">
                            {group.budget.name}
                        </p>
                        <p className="text-muted-foreground font-mono text-[11px] tabular-nums">
                            saldo {fmt(group.total)} · presup.{' '}
                            {fmt(group.budgeted)}
                        </p>
                    </div>
                </div>
                <div className="text-right">
                    <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                        Disponible
                    </p>
                    <p
                        className={cn(
                            'font-mono text-xl font-bold tabular-nums',
                            group.available < 0
                                ? 'text-red-600 dark:text-red-400'
                                : 'text-emerald-700 dark:text-emerald-300',
                        )}
                    >
                        {fmt(group.available)}
                    </p>
                </div>
            </div>

            <div className="px-5 pt-3">
                <div className="mb-1 flex items-center justify-between font-mono text-[10px]">
                    <span className="text-muted-foreground tracking-wider uppercase">
                        gastado {fmt(group.spent)}
                    </span>
                    <span
                        className={cn(
                            'tabular-nums',
                            overspent
                                ? 'font-semibold text-red-600 dark:text-red-400'
                                : 'text-muted-foreground',
                        )}
                    >
                        {overspent
                            ? `+${fmt(group.overspend)} sobre el cap`
                            : `${group.percentage}%`}
                    </span>
                </div>
                <div className="bg-muted relative h-1.5 w-full overflow-hidden rounded-full">
                    <div
                        className={cn(
                            'h-full rounded-full',
                            overspent ? 'bg-red-500' : 'bg-emerald-500',
                        )}
                        style={{ width: `${Math.min(100, group.percentage)}%` }}
                    />
                </div>
            </div>

            <div className="divide-border mt-2 divide-y">
                {group.accounts.map((account) => (
                    <AccountLine
                        key={account.uuid}
                        account={account}
                        fmt={fmt}
                        onEdit={onEdit}
                        onMakeDefault={onMakeDefault}
                        onDelete={onDelete}
                    />
                ))}
            </div>
        </div>
    );
}

function UnbudgetedAccountCard({
    account,
    fmt,
    onEdit,
    onMakeDefault,
    onDelete,
}: {
    account: AccountRow;
    fmt: (n: number) => string;
} & RowHandlers) {
    return (
        <div className="bg-card border-border flex items-center justify-between gap-3 rounded-2xl border px-5 py-4 shadow-sm">
            <div className="flex items-center gap-2.5">
                <span
                    className="flex size-8 flex-shrink-0 items-center justify-center rounded-lg border text-sm"
                    style={{
                        backgroundColor: account.color + '20',
                        borderColor: account.color,
                    }}
                >
                    {account.emoji ?? '💳'}
                </span>
                <div>
                    <div className="flex items-center gap-2">
                        <p className="text-foreground text-sm font-semibold">
                            {account.name}
                        </p>
                        {account.is_default && <DefaultBadge />}
                        {!account.is_active && <InactiveBadge />}
                    </div>
                    <p className="text-muted-foreground font-mono text-[11px]">
                        sin budget · libre
                    </p>
                </div>
            </div>
            <div className="flex items-center gap-2">
                <div className="text-right">
                    <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                        Disponible
                    </p>
                    <p
                        className={cn(
                            'font-mono text-xl font-bold tabular-nums',
                            account.available < 0
                                ? 'text-red-600 dark:text-red-400'
                                : 'text-foreground',
                        )}
                    >
                        {fmt(account.available)}
                    </p>
                </div>
                <AccountActions
                    account={account}
                    onEdit={onEdit}
                    onMakeDefault={onMakeDefault}
                    onDelete={onDelete}
                />
            </div>
        </div>
    );
}

function AccountLine({
    account,
    fmt,
    onEdit,
    onMakeDefault,
    onDelete,
}: {
    account: AccountRow;
    fmt: (n: number) => string;
} & RowHandlers) {
    return (
        <div
            className={cn(
                'hover:bg-muted/40 flex items-center justify-between gap-3 px-5 py-2.5 transition-colors',
                !account.is_active && 'opacity-60',
            )}
        >
            <div className="flex items-center gap-2.5">
                <span
                    className="flex size-6 flex-shrink-0 items-center justify-center rounded-md border text-[11px]"
                    style={{
                        backgroundColor: account.color + '20',
                        borderColor: account.color,
                    }}
                >
                    {account.emoji ?? ''}
                </span>
                <span className="text-foreground text-sm font-medium">
                    {account.name}
                </span>
                {account.is_default && <DefaultBadge />}
                {!account.is_active && <InactiveBadge />}
            </div>
            <div className="flex items-center gap-2">
                <span
                    className={cn(
                        'font-mono text-sm font-semibold tabular-nums',
                        account.current_balance < 0
                            ? 'text-red-600 dark:text-red-400'
                            : 'text-foreground',
                    )}
                >
                    {fmt(account.current_balance)}
                </span>
                <AccountActions
                    account={account}
                    onEdit={onEdit}
                    onMakeDefault={onMakeDefault}
                    onDelete={onDelete}
                />
            </div>
        </div>
    );
}

function AccountActions({
    account,
    onEdit,
    onMakeDefault,
    onDelete,
}: {
    account: AccountRow;
} & RowHandlers) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="size-7">
                    <MoreHorizontalIcon className="size-3.5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {!account.is_default && (
                    <DropdownMenuItem onClick={() => onMakeDefault(account)}>
                        <StarIcon />
                        Marcar por defecto
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem onClick={() => onEdit(account)}>
                    <PencilIcon />
                    Editar
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => onDelete(account)}>
                    <Trash2Icon />
                    Eliminar
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function DefaultBadge() {
    return (
        <span
            className="rounded bg-amber-50 px-1.5 py-0.5 font-mono text-[10px] font-bold tracking-wider text-amber-700 uppercase dark:bg-amber-950/50 dark:text-amber-300"
            title="Cuenta por defecto"
        >
            default
        </span>
    );
}

function InactiveBadge() {
    return (
        <span className="text-muted-foreground bg-muted rounded px-1.5 py-0.5 font-mono text-[10px] font-bold tracking-wider uppercase">
            inactive
        </span>
    );
}
