import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { FilterPill } from '@/components/filter-pill';
import { Button } from '@/components/ui/button';
import { SelectList } from '@/components/ui/select-list';

export interface CycleOption {
    start: string;
    end: string;
    is_current: boolean;
}

export function formatCycleRange(
    start: string,
    end: string,
    locale: string,
): string {
    const startDate = new Date(`${start}T00:00:00`);
    const endDate = new Date(`${end}T00:00:00`);
    const sameYear = startDate.getFullYear() === endDate.getFullYear();

    return `${startDate.toLocaleDateString(locale, {
        day: 'numeric',
        month: 'short',
        ...(sameYear ? {} : { year: 'numeric' }),
    })} — ${endDate.toLocaleDateString(locale, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })}`;
}

export function CyclePicker({
    options,
    selectedStart,
    locale,
    onSelect,
}: {
    options: CycleOption[];
    selectedStart: string;
    locale: string;
    onSelect: (start: string) => void;
}) {
    const index = options.findIndex((option) => option.start === selectedStart);
    const older = options[index + 1];
    const newer = options[index - 1];

    return (
        <div className="flex items-center gap-1">
            <Button
                variant="outline"
                size="icon"
                className="size-8 rounded-full"
                disabled={!older}
                onClick={() => older && onSelect(older.start)}
                aria-label="Ciclo anterior"
            >
                <ChevronLeftIcon />
            </Button>

            <FilterPill
                label="Ciclo"
                value={
                    index >= 0
                        ? formatCycleRange(
                              options[index].start,
                              options[index].end,
                              locale,
                          )
                        : undefined
                }
                align="start"
                contentClassName="w-72"
                flushBottom
            >
                {({ close }) => (
                    <SelectList
                        mode="single"
                        value={index >= 0 ? index : null}
                        onChange={(next) =>
                            next !== null && onSelect(options[next].start)
                        }
                        onSelect={close}
                        searchPlaceholder="Buscar ciclo"
                        emptyMessage="Sin ciclos"
                        options={options.map((option, position) => ({
                            id: position,
                            label: formatCycleRange(
                                option.start,
                                option.end,
                                locale,
                            ),
                            sublabel: option.is_current
                                ? 'Ciclo actual'
                                : undefined,
                        }))}
                    />
                )}
            </FilterPill>

            <Button
                variant="outline"
                size="icon"
                className="size-8 rounded-full"
                disabled={!newer}
                onClick={() => newer && onSelect(newer.start)}
                aria-label="Ciclo siguiente"
            >
                <ChevronRightIcon />
            </Button>
        </div>
    );
}
