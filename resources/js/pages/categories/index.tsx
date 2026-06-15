import { Head, router } from '@inertiajs/react';
import {
    ChevronDownIcon,
    ChevronRightIcon,
    MoreHorizontalIcon,
    PencilIcon,
    PlusIcon,
    TagIcon,
    Trash2Icon,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { CategoryFormDialog } from '@/components/forms/category-form-dialog';
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
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, Category } from '@/types';

interface CategoryRow {
    id: number;
    uuid: string;
    parent_id: number | null;
    name: string;
    color: string;
    emoji: string | null;
    transaction_count: number;
}

interface ParentCategoryRow extends CategoryRow {
    children: CategoryRow[];
}

interface Totals {
    categories: number;
    in_use: number;
    idle: number;
}

interface Period {
    start: string;
}

interface ParentOption {
    id: number;
    uuid: string;
    name: string;
    color: string;
}

interface Props {
    categories: ParentCategoryRow[];
    parentCategories: ParentOption[];
    totals: Totals;
    period: Period;
}

function rowToCategory(row: CategoryRow): Category {
    return {
        id: row.id,
        uuid: row.uuid,
        parent_id: row.parent_id,
        user_id: 0,
        name: row.name,
        emoji: row.emoji,
        color: row.color,
        created_at: '',
        updated_at: '',
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Categorías', href: '/categories' },
];

function fmtMonthLabel(period: Period): string {
    const date = new Date(`${period.start}T00:00:00`);
    return date.toLocaleDateString('es-CL', {
        month: 'long',
        year: 'numeric',
    });
}

export default function CategoriesIndex({
    categories,
    parentCategories,
    totals,
    period,
}: Props) {
    const isEmpty = categories.length === 0;
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Category | undefined>();
    const [deleting, setDeleting] = useState<CategoryRow | null>(null);
    const [deletePending, setDeletePending] = useState(false);

    const parentOptions: Category[] = parentCategories.map((p) => ({
        id: p.id,
        uuid: p.uuid,
        parent_id: null,
        user_id: 0,
        name: p.name,
        emoji: null,
        color: p.color,
        created_at: '',
        updated_at: '',
    }));

    const openCreate = () => {
        setEditing(undefined);
        setFormOpen(true);
    };

    const openEdit = (row: CategoryRow) => {
        setEditing(rowToCategory(row));
        setFormOpen(true);
    };

    const confirmDelete = () => {
        if (!deleting) return;
        setDeletePending(true);
        router.delete(`/categories/${deleting.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Categoría eliminada');
                setDeleting(null);
            },
            onError: () => toast.error('Error al eliminar la categoría'),
            onFinish: () => setDeletePending(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categorías" />

            <div className="flex flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-foreground text-2xl font-bold tracking-tight">
                            Categorías
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Jerarquía de categorías · movimientos del mes en
                            curso ·{' '}
                            <span className="font-mono">
                                {fmtMonthLabel(period)}
                            </span>
                        </p>
                    </div>
                    <Button onClick={openCreate} size="sm">
                        <PlusIcon className="size-4" />
                        Nueva categoría
                    </Button>
                </div>

                {isEmpty ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <TagIcon />
                            </EmptyMedia>
                            <EmptyTitle>
                                Aún no tienes categorías
                            </EmptyTitle>
                            <EmptyDescription>
                                Crea tu primera categoría para empezar a
                                organizar tus movimientos.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <>
                        <GlobalKpiStrip totals={totals} />
                        <CategoriesBoard
                            categories={categories}
                            totals={totals}
                            onEdit={openEdit}
                            onDelete={(row) => setDeleting(row)}
                        />
                    </>
                )}
            </div>

            <CategoryFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                category={editing}
                parentCategories={parentOptions}
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleting(null);
                }}
                title="Eliminar categoría"
                description={
                    deleting ? (
                        <>
                            Vas a eliminar{' '}
                            <span className="font-semibold">
                                {deleting.name}
                            </span>
                            . Las subcategorías quedarán sin padre y las
                            transacciones existentes mantendrán la referencia
                            (puedes recuperarla más tarde).
                        </>
                    ) : (
                        ''
                    )
                }
                confirmLabel="Eliminar"
                variant="destructive"
                onConfirm={confirmDelete}
                loading={deletePending}
            />
        </AppLayout>
    );
}

function GlobalKpiStrip({ totals }: { totals: Totals }) {
    return (
        <div className="bg-card border-border grid grid-cols-2 overflow-hidden rounded-2xl border shadow-sm md:grid-cols-3">
            <KpiCell
                label="CATEGORIES"
                value={String(totals.categories)}
                note="top-level"
            />
            <KpiCell
                label="IN USE"
                value={String(totals.in_use)}
                note="con movimiento este mes"
            />
            <KpiCell
                label="IDLE"
                value={String(totals.idle)}
                note="sin movimiento este mes"
                tone={totals.idle > 0 ? 'muted' : 'neutral'}
            />
        </div>
    );
}

function KpiCell({
    label,
    value,
    note,
    tone = 'neutral',
}: {
    label: string;
    value: string;
    note: string;
    tone?: 'neutral' | 'muted';
}) {
    return (
        <div className="border-border flex flex-col gap-1.5 border-r border-b px-5 py-4 last:border-r-0 md:border-b-0">
            <p className="text-muted-foreground font-mono text-[10px] font-semibold tracking-[0.18em] uppercase">
                {label}
            </p>
            <p
                className={cn(
                    'font-mono text-2xl font-bold tabular-nums',
                    tone === 'muted'
                        ? 'text-muted-foreground'
                        : 'text-foreground',
                )}
                title={value}
            >
                {value}
            </p>
            <p className="text-muted-foreground truncate text-[11px]">{note}</p>
        </div>
    );
}

function CategoriesBoard({
    categories,
    totals,
    onEdit,
    onDelete,
}: {
    categories: ParentCategoryRow[];
    totals: Totals;
    onEdit: (row: CategoryRow) => void;
    onDelete: (row: CategoryRow) => void;
}) {
    const [expanded, setExpanded] = useState<Set<number>>(
        () =>
            new Set(
                categories
                    .filter((c) => c.children.length > 0)
                    .map((c) => c.id),
            ),
    );

    const inUseCount = totals.in_use;
    const idleCount = totals.idle;
    const status: 'ok' | 'warn' | 'idle' =
        inUseCount === 0 ? 'idle' : idleCount > inUseCount ? 'warn' : 'ok';
    const statusBg =
        status === 'ok'
            ? 'bg-emerald-500'
            : status === 'warn'
              ? 'bg-amber-500'
              : 'bg-slate-400';
    const statusLabel =
        status === 'ok'
            ? `${inUseCount} ACTIVE`
            : status === 'warn'
              ? `${idleCount} IDLE`
              : 'NO ACTIVITY';

    const toggleExpand = (id: number) => {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    return (
        <section className="bg-card border-border overflow-hidden rounded-2xl border shadow-sm">
            <div className="border-border flex flex-wrap items-center justify-between gap-3 border-b px-6 py-3">
                <div className="flex items-center gap-3">
                    <span className="text-foreground font-mono text-xs font-semibold tracking-[0.18em] uppercase">
                        Categories
                    </span>
                    <span className="text-muted-foreground text-[11px]">
                        Jerarquía · roll-up padre + hijos
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
                                status === 'idle' && 'text-muted-foreground',
                            )}
                        >
                            {statusLabel}
                        </span>
                    </div>
                    <div className="text-muted-foreground text-[11px]">
                        transacciones del mes en curso
                    </div>
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-xs">
                    <thead>
                        <tr className="border-border text-muted-foreground border-b font-mono text-[10px] tracking-wider uppercase">
                            <th className="px-6 py-2 text-left font-semibold">
                                name
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                Transactions
                            </th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody className="divide-border divide-y">
                        {categories.map((parent) => {
                            const isExpanded = expanded.has(parent.id);
                            const hasChildren = parent.children.length > 0;
                            return (
                                <CategoryRows
                                    key={parent.uuid}
                                    parent={parent}
                                    expanded={isExpanded}
                                    hasChildren={hasChildren}
                                    onToggle={() => toggleExpand(parent.id)}
                                    onEdit={onEdit}
                                    onDelete={onDelete}
                                />
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function CategoryRows({
    parent,
    expanded,
    hasChildren,
    onToggle,
    onEdit,
    onDelete,
}: {
    parent: ParentCategoryRow;
    expanded: boolean;
    hasChildren: boolean;
    onToggle: () => void;
    onEdit: (row: CategoryRow) => void;
    onDelete: (row: CategoryRow) => void;
}) {
    return (
        <>
            <CategoryRow
                row={parent}
                level={0}
                hasChildren={hasChildren}
                expanded={expanded}
                onToggle={onToggle}
                onEdit={onEdit}
                onDelete={onDelete}
            />
            {expanded &&
                parent.children.map((child) => (
                    <CategoryRow
                        key={child.uuid}
                        row={child}
                        level={1}
                        hasChildren={false}
                        expanded={false}
                        onToggle={() => undefined}
                        onEdit={onEdit}
                        onDelete={onDelete}
                    />
                ))}
        </>
    );
}

function CategoryRow({
    row,
    level,
    hasChildren,
    expanded,
    onToggle,
    onEdit,
    onDelete,
}: {
    row: CategoryRow;
    level: 0 | 1;
    hasChildren: boolean;
    expanded: boolean;
    onToggle: () => void;
    onEdit: (row: CategoryRow) => void;
    onDelete: (row: CategoryRow) => void;
}) {
    const isChild = level === 1;

    return (
        <tr
            className={cn(
                'hover:bg-muted/40 transition-colors',
                isChild && 'bg-muted/10',
                row.transaction_count === 0 && 'opacity-70',
            )}
        >
            <td className={cn('py-2.5 pr-3', isChild ? 'pl-16' : 'pl-6')}>
                <div className="flex items-center gap-2.5">
                    {!isChild &&
                        (hasChildren ? (
                            <button
                                type="button"
                                onClick={onToggle}
                                className="hover:bg-muted text-muted-foreground -ml-1 flex size-5 items-center justify-center rounded-sm transition-colors"
                                aria-label={expanded ? 'Colapsar' : 'Expandir'}
                            >
                                {expanded ? (
                                    <ChevronDownIcon className="size-3.5" />
                                ) : (
                                    <ChevronRightIcon className="size-3.5" />
                                )}
                            </button>
                        ) : (
                            <span className="-ml-1 size-5" />
                        ))}

                    <span
                        className="flex size-6 flex-shrink-0 items-center justify-center rounded-md border text-[11px]"
                        style={{
                            backgroundColor: row.color + '20',
                            borderColor: row.color,
                        }}
                    >
                        {row.emoji ?? ''}
                    </span>
                    <span
                        className={cn(
                            'text-foreground',
                            isChild ? 'text-[12px]' : 'font-medium',
                        )}
                    >
                        {row.name}
                    </span>
                </div>
            </td>
            <td className="text-muted-foreground px-3 py-2.5 text-right font-mono tabular-nums">
                {row.transaction_count > 0 ? row.transaction_count : '—'}
            </td>
            <td className="px-3 py-2.5 text-right">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground hover:text-foreground size-7"
                            aria-label="Acciones"
                        >
                            <MoreHorizontalIcon className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => onEdit(row)}>
                            <PencilIcon />
                            Editar
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onClick={() => onDelete(row)}
                            variant="destructive"
                        >
                            <Trash2Icon />
                            Eliminar
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </td>
        </tr>
    );
}
