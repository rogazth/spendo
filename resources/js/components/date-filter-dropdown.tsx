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
import { CalendarIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

interface DateFilterDropdownProps {
    dateFrom: string;
    dateTo: string;
    onChange: (next: { dateFrom: string; dateTo: string }) => void;
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
    onChange,
}: DateFilterDropdownProps) {
    const [dateMode, setDateMode] = useState<'single' | 'range'>(
        dateFrom && dateTo && dateFrom !== dateTo ? 'range' : 'single',
    );

    const singleDate = useMemo(
        () => (dateFrom && dateFrom === dateTo ? parseLocalDate(dateFrom) : undefined),
        [dateFrom, dateTo],
    );

    const rangeDate = useMemo<DateRange | undefined>(() => {
        if (!dateFrom || !dateTo || dateFrom === dateTo) return undefined;
        const from = parseLocalDate(dateFrom);
        const to = parseLocalDate(dateTo);
        if (!from || !to) return undefined;
        return { from, to };
    }, [dateFrom, dateTo]);

    const dateLabel = useMemo(() => {
        if (dateFrom && dateTo) {
            const fromDate = parseLocalDate(dateFrom);
            const toDate = parseLocalDate(dateTo);
            if (!fromDate || !toDate) return 'Fecha';
            if (dateFrom === dateTo) {
                return format(fromDate, 'PPP', { locale: es });
            }
            return `${format(fromDate, 'PPP', { locale: es })} — ${format(toDate, 'PPP', { locale: es })}`;
        }
        return 'Fecha';
    }, [dateFrom, dateTo]);

    const today = startOfToday();
    const quickOptions = [
        {
            key: 'today',
            label: 'Hoy',
            from: today,
            to: today,
        },
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

    const setQuickRange = (from: Date, to: Date) => {
        const fromValue = format(from, 'yyyy-MM-dd');
        const toValue = format(to, 'yyyy-MM-dd');

        onChange({ dateFrom: fromValue, dateTo: toValue });
        if (fromValue === toValue) {
            setDateMode('single');
            return;
        }
        setDateMode('range');
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className={cn('justify-start border-dashed')}
                >
                    <CalendarIcon className="h-4 w-4" />
                    {dateLabel}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-[340px] max-w-[calc(100vw-2rem)] overflow-hidden p-0 sm:w-[520px]"
            >
                <div className="flex flex-col gap-3 p-3 sm:flex-row">
                    <div>
                        <div className="mb-2 inline-flex rounded-md border p-1">
                            <Button
                                type="button"
                                variant={dateMode === 'single' ? 'secondary' : 'ghost'}
                                size="sm"
                                onClick={() => setDateMode('single')}
                            >
                                Día
                            </Button>
                            <Button
                                type="button"
                                variant={dateMode === 'range' ? 'secondary' : 'ghost'}
                                size="sm"
                                onClick={() => setDateMode('range')}
                            >
                                Rango
                            </Button>
                        </div>
                        {dateMode === 'single' ? (
                            <Calendar
                                mode="single"
                                selected={singleDate}
                                onSelect={(date) => {
                                    if (!date) {
                                        onChange({ dateFrom: '', dateTo: '' });
                                        return;
                                    }
                                    const formatted = format(date, 'yyyy-MM-dd');
                                    onChange({ dateFrom: formatted, dateTo: formatted });
                                }}
                                initialFocus
                            />
                        ) : (
                            <Calendar
                                mode="range"
                                selected={rangeDate}
                                onSelect={(range) => {
                                    if (!range?.from) {
                                        onChange({ dateFrom: '', dateTo: '' });
                                        return;
                                    }
                                    const fromValue = format(range.from, 'yyyy-MM-dd');
                                    const toValue = format(range.to ?? range.from, 'yyyy-MM-dd');
                                    onChange({ dateFrom: fromValue, dateTo: toValue });
                                }}
                                initialFocus
                            />
                        )}
                    </div>
                    <div className="flex min-w-[180px] flex-col gap-2 border-t pt-3 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-3">
                        <span className="text-xs text-muted-foreground uppercase">
                            Rápidos
                        </span>
                        {quickOptions.map((option) => (
                            <Button
                                key={option.key}
                                type="button"
                                variant={selectedQuickKey === option.key ? 'secondary' : 'ghost'}
                                size="sm"
                                className="justify-start"
                                onClick={() => setQuickRange(option.from, option.to)}
                            >
                                {option.label}
                            </Button>
                        ))}
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="justify-start"
                            onClick={() => onChange({ dateFrom: '', dateTo: '' })}
                        >
                            Limpiar
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
