import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2Icon,
    EyeOffIcon,
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
    include_in_budget: boolean;
}

interface CurrencySummary {
    currency: string;
    currency_locale: string;
    accounts_count: number;
    active_count: number;
    inactive_count: number;
    included_count: number;
    excluded_count: number;
    negative_count: number;
    total: number;
    budgeted_total: number;
    excluded_total: number;
    accounts: AccountRow[];
}

interface Totals {
    accounts: number;
    active: number;
    inactive: number;
    included: number;
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

type SectionStatus = 'ok' | 'warn' | 'incident';

function sectionStatus(s: CurrencySummary): SectionStatus {
    if (s.negative_count > 0) return 'incident';
    if (s.inactive_count > 0 || s.excluded_count > 0) return 'warn';
    return 'ok';
}

type RowStatus = 'ok' | 'idle' | 'excluded' | 'negative' | 'inactive';

function rowStatus(a: AccountRow): RowStatus {
    if (!a.is_active) return 'inactive';
    if (a.current_balance < 0) return 'negative';
    if (!a.include_in_budget) return 'excluded';
    if (a.current_balance === 0) return 'idle';
    return 'ok';
}

const ROW_STATUS_DOT: Record<RowStatus, string> = {
    ok: 'bg-emerald-500',
    idle: 'bg-sky-500',
    excluded: 'bg-slate-400',
    negative: 'bg-red-500',
    inactive: 'bg-muted-foreground/40',
};

const ROW_STATUS_LABEL: Record<RowStatus, string> = {
    ok: 'ok',
    idle: 'idle',
    excluded: 'excluded',
    negative: 'negative',
    inactive: 'inactive',
};

export default function AccountsIndex({ currencySummaries, totals }: Props) {
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cuentas" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-foreground text-2xl font-bold tracking-tight">
                        Cuentas
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Status board · balances por moneda.
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
                    <>
                        <GlobalKpiStrip totals={totals} />

                        {currencySummaries.map((summary) => (
                            <CurrencySection
                                key={summary.currency}
                                summary={summary}
                                onEdit={handleEdit}
                                onMakeDefault={handleMakeDefault}
                                onDelete={setDeletingAccount}
                            />
                        ))}
                    </>
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

function GlobalKpiStrip({ totals }: { totals: Totals }) {
    return (
        <div className="bg-card border-border grid grid-cols-2 overflow-hidden rounded-2xl border shadow-sm md:grid-cols-4">
            <KpiCell
                label="ACCOUNTS"
                value={String(totals.accounts)}
                note={`${totals.active} activas · ${totals.inactive} inactivas`}
            />
            <KpiCell
                label="INCLUDED"
                value={String(totals.included)}
                note="suman al budget"
            />
            <KpiCell
                label="CURRENCIES"
                value={String(totals.currencies)}
                note="monedas distintas"
            />
            <KpiCell
                label="DEFAULT"
                value={totals.default_name ?? '—'}
                note={totals.default_name ? 'cuenta por defecto' : 'sin asignar'}
                small
            />
        </div>
    );
}

function KpiCell({
    label,
    value,
    note,
    small,
}: {
    label: string;
    value: string;
    note: string;
    small?: boolean;
}) {
    return (
        <div className="border-border flex flex-col gap-1.5 border-r border-b px-5 py-4 last:border-r-0 md:border-b-0">
            <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                {label}
            </p>
            <p
                className={cn(
                    'text-foreground font-mono font-bold tabular-nums',
                    small ? 'truncate text-base' : 'text-2xl',
                )}
                title={value}
            >
                {value}
            </p>
            <p className="text-muted-foreground truncate text-[11px]">{note}</p>
        </div>
    );
}

function CurrencySection({
    summary,
    onEdit,
    onMakeDefault,
    onDelete,
}: {
    summary: CurrencySummary;
    onEdit: (row: AccountRow) => void;
    onMakeDefault: (row: AccountRow) => void;
    onDelete: (row: AccountRow) => void;
}) {
    const fmt = (n: number) =>
        formatCurrency(n, summary.currency, summary.currency_locale);
    const status = sectionStatus(summary);

    const statusBg =
        status === 'ok'
            ? 'bg-emerald-500'
            : status === 'warn'
              ? 'bg-amber-500'
              : 'bg-red-500';

    const statusLabel =
        status === 'ok'
            ? 'ALL OK'
            : status === 'incident'
              ? `${summary.negative_count} NEGATIVE`
              : `${summary.inactive_count + summary.excluded_count} WARNING${
                    summary.inactive_count + summary.excluded_count !== 1
                        ? 'S'
                        : ''
                }`;

    const reservedPct =
        summary.total > 0
            ? Math.round((summary.budgeted_total / summary.total) * 100)
            : 0;

    return (
        <section className="bg-card border-border overflow-hidden rounded-2xl border shadow-sm">
            <div className="border-border flex flex-wrap items-center justify-between gap-3 border-b px-6 py-3">
                <div className="flex items-center gap-3">
                    <span className="text-foreground font-mono text-xs font-semibold tracking-[0.18em] uppercase">
                        {summary.currency}
                    </span>
                    <span className="text-muted-foreground text-[11px]">
                        Status board · balances
                    </span>
                </div>
                <div className="flex items-center gap-4">
                    <div className="flex items-center gap-2">
                        <span
                            className={cn('size-2 rounded-full', statusBg)}
                            aria-hidden
                        />
                        <span
                            className={cn(
                                'font-mono text-[11px] font-bold tracking-wider uppercase',
                                status === 'ok' &&
                                    'text-emerald-700 dark:text-emerald-300',
                                status === 'warn' &&
                                    'text-amber-700 dark:text-amber-300',
                                status === 'incident' &&
                                    'text-red-700 dark:text-red-300',
                            )}
                        >
                            {statusLabel}
                        </span>
                    </div>
                    <div className="text-muted-foreground flex items-center gap-2 text-[11px]">
                        <span className="text-foreground font-mono font-semibold tabular-nums">
                            {summary.accounts_count}
                        </span>
                        <span>
                            {summary.accounts_count === 1
                                ? 'account'
                                : 'accounts'}
                        </span>
                    </div>
                </div>
            </div>

            <div className="bg-border grid grid-cols-1 gap-px lg:grid-cols-2">
                <div className="bg-card divide-border divide-y">
                    <KpiRow
                        label="TOTAL"
                        value={fmt(summary.total)}
                        emphasize
                        note={`${summary.accounts_count} ${summary.accounts_count === 1 ? 'cuenta' : 'cuentas'}`}
                    />
                    <KpiRow
                        label="BUDGETED"
                        value={fmt(summary.budgeted_total)}
                        note={`${reservedPct}% del total · ${summary.included_count} incluidas`}
                    />
                </div>
                <div className="bg-card divide-border divide-y">
                    <KpiRow
                        label="EXCLUDED"
                        value={fmt(summary.excluded_total)}
                        note={
                            summary.excluded_count > 0
                                ? `${summary.excluded_count} fuera del budget`
                                : 'todas incluidas'
                        }
                    />
                    <KpiRow
                        label="NEGATIVE"
                        value={
                            summary.negative_count > 0
                                ? `${summary.negative_count}`
                                : '0'
                        }
                        tone={summary.negative_count > 0 ? 'danger' : 'neutral'}
                        note={
                            summary.negative_count > 0
                                ? 'cuentas con saldo < 0'
                                : 'sin saldos negativos'
                        }
                    />
                </div>
            </div>

            {summary.negative_count > 0 && (
                <IncidentsRow summary={summary} fmt={fmt} />
            )}

            <AccountsTable
                summary={summary}
                onEdit={onEdit}
                onMakeDefault={onMakeDefault}
                onDelete={onDelete}
                fmt={fmt}
            />
        </section>
    );
}

function KpiRow({
    label,
    value,
    note,
    tone = 'neutral',
    emphasize,
}: {
    label: string;
    value: string;
    note: string;
    tone?: 'neutral' | 'success' | 'danger';
    emphasize?: boolean;
}) {
    const valueClass =
        tone === 'success'
            ? 'text-emerald-700 dark:text-emerald-300'
            : tone === 'danger'
              ? 'text-red-600 dark:text-red-400'
              : 'text-foreground';
    return (
        <div className="grid grid-cols-[120px_1fr] items-center gap-4 px-6 py-3.5">
            <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                {label}
            </p>
            <div className="flex items-baseline justify-between gap-3">
                <p
                    className={cn(
                        'font-mono font-bold tabular-nums',
                        emphasize ? 'text-2xl' : 'text-lg',
                        valueClass,
                    )}
                >
                    {value}
                </p>
                <p className="text-muted-foreground truncate text-right text-[11px]">
                    {note}
                </p>
            </div>
        </div>
    );
}

function IncidentsRow({
    summary,
    fmt,
}: {
    summary: CurrencySummary;
    fmt: (n: number) => string;
}) {
    const negatives = summary.accounts.filter((a) => a.current_balance < 0);
    return (
        <div className="border-border border-t px-6 py-4">
            <div className="mb-2 flex items-center justify-between">
                <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                    Incidents
                </p>
                <span className="text-muted-foreground text-[10px]">
                    Cuentas con saldo negativo
                </span>
            </div>
            <ul className="divide-border divide-y">
                {negatives.map((a) => (
                    <li
                        key={a.uuid}
                        className="grid grid-cols-[24px_1fr_auto_auto] items-center gap-3 py-2"
                    >
                        <span
                            className="size-4 rounded-sm border"
                            style={{
                                backgroundColor: a.color + '20',
                                borderColor: a.color,
                            }}
                        />
                        <div>
                            <p className="text-foreground text-sm font-medium">
                                {a.name}
                            </p>
                            <p className="text-muted-foreground text-[11px]">
                                saldo bajo cero
                            </p>
                        </div>
                        <span className="rounded bg-red-50 px-1.5 py-0.5 font-mono text-[10px] font-bold tracking-wider text-red-700 uppercase dark:bg-red-950/50 dark:text-red-300">
                            NEG
                        </span>
                        <span className="font-mono text-sm font-semibold tabular-nums text-red-600 dark:text-red-400">
                            {fmt(a.current_balance)}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function AccountsTable({
    summary,
    onEdit,
    onMakeDefault,
    onDelete,
    fmt,
}: {
    summary: CurrencySummary;
    onEdit: (row: AccountRow) => void;
    onMakeDefault: (row: AccountRow) => void;
    onDelete: (row: AccountRow) => void;
    fmt: (n: number) => string;
}) {
    const denom = Math.max(
        Math.abs(summary.accounts.reduce((acc, a) => acc + Math.abs(a.current_balance), 0)),
        1,
    );

    return (
        <div className="border-border border-t">
            <div className="flex items-center justify-between px-6 pt-4 pb-2">
                <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                    Accounts
                </p>
                <span className="text-muted-foreground text-[10px]">
                    share = peso del saldo absoluto · status según balance e inclusión
                </span>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-xs">
                    <thead>
                        <tr className="border-border text-muted-foreground border-b font-mono text-[10px] tracking-wider uppercase">
                            <th className="px-6 py-2 text-left font-semibold">
                                name
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                balance
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                share
                            </th>
                            <th className="px-3 py-2 text-center font-semibold">
                                in budget
                            </th>
                            <th className="px-3 py-2 text-center font-semibold">
                                status
                            </th>
                            <th className="w-10 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-border divide-y">
                        {summary.accounts.map((a) => {
                            const st = rowStatus(a);
                            const share =
                                denom > 0
                                    ? Math.round(
                                          (Math.abs(a.current_balance) / denom) *
                                              100,
                                      )
                                    : 0;
                            return (
                                <tr
                                    key={a.uuid}
                                    className={cn(
                                        'hover:bg-muted/40 transition-colors',
                                        !a.is_active && 'opacity-60',
                                    )}
                                >
                                    <td className="px-6 py-2.5">
                                        <div className="flex items-center gap-2.5">
                                            <span
                                                className="flex size-6 flex-shrink-0 items-center justify-center rounded-md border text-[11px]"
                                                style={{
                                                    backgroundColor:
                                                        a.color + '20',
                                                    borderColor: a.color,
                                                }}
                                            >
                                                {a.emoji ?? ''}
                                            </span>
                                            <span className="text-foreground font-medium">
                                                {a.name}
                                            </span>
                                            {a.is_default && (
                                                <span
                                                    className="rounded bg-amber-50 px-1.5 py-0.5 font-mono text-[10px] font-bold tracking-wider text-amber-700 uppercase dark:bg-amber-950/50 dark:text-amber-300"
                                                    title="Cuenta por defecto"
                                                >
                                                    default
                                                </span>
                                            )}
                                            {!a.is_active && (
                                                <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                                    inactive
                                                </span>
                                            )}
                                            {!a.include_in_budget &&
                                                a.is_active && (
                                                    <span
                                                        className="text-muted-foreground inline-flex items-center gap-1 font-mono text-[10px] tracking-wider uppercase"
                                                        title="Excluida del budget"
                                                    >
                                                        <EyeOffIcon className="size-2.5" />
                                                        excluded
                                                    </span>
                                                )}
                                        </div>
                                    </td>
                                    <td
                                        className={cn(
                                            'px-3 py-2.5 text-right font-mono font-semibold tabular-nums',
                                            a.current_balance < 0
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-foreground',
                                        )}
                                    >
                                        {fmt(a.current_balance)}
                                    </td>
                                    <td className="text-muted-foreground w-32 px-3 py-2.5 text-right font-mono tabular-nums">
                                        <div className="flex items-center justify-end gap-2">
                                            <div className="bg-muted relative h-1.5 w-16 overflow-hidden rounded-full">
                                                <div
                                                    className={cn(
                                                        'h-full rounded-full',
                                                        a.current_balance < 0
                                                            ? 'bg-red-500/60'
                                                            : 'bg-foreground/40',
                                                    )}
                                                    style={{
                                                        width: `${share}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="w-8 text-right">
                                                {share}%
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-3 py-2.5 text-center">
                                        {a.include_in_budget ? (
                                            <CheckCircle2Icon className="text-muted-foreground mx-auto size-3.5" />
                                        ) : (
                                            <span className="text-muted-foreground font-mono text-[10px]">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <div className="flex items-center justify-center gap-1.5">
                                            <span
                                                className={cn(
                                                    'size-1.5 rounded-full',
                                                    ROW_STATUS_DOT[st],
                                                )}
                                            />
                                            <span className="text-muted-foreground font-mono text-[10px] uppercase">
                                                {ROW_STATUS_LABEL[st]}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-3 py-2.5 text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-7"
                                                >
                                                    <MoreHorizontalIcon className="size-3.5" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                {!a.is_default && (
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            onMakeDefault(a)
                                                        }
                                                    >
                                                        <StarIcon />
                                                        Marcar por defecto
                                                    </DropdownMenuItem>
                                                )}
                                                <DropdownMenuItem
                                                    onClick={() => onEdit(a)}
                                                >
                                                    <PencilIcon />
                                                    Editar
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onClick={() => onDelete(a)}
                                                >
                                                    <Trash2Icon />
                                                    Eliminar
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
