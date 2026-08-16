import { Head, router } from '@inertiajs/react';
import { FilterPill } from '@/components/filter-pill';
import {
    CategoryBreakdown,
    type Breakdown,
} from '@/components/insights/category-breakdown';
import {
    CyclePicker,
    formatCycleRange,
    type CycleOption,
} from '@/components/insights/cycle-picker';
import {
    YearChart,
    type CashFlowPoint,
} from '@/components/insights/year-chart';
import { SelectList } from '@/components/ui/select-list';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface YearSection {
    currency: string;
    currency_locale: string;
    cash_flow: CashFlowPoint[];
    income: number;
    expense: number;
    net: number;
    average_expense: number;
    start: string;
    end: string;
    categories: Breakdown;
}

interface CycleSection {
    currency: string;
    currency_locale: string;
    income: number;
    expense: number;
    net: number;
    categories: Breakdown;
    largest_expenses: {
        uuid: string;
        description: string;
        category: string;
        emoji: string;
        amount: number;
        date: string;
    }[];
}

interface Props {
    years: number[];
    selectedYear: number;
    year: YearSection[];
    cycleOptions: CycleOption[];
    selectedCycle: {
        start: string;
        end: string;
        is_current: boolean;
        days_left: number;
    };
    cycle: CycleSection[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insights', href: '/insights' },
];

export default function Insights({
    years,
    selectedYear,
    year,
    cycleOptions,
    selectedCycle,
    cycle,
}: Props) {
    const goToYear = (next: number) =>
        router.get(
            '/insights',
            { year: next, cycle: selectedCycle.start },
            {
                only: ['year', 'selectedYear'],
                preserveScroll: true,
                preserveState: true,
            },
        );

    const goToCycle = (start: string) =>
        router.get(
            '/insights',
            { year: selectedYear, cycle: start },
            {
                only: ['cycle', 'selectedCycle', 'cycleOptions'],
                preserveScroll: true,
                preserveState: true,
            },
        );

    const isEmpty = year.length === 0 && cycle.length === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Insights" />

            <div className="flex flex-1 flex-col gap-10 px-4 py-6 md:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            Insights
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            En qué se te fue la plata, en el año y en el ciclo.
                        </p>
                    </div>

                    {years.length > 1 && (
                        <FilterPill
                            label="Año"
                            value={String(selectedYear)}
                            align="end"
                            contentClassName="w-44"
                            flushBottom
                        >
                            {({ close }) => (
                                <SelectList
                                    mode="single"
                                    value={selectedYear}
                                    onChange={(next) =>
                                        next !== null && goToYear(Number(next))
                                    }
                                    onSelect={close}
                                    emptyMessage="Sin años"
                                    options={years.map((option) => ({
                                        id: option,
                                        label: String(option),
                                    }))}
                                />
                            )}
                        </FilterPill>
                    )}
                </div>

                {isEmpty ? (
                    <div className="flex flex-col items-center gap-2 rounded-2xl border border-border bg-card p-12 text-center">
                        <p className="font-medium text-foreground">
                            Todavía no hay nada que mostrar
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Registrá transacciones y acá vas a ver cuánto
                            gastaste por mes y por categoría.
                        </p>
                    </div>
                ) : (
                    <>
                        <YearBlock sections={year} year={selectedYear} />
                        <CycleBlock
                            sections={cycle}
                            options={cycleOptions}
                            selected={selectedCycle}
                            onSelect={goToCycle}
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function YearBlock({
    sections,
    year,
}: {
    sections: YearSection[];
    year: number;
}) {
    return (
        <section className="space-y-4">
            <div>
                <h2 className="text-lg font-semibold text-foreground">
                    El año {year}
                </h2>
                <p className="text-sm text-muted-foreground">
                    Un punto por ciclo, nombrado según el mes en que cierra.
                </p>
            </div>

            {sections.length === 0 ? (
                <p className="rounded-xl border border-border bg-card p-6 text-center text-sm text-muted-foreground">
                    Sin movimientos en {year}.
                </p>
            ) : (
                sections.map((section) => {
                    const locale = section.currency_locale ?? 'es-CL';
                    const money = (value: number) =>
                        formatCurrency(value, section.currency, locale);

                    return (
                        <div key={section.currency} className="space-y-4">
                            {sections.length > 1 && (
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {section.currency}
                                </p>
                            )}

                            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                <Stat
                                    label="Gastado"
                                    value={money(section.expense)}
                                />
                                <Stat
                                    label="Ingresado"
                                    value={money(section.income)}
                                />
                                <Stat
                                    label="Ahorro neto"
                                    value={money(section.net)}
                                    tone={section.net < 0 ? 'bad' : 'good'}
                                />
                                <Stat
                                    label="Promedio por ciclo"
                                    value={money(section.average_expense)}
                                />
                            </div>

                            <YearChart
                                points={section.cash_flow}
                                currency={section.currency}
                                locale={locale}
                            />

                            <div className="space-y-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Gasto por categoría en {year}
                                </h3>
                                <CategoryBreakdown
                                    breakdown={section.categories}
                                    currency={section.currency}
                                    locale={locale}
                                    range={{
                                        start: section.start,
                                        end: section.end,
                                    }}
                                    compareLabel={`${year - 1} a la misma altura`}
                                />
                            </div>
                        </div>
                    );
                })
            )}
        </section>
    );
}

function CycleBlock({
    sections,
    options,
    selected,
    onSelect,
}: {
    sections: CycleSection[];
    options: CycleOption[];
    selected: Props['selectedCycle'];
    onSelect: (start: string) => void;
}) {
    const locale = sections[0]?.currency_locale ?? 'es-CL';

    return (
        <section className="space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold text-foreground">
                        {selected.is_current ? 'Ciclo actual' : 'Ciclo cerrado'}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {formatCycleRange(selected.start, selected.end, locale)}
                        {selected.is_current &&
                            ` · quedan ${selected.days_left} días`}
                    </p>
                </div>

                <CyclePicker
                    options={options}
                    selectedStart={selected.start}
                    locale={locale}
                    onSelect={onSelect}
                />
            </div>

            {sections.length === 0 ? (
                <p className="rounded-xl border border-border bg-card p-6 text-center text-sm text-muted-foreground">
                    Sin movimientos en este ciclo.
                </p>
            ) : (
                sections.map((section) => {
                    const sectionLocale = section.currency_locale ?? 'es-CL';
                    const money = (value: number) =>
                        formatCurrency(value, section.currency, sectionLocale);

                    return (
                        <div key={section.currency} className="space-y-4">
                            {sections.length > 1 && (
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {section.currency}
                                </p>
                            )}

                            <div className="grid grid-cols-3 gap-3">
                                <Stat
                                    label="Gastado"
                                    value={money(section.expense)}
                                />
                                <Stat
                                    label="Ingresado"
                                    value={money(section.income)}
                                />
                                <Stat
                                    label="Neto"
                                    value={money(section.net)}
                                    tone={section.net < 0 ? 'bad' : 'good'}
                                />
                            </div>

                            <CategoryBreakdown
                                breakdown={section.categories}
                                currency={section.currency}
                                locale={sectionLocale}
                                range={{
                                    start: selected.start,
                                    end: selected.end,
                                }}
                                compareLabel="el ciclo anterior"
                            />

                            {section.largest_expenses.length > 0 && (
                                <div className="space-y-2">
                                    <h3 className="text-sm font-semibold text-foreground">
                                        Los gastos más grandes
                                    </h3>
                                    <div className="divide-y divide-border rounded-xl border border-border bg-card">
                                        {section.largest_expenses.map(
                                            (expense) => (
                                                <div
                                                    key={expense.uuid}
                                                    className="flex items-center justify-between gap-3 px-4 py-2.5"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm text-foreground">
                                                            <span aria-hidden>
                                                                {expense.emoji}
                                                            </span>{' '}
                                                            {expense.description ||
                                                                expense.category}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {expense.category} ·{' '}
                                                            {dayLabel(
                                                                expense.date,
                                                                sectionLocale,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <span className="shrink-0 text-sm font-medium text-foreground tabular-nums">
                                                        {money(expense.amount)}
                                                    </span>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    );
                })
            )}
        </section>
    );
}

function dayLabel(date: string, locale: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(locale, {
        day: 'numeric',
        month: 'short',
    });
}

function Stat({
    label,
    value,
    tone,
}: {
    label: string;
    value: string;
    tone?: 'good' | 'bad';
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p
                className={cn(
                    'mt-0.5 text-lg font-semibold text-foreground tabular-nums',
                    tone === 'bad' && 'text-rose-600 dark:text-rose-500',
                )}
            >
                {value}
            </p>
        </div>
    );
}
