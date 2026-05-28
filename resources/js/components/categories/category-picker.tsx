import { CheckIcon, ChevronsUpDownIcon } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { CategoryAvatar } from '@/components/categories/category-avatar';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { Category } from '@/types';

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
}

interface SelectedCategory {
    id: number;
    name: string;
    color: string;
    emoji: string | null;
    depth: 0 | 1;
}

function findCategory(
    categories: Category[],
    value: number | null,
): SelectedCategory | null {
    if (value === null) return null;
    for (const parent of categories) {
        if (parent.id === value) {
            return {
                id: parent.id,
                name: parent.name,
                color: parent.color,
                emoji: parent.emoji,
                depth: 0,
            };
        }
        const child = parent.children?.find((c) => c.id === value);
        if (child) {
            return {
                id: child.id,
                name: child.name,
                color: child.color,
                emoji: child.emoji,
                depth: 1,
            };
        }
    }
    return null;
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
}: CategoryPickerProps) {
    const [open, setOpen] = useState(false);
    const selected = useMemo(
        () => findCategory(categories, value),
        [categories, value],
    );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className={cn(
                        'w-full justify-between font-normal',
                        !selected && 'text-muted-foreground',
                        triggerClassName,
                    )}
                >
                    {selected ? (
                        <span className="flex min-w-0 items-center gap-2">
                            <CategoryAvatar
                                color={selected.color}
                                emoji={selected.emoji}
                            />
                            <span className="truncate text-sm">
                                {selected.name}
                            </span>
                        </span>
                    ) : (
                        <span className="text-sm">{placeholder}</span>
                    )}
                    <ChevronsUpDownIcon className="size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[var(--radix-popover-trigger-width)] min-w-[260px] p-0"
                align="start"
            >
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyMessage}</CommandEmpty>
                        <CommandGroup>
                            {categories.map((parent) => (
                                <Fragment key={parent.id}>
                                    <CommandItem
                                        value={`category-${parent.id}`}
                                        keywords={[parent.name]}
                                        onSelect={() => {
                                            onChange(parent.id);
                                            setOpen(false);
                                        }}
                                        className="gap-2"
                                    >
                                        <CategoryAvatar
                                            color={parent.color}
                                            emoji={parent.emoji}
                                            size="md"
                                        />
                                        <span className="flex-1 truncate">
                                            {parent.name}
                                        </span>
                                        {selected?.id === parent.id && (
                                            <CheckIcon className="text-foreground size-4 shrink-0" />
                                        )}
                                    </CommandItem>
                                    {parent.children?.map((child) => (
                                        <CommandItem
                                            key={child.id}
                                            value={`category-${child.id}`}
                                            keywords={[child.name, parent.name]}
                                            onSelect={() => {
                                                onChange(child.id);
                                                setOpen(false);
                                            }}
                                            className="gap-2 pl-8"
                                        >
                                            <CategoryAvatar
                                                color={child.color}
                                                emoji={child.emoji}
                                                size="md"
                                            />
                                            <span className="flex-1 truncate">
                                                {child.name}
                                            </span>
                                            {selected?.id === child.id && (
                                                <CheckIcon className="text-foreground size-4 shrink-0" />
                                            )}
                                        </CommandItem>
                                    ))}
                                </Fragment>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
