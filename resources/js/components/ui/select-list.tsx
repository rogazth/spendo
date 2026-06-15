import { CheckIcon } from 'lucide-react';
import { Fragment, type ReactNode } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { cn } from '@/lib/utils';

export interface SelectOption {
    id: number;
    label: string;
    sublabel?: ReactNode;
    leading?: ReactNode;
    keywords?: string[];
    children?: SelectOption[];
}

interface BaseSelectListProps {
    options: SelectOption[];
    searchPlaceholder?: string;
    emptyMessage?: string;
    className?: string;
}

interface SingleSelectListProps extends BaseSelectListProps {
    mode: 'single';
    value: number | null;
    onChange: (id: number | null) => void;
    /** Called after a selection, e.g. to close the surrounding popover. */
    onSelect?: () => void;
    clearOption?: { label: string; keywords?: string[] };
}

interface MultiSelectListProps extends BaseSelectListProps {
    mode: 'multiple';
    value: number[];
    onChange: (ids: number[]) => void;
    /** When set, toggling a parent toggles all its children (and vice-versa). */
    cascadeChildren?: boolean;
}

export type SelectListProps = SingleSelectListProps | MultiSelectListProps;

/**
 * Single source of truth for the searchable picker body shared by filters and
 * forms. Owns all list spacing/layout; callers only provide options and a
 * selection model. Render it inside a `FilterPill` (filters) or a `SelectField`
 * (forms) — it intentionally knows nothing about triggers or popovers.
 */
const GROUP_CLASSNAME = 'p-3';
const CHILD_INDENT = 'pl-8';

export function SelectList(props: SelectListProps) {
    const {
        searchPlaceholder,
        emptyMessage = 'Sin resultados',
        className,
    } = props;

    return (
        <Command className={className}>
            <CommandInput placeholder={searchPlaceholder} />
            <CommandList>
                <CommandEmpty>{emptyMessage}</CommandEmpty>
                <CommandGroup className={GROUP_CLASSNAME}>
                    {props.mode === 'single' ? (
                        <SingleRows {...props} />
                    ) : (
                        <MultiRows {...props} />
                    )}
                </CommandGroup>
            </CommandList>
        </Command>
    );
}

function SingleRows({
    options,
    value,
    onChange,
    onSelect,
    clearOption,
}: SingleSelectListProps) {
    const handleSelect = (id: number | null) => {
        onChange(id);
        onSelect?.();
    };

    return (
        <>
            {clearOption && (
                <CommandItem
                    value="opt-clear"
                    keywords={clearOption.keywords ?? ['ninguno', 'sin']}
                    onSelect={() => handleSelect(null)}
                    className="gap-2 text-muted-foreground"
                >
                    <span className="flex-1 truncate">{clearOption.label}</span>
                    {value === null && (
                        <CheckIcon className="size-4 shrink-0 text-foreground" />
                    )}
                </CommandItem>
            )}
            {options.map((option) => (
                <Fragment key={option.id}>
                    <SingleRow
                        option={option}
                        selected={value === option.id}
                        onSelect={handleSelect}
                    />
                    {option.children?.map((child) => (
                        <SingleRow
                            key={child.id}
                            option={child}
                            selected={value === child.id}
                            onSelect={handleSelect}
                            isChild
                        />
                    ))}
                </Fragment>
            ))}
        </>
    );
}

interface SingleRowProps {
    option: SelectOption;
    selected: boolean;
    onSelect: (id: number) => void;
    isChild?: boolean;
}

function SingleRow({ option, selected, onSelect, isChild }: SingleRowProps) {
    return (
        <CommandItem
            value={`opt-${option.id}`}
            keywords={option.keywords ?? [option.label]}
            onSelect={() => onSelect(option.id)}
            className={cn('gap-2', isChild && CHILD_INDENT)}
        >
            {option.leading}
            <div className="flex min-w-0 flex-1 flex-col leading-tight">
                <span className="truncate text-sm">{option.label}</span>
                {option.sublabel}
            </div>
            {selected && (
                <CheckIcon className="size-4 shrink-0 text-foreground" />
            )}
        </CommandItem>
    );
}

function MultiRows({
    options,
    value,
    onChange,
    cascadeChildren,
}: MultiSelectListProps) {
    const selected = new Set(value);

    const toggleFlat = (id: number) => {
        onChange(
            selected.has(id)
                ? value.filter((v) => v !== id)
                : [...value, id],
        );
    };

    const toggleParent = (option: SelectOption) => {
        const childIds = option.children?.map((c) => c.id) ?? [];
        if (!cascadeChildren || childIds.length === 0) {
            toggleFlat(option.id);
            return;
        }

        const allChildrenSelected = childIds.every((id) => selected.has(id));
        const isChecked = selected.has(option.id) || allChildrenSelected;

        if (isChecked) {
            const remove = new Set([option.id, ...childIds]);
            onChange(value.filter((id) => !remove.has(id)));
        } else {
            onChange([
                ...value.filter((id) => !childIds.includes(id)),
                option.id,
            ]);
        }
    };

    const toggleChild = (parent: SelectOption, childId: number) => {
        if (!cascadeChildren) {
            toggleFlat(childId);
            return;
        }

        const siblingIds = (parent.children ?? [])
            .map((c) => c.id)
            .filter((id) => id !== childId);

        if (selected.has(parent.id)) {
            const next = value.filter((id) => id !== parent.id);
            onChange([...new Set([...next, ...siblingIds])]);
            return;
        }

        if (selected.has(childId)) {
            onChange(value.filter((id) => id !== childId));
        } else {
            onChange([...value, childId]);
        }
    };

    return (
        <>
            {options.map((option) => {
                const childIds = option.children?.map((c) => c.id) ?? [];
                const parentSelected = selected.has(option.id);
                const allChildrenSelected =
                    childIds.length > 0 &&
                    childIds.every((id) => selected.has(id));
                const parentChecked =
                    parentSelected ||
                    (!!cascadeChildren && allChildrenSelected);

                return (
                    <Fragment key={option.id}>
                        <CommandItem
                            value={`opt-${option.id}`}
                            keywords={option.keywords ?? [option.label]}
                            onSelect={() => toggleParent(option)}
                            className="gap-2"
                        >
                            <Checkbox
                                checked={parentChecked}
                                tabIndex={-1}
                                aria-hidden
                                className="pointer-events-none"
                            />
                            {option.leading}
                            <span className="flex-1 truncate">
                                {option.label}
                            </span>
                        </CommandItem>
                        {option.children?.map((child) => {
                            const childChecked =
                                (!!cascadeChildren && parentSelected) ||
                                selected.has(child.id);
                            return (
                                <CommandItem
                                    key={child.id}
                                    value={`opt-${child.id}`}
                                    keywords={child.keywords ?? [child.label]}
                                    onSelect={() =>
                                        toggleChild(option, child.id)
                                    }
                                    className={cn('gap-2', CHILD_INDENT)}
                                >
                                    <Checkbox
                                        checked={childChecked}
                                        tabIndex={-1}
                                        aria-hidden
                                        className="pointer-events-none"
                                    />
                                    {child.leading}
                                    <span className="flex-1 truncate">
                                        {child.label}
                                    </span>
                                </CommandItem>
                            );
                        })}
                    </Fragment>
                );
            })}
        </>
    );
}
