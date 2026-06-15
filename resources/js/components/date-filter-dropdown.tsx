import {
    endOfMonth,
    endOfWeek,
    format,
    startOfMonth,
    startOfToday,
    startOfWeek,
    subDays,
    subMonths,
} from 'date-fns';
import { es } from 'date-fns/locale';
import { useMemo, useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { FilterPill } from '@/components/filter-pill';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';

interface DateFilterDropdownProps {
    dateFrom: string;
    dateTo: string;
    datesAll?: boolean;
    onChange: (next: { dateFrom: string; dateTo: string }) => void;
    onClearDates?: () => void;
}

function parseLocalDate(value: string): Date | undefined {
    if (!value) return undefined;
    const [year, month, day] = value.split('-').map(Number);
    if (!year || !month || !day) return undefined;
    return new Date(year, month - 1, day);
}

export function DateFilterDropdown({
    dateFrom,
    dateTo,
    datesAll,
    onChange,
    onClearDates,
}: DateFilterDropdownProps) {
    const clearDates = () => {
        if (onClearDates) {
            onClearDates();
        } else {
            onChange({ dateFrom: '', dateTo: '' });
        }
    };
    const [pendingStart, setPendingStart] = useState<Date | undefined>();

    const selectedRange = useMemo<DateRange | undefined>(() => {
        if (pendingStart) return { from: pendingStart, to: undefined };
        if (!dateFrom || !dateTo) return undefined;
        const from = parseLocalDate(dateFrom);
        const to = parseLocalDate(dateTo);
        if (!from || !to) return undefined;
        return { from, to };
    }, [pendingStart, dateFrom, dateTo]);

    const dateValue = useMemo(() => {
        if (!dateFrom || !dateTo) return undefined;
        const fromDate = parseLocalDate(dateFrom);
        const toDate = parseLocalDate(dateTo);
        if (!fromDate || !toDate) return undefined;
        if (dateFrom === dateTo) {
            return format(fromDate, 'PPP', { locale: es });
        }
        return `${format(fromDate, 'd MMM', { locale: es })} — ${format(toDate, 'd MMM yyyy', { locale: es })}`;
    }, [dateFrom, dateTo]);

    const today = startOfToday();
    const quickOptions = [
        { key: 'today', label: 'Hoy', from: today, to: today },
        {
            key: 'this-week',
            label: 'Esta semana',
            from: startOfWeek(today, { weekStartsOn: 1 }),
            to: endOfWeek(today, { weekStartsOn: 1 }),
        },
        {
            key: 'this-month',
            label: 'Este mes',
            from: startOfMonth(today),
            to: endOfMonth(today),
        },
        {
            key: 'last-30-days',
            label: 'Últimos 30 días',
            from: subDays(today, 29),
            to: today,
        },
        {
            key: 'last-month',
            label: 'Último mes',
            from: startOfMonth(subMonths(today, 1)),
            to: endOfMonth(subMonths(today, 1)),
        },
        {
            key: 'last-6-months',
            label: 'Últimos 6 meses',
            from: startOfMonth(subMonths(today, 5)),
            to: endOfMonth(today),
        },
    ] as const;

    const selectedQuickKey = useMemo(() => {
        return (
            quickOptions.find((option) => {
                const optionFrom = format(option.from, 'yyyy-MM-dd');
                const optionTo = format(option.to, 'yyyy-MM-dd');
                return optionFrom === dateFrom && optionTo === dateTo;
            })?.key ?? null
        );
    }, [dateFrom, dateTo, quickOptions]);

    const pillValue = datesAll ? 'Todas las fechas' : dateValue;

    return (
        <FilterPill
            label="Fecha"
            value={pillValue}
            onClear={clearDates}
            contentClassName="w-[340px] max-w-[calc(100vw-2rem)] p-0 sm:w-[300px]"
        >
            {({ close }) => {
                const applyQuickRange = (from: Date, to: Date) => {
                    const fromValue = format(from, 'yyyy-MM-dd');
                    const toValue = format(to, 'yyyy-MM-dd');
                    setPendingStart(undefined);
                    onChange({ dateFrom: fromValue, dateTo: toValue });
                    close();
                };

                return (
                    <div className="flex flex-col gap-3 px-4 pt-4 sm:pb-4">
                        <div className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4">
                            {quickOptions.map((option) => (
                                <Button
                                    key={option.key}
                                    type="button"
                                    variant={
                                        selectedQuickKey === option.key
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                    size="sm"
                                    className="h-8 shrink-0 rounded-full px-3"
                                    onClick={() =>
                                        applyQuickRange(option.from, option.to)
                                    }
                                >
                                    {option.label}
                                </Button>
                            ))}
                        </div>

                        <Calendar
                            mode="range"
                            selected={selectedRange}
                            onSelect={(_range, clickedDay) => {
                                if (!clickedDay) return;
                                if (!pendingStart) {
                                    setPendingStart(clickedDay);
                                    return;
                                }
                                let start = pendingStart;
                                let end = clickedDay;
                                if (end < start) {
                                    [start, end] = [end, start];
                                }
                                setPendingStart(undefined);
                                onChange({
                                    dateFrom: format(start, 'yyyy-MM-dd'),
                                    dateTo: format(end, 'yyyy-MM-dd'),
                                });
                                close();
                            }}
                            className="w-full !p-0"
                            classNames={{ root: 'w-full' }}
                            initialFocus
                        />
                    </div>
                );
            }}
        </FilterPill>
    );
}
