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
import { Button } from '@/components/ui/button';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { Account, BreadcrumbItem } from '@/types';

interface BudgetSegment {
    uuid: string;
    name: string;
    color: string;
    emoji: string | null;
    reserved: number;
    overspend: number;
}

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
    reserved: number;
    available: number;
    budgets: BudgetSegment[];
}

interface CurrencySummary {
    currency: string;
    currency_locale: string;
    accounts_count: number;
    total: number;
    reserved_total: number;
    unassigned_reserved: number;
    overspend_total: number;
    available: number;
    accounts: AccountRow[];
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
                        reservado por cada budget.
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

    const showRollup = summary.accounts_count > 1;

    return (
        <section className="flex flex-col gap-3">
            {showRollup ? (
                <CurrencyRollup
                    summary={summary}
                    fmt={fmt}
                    showCurrencyLabel={showCurrencyLabel}
                />
            ) : (
                showCurrencyLabel && (
                    <CurrencyLabel currency={summary.currency} />
                )
            )}

            {summary.accounts.map((account) => (
                <AccountCard
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

function CurrencyLabel({ currency }: { currency: string }) {
    return (
        <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
            {currency}
        </p>
    );
}

function CurrencyRollup({
    summary,
    fmt,
    showCurrencyLabel,
}: {
    summary: CurrencySummary;
    fmt: (n: number) => string;
    showCurrencyLabel: boolean;
}) {
    const negative = summary.available < 0;
    const denom = summary.total > 0 ? summary.total : 0;
    const reservedPct =
        denom > 0 ? Math.min(100, (summary.reserved_total / denom) * 100) : 0;
    const availablePct = denom > 0 ? Math.max(0, 100 - reservedPct) : 0;

    return (
        <div className="bg-card border-border rounded-2xl border px-6 py-5 shadow-sm">
            <div className="flex items-end justify-between gap-3">
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
                            negative
                                ? 'text-red-600 dark:text-red-400'
                                : 'text-foreground',
                        )}
                    >
                        {fmt(summary.available)}
                    </p>
                </div>
                <p className="text-muted-foreground font-mono text-[11px] tabular-nums">
                    saldo{' '}
                    <span className="text-foreground font-semibold">
                        {fmt(summary.total)}
                    </span>
                </p>
            </div>

            <div className="bg-muted mt-4 flex h-2.5 w-full overflow-hidden rounded-full">
                <div
                    className={cn(
                        'h-full',
                        negative
                            ? 'bg-red-500'
                            : 'bg-indigo-400 dark:bg-indigo-500/70',
                    )}
                    style={{ width: `${reservedPct}%` }}
                />
                <div
                    className="h-full bg-emerald-500"
                    style={{ width: `${availablePct}%` }}
                />
            </div>

            <div className="mt-2.5 flex flex-wrap gap-x-5 gap-y-1 font-mono text-[11px]">
                <LegendDot
                    dotClass={
                        negative
                            ? 'bg-red-500'
                            : 'bg-indigo-400 dark:bg-indigo-500/70'
                    }
                    label="reservado"
                    value={fmt(summary.reserved_total)}
                />
                <LegendDot
                    dotClass="bg-emerald-500"
                    label="libre"
                    value={fmt(summary.available)}
                    valueClass={
                        negative ? 'text-red-600 dark:text-red-400' : undefined
                    }
                />
                {summary.unassigned_reserved > 0 && (
                    <span className="text-muted-foreground/70 flex items-center gap-1.5">
                        sin cuenta{' '}
                        <span className="text-muted-foreground font-semibold tabular-nums">
                            {fmt(summary.unassigned_reserved)}
                        </span>
                    </span>
                )}
            </div>
        </div>
    );
}

function AccountCard({
    account,
    fmt,
    onEdit,
    onMakeDefault,
    onDelete,
}: {
    account: AccountRow;
    fmt: (n: number) => string;
} & RowHandlers) {
    const negative = account.available < 0;
    const balance = account.current_balance;
    const reserved = account.reserved;

    // When reserved exceeds the room in the account, segments fill the whole bar
    // proportionally and there is no free slice; "available" goes red instead.
    const overflow = reserved > Math.max(balance, 0);
    const base = overflow ? reserved : balance > 0 ? balance : 0;

    const segments = account.budgets
        .filter((budget) => budget.reserved > 0)
        .map((budget) => ({
            ...budget,
            width: base > 0 ? (budget.reserved / base) * 100 : 0,
        }));
    const librePct =
        !overflow && balance > 0
            ? Math.max(0, ((balance - reserved) / balance) * 100)
            : 0;

    return (
        <div className="bg-card border-border overflow-hidden rounded-2xl border shadow-sm">
            <div
                className="border-border flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4"
                style={{ borderLeft: `3px solid ${account.color}` }}
            >
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
                        <p className="text-muted-foreground font-mono text-[11px] tabular-nums">
                            saldo {fmt(balance)}
                        </p>
                    </div>
                </div>
                <div className="flex items-start gap-1">
                    <div className="text-right">
                        <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                            Disponible
                        </p>
                        <p
                            className={cn(
                                'font-mono text-xl font-bold tabular-nums',
                                negative
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

            <div className="px-5 pt-4 pb-5">
                <TooltipProvider delayDuration={100}>
                    <div className="bg-muted flex h-2.5 w-full overflow-hidden rounded-full">
                        {segments.map((segment) => (
                            <Tooltip key={segment.uuid}>
                                <TooltipTrigger asChild>
                                    <div
                                        className="h-full"
                                        style={{
                                            width: `${segment.width}%`,
                                            backgroundColor: segment.color,
                                        }}
                                    />
                                </TooltipTrigger>
                                <TooltipContent>
                                    <span className="flex items-center gap-1.5 font-mono text-[11px]">
                                        {segment.emoji ?? '🎯'}
                                        <span className="font-semibold">
                                            {segment.name}
                                        </span>
                                        <span className="tabular-nums">
                                            {fmt(segment.reserved)}
                                        </span>
                                        {segment.overspend > 0 && (
                                            <span className="tabular-nums text-red-400">
                                                +{fmt(segment.overspend)}
                                            </span>
                                        )}
                                    </span>
                                </TooltipContent>
                            </Tooltip>
                        ))}
                        {librePct > 0 && (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <div
                                        className="h-full bg-emerald-500"
                                        style={{ width: `${librePct}%` }}
                                    />
                                </TooltipTrigger>
                                <TooltipContent>
                                    <span className="flex items-center gap-1.5 font-mono text-[11px]">
                                        <span className="font-semibold">
                                            libre
                                        </span>
                                        <span className="tabular-nums">
                                            {fmt(account.available)}
                                        </span>
                                    </span>
                                </TooltipContent>
                            </Tooltip>
                        )}
                    </div>
                </TooltipProvider>

                <div className="mt-2.5 flex flex-wrap gap-x-5 gap-y-1.5 font-mono text-[11px]">
                    {account.budgets.map((budget) => (
                        <span
                            key={budget.uuid}
                            className="text-muted-foreground flex items-center gap-1.5"
                        >
                            <span
                                className="size-2 rounded-full"
                                style={{ backgroundColor: budget.color }}
                            />
                            {budget.emoji ?? '🎯'} {budget.name}{' '}
                            <span className="text-foreground font-semibold tabular-nums">
                                {fmt(budget.reserved)}
                            </span>
                            {budget.overspend > 0 && (
                                <span className="font-semibold tabular-nums text-red-600 dark:text-red-400">
                                    +{fmt(budget.overspend)}
                                </span>
                            )}
                        </span>
                    ))}
                    <span className="text-muted-foreground flex items-center gap-1.5">
                        <span className="size-2 rounded-full bg-emerald-500" />
                        libre{' '}
                        <span
                            className={cn(
                                'text-foreground font-semibold tabular-nums',
                                negative && 'text-red-600 dark:text-red-400',
                            )}
                        >
                            {fmt(account.available)}
                        </span>
                    </span>
                </div>
            </div>
        </div>
    );
}

function LegendDot({
    dotClass,
    label,
    value,
    valueClass,
}: {
    dotClass: string;
    label: string;
    value: string;
    valueClass?: string;
}) {
    return (
        <span className="text-muted-foreground flex items-center gap-1.5">
            <span className={cn('size-2 rounded-full', dotClass)} />
            {label}{' '}
            <span
                className={cn(
                    'text-foreground font-semibold tabular-nums',
                    valueClass,
                )}
            >
                {value}
            </span>
        </span>
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
