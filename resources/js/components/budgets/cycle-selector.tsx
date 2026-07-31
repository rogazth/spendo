import { router } from '@inertiajs/react';
import { FilterPill } from '@/components/filter-pill';
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
    const startLabel = startDate.toLocaleDateString(locale, {
        day: 'numeric',
        month: 'short',
        ...(sameYear ? {} : { year: 'numeric' }),
    });
    const endLabel = endDate.toLocaleDateString(locale, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

    return `${startLabel} — ${endLabel}`;
}

export function CycleSelector({
    budgetUuid,
    options,
    selectedStart,
    locale,
}: {
    budgetUuid: string;
    options: CycleOption[];
    selectedStart: string | null;
    locale: string;
}) {
    const selectedIndex = options.findIndex((option) =>
        selectedStart === null
            ? option.is_current
            : option.start === selectedStart,
    );

    const goToCycle = (index: number | null) => {
        if (index === null) {
            return;
        }

        const option = options[index];

        if (!option) {
            return;
        }

        router.get(
            `/budgets/${budgetUuid}`,
            option.is_current ? {} : { cycle: option.start },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <FilterPill
            label="Ciclo"
            value={
                selectedIndex >= 0
                    ? formatCycleRange(
                          options[selectedIndex].start,
                          options[selectedIndex].end,
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
                    value={selectedIndex >= 0 ? selectedIndex : null}
                    onChange={goToCycle}
                    onSelect={close}
                    searchPlaceholder="Buscar ciclo"
                    emptyMessage="Sin ciclos"
                    options={options.map((option, index) => ({
                        id: index,
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
    );
}
