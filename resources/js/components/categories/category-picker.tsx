import { useMemo } from 'react';
import { CategoryAvatar } from '@/components/categories/category-avatar';
import { SelectField } from '@/components/ui/select-field';
import { SelectList, type SelectOption } from '@/components/ui/select-list';
import type { Category } from '@/types';

export function toCategoryOptions(
    categories: Category[],
    parentsOnly = false,
): SelectOption[] {
    return categories.map((parent) => ({
        id: parent.id,
        label: parent.name,
        keywords: [parent.name],
        leading: (
            <CategoryAvatar
                color={parent.color}
                emoji={parent.emoji}
                size="md"
            />
        ),
        children: parentsOnly
            ? undefined
            : parent.children?.map((child) => ({
                  id: child.id,
                  label: child.name,
                  keywords: [child.name, parent.name],
                  leading: (
                      <CategoryAvatar
                          color={child.color}
                          emoji={child.emoji}
                          size="md"
                      />
                  ),
              })),
    }));
}

function findOption(
    options: SelectOption[],
    value: number | null,
): SelectOption | null {
    if (value === null) {
        return null;
    }
    for (const option of options) {
        if (option.id === value) {
            return option;
        }
        const child = option.children?.find((c) => c.id === value);
        if (child) {
            return child;
        }
    }
    return null;
}

interface CategoryPickerProps {
    categories: Category[];
    value: number | null;
    onChange: (id: number | null) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    triggerClassName?: string;
    disabled?: boolean;
    id?: string;
    parentsOnly?: boolean;
    allowClear?: boolean;
    clearLabel?: string;
}

export function CategoryPicker({
    categories,
    value,
    onChange,
    placeholder = 'Selecciona categoría',
    searchPlaceholder = 'Buscar categoría...',
    emptyMessage = 'Sin categorías',
    triggerClassName,
    disabled,
    id,
    parentsOnly = false,
    allowClear = false,
    clearLabel = 'Sin categoría',
}: CategoryPickerProps) {
    const options = useMemo(
        () => toCategoryOptions(categories, parentsOnly),
        [categories, parentsOnly],
    );
    const selected = useMemo(() => findOption(options, value), [options, value]);

    return (
        <SelectField
            id={id}
            disabled={disabled}
            placeholder={placeholder}
            title={placeholder}
            triggerClassName={triggerClassName}
            value={
                selected ? (
                    <>
                        {selected.leading}
                        <span className="truncate text-sm">
                            {selected.label}
                        </span>
                    </>
                ) : undefined
            }
        >
            {({ close }) => (
                <SelectList
                    mode="single"
                    options={options}
                    value={value}
                    onChange={onChange}
                    onSelect={close}
                    searchPlaceholder={searchPlaceholder}
                    emptyMessage={emptyMessage}
                    clearOption={
                        allowClear
                            ? { label: clearLabel, keywords: ['ninguna', 'sin'] }
                            : undefined
                    }
                />
            )}
        </SelectField>
    );
}

interface CategoryMultiSelectProps {
    categories: Category[];
    value: number[];
    onChange: (ids: number[]) => void;
    searchPlaceholder?: string;
    emptyMessage?: string;
}

export function CategoryMultiSelect({
    categories,
    value,
    onChange,
    searchPlaceholder = 'Buscar categoría...',
    emptyMessage = 'Sin categorías',
}: CategoryMultiSelectProps) {
    const options = useMemo(() => toCategoryOptions(categories), [categories]);

    return (
        <SelectList
            mode="multiple"
            cascadeChildren
            options={options}
            value={value}
            onChange={onChange}
            searchPlaceholder={searchPlaceholder}
            emptyMessage={emptyMessage}
        />
    );
}
