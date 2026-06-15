import { Link, useForm, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { CalendarIcon, PlusIcon, Trash2Icon } from 'lucide-react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { CategoryPicker } from '@/components/categories/category-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MoneyInput } from '@/components/ui/money-input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DEFAULT_COLORS } from '@/constants/colors';
import { cn } from '@/lib/utils';
import {
    BUDGET_FREQUENCIES,
    type Account,
    type Budget,
    type BudgetFrequency,
    type Category,
    type Currency,
} from '@/types';

interface BudgetFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    accounts: Account[];
    categories: Category[];
    budget?: Budget | null;
}

interface BudgetFormData {
    name: string;
    color: string;
    emoji: string | null;
    description: string;
    currency: string;
    frequency: BudgetFrequency;
    anchor_date: Date;
    ends_at: Date | null;
    account_ids: number[];
    items: Array<{
        category_id: number | null;
        amount: number | null;
    }>;
}

function buildInitialData(
    budget: Budget | null | undefined,
    defaultCurrency: string,
): BudgetFormData {
    if (budget) {
        return {
            name: budget.name,
            color: budget.color ?? '#6366F1',
            emoji: budget.emoji ?? null,
            description: budget.description ?? '',
            currency: budget.currency,
            frequency: budget.frequency,
            anchor_date: new Date(`${budget.anchor_date}T00:00:00`),
            ends_at: budget.ends_at
                ? new Date(`${budget.ends_at}T00:00:00`)
                : null,
            account_ids:
                budget.account_ids ??
                (budget.accounts ?? []).map((account) => account.id),
            items: (budget.items ?? []).map((item) => ({
                category_id: item.category_id,
                amount: item.amount,
            })),
        };
    }

    return {
        name: '',
        color: '#6366F1',
        emoji: null,
        description: '',
        currency: defaultCurrency,
        frequency: 'monthly',
        anchor_date: new Date(),
        ends_at: null,
        account_ids: [],
        items: [{ category_id: null, amount: null }],
    };
}

export function BudgetFormDialog({
    open,
    onOpenChange,
    accounts,
    categories,
    budget = null,
}: BudgetFormDialogProps) {
    const { currencies = [] } = usePage<{ currencies?: Currency[] }>().props;
    const defaultAccount =
        accounts.find((account) => account.is_default) ?? accounts[0];
    const defaultCurrency = defaultAccount?.currency ?? 'CLP';
    const isEdit = budget !== null;

    const { data, setData, post, put, processing, errors, reset, transform } =
        useForm<BudgetFormData>(buildInitialData(budget, defaultCurrency));

    useEffect(() => {
        if (open) {
            setData(buildInitialData(budget, defaultCurrency));
        }
    }, [open, budget]);

    const addItem = () => {
        setData('items', [...data.items, { category_id: null, amount: null }]);
    };

    const removeItem = (index: number) => {
        setData(
            'items',
            data.items.filter((_, currentIndex) => currentIndex !== index),
        );
    };

    const updateItem = (
        index: number,
        patch: Partial<{ category_id: number | null; amount: number | null }>,
    ) => {
        setData(
            'items',
            data.items.map((item, currentIndex) =>
                currentIndex === index ? { ...item, ...patch } : item,
            ),
        );
    };

    const currencyAccounts = accounts.filter(
        (account) => account.currency === data.currency,
    );

    const toggleAccount = (accountId: number) => {
        setData(
            'account_ids',
            data.account_ids.includes(accountId)
                ? data.account_ids.filter((id) => id !== accountId)
                : [...data.account_ids, accountId],
        );
    };

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();

        transform((formData) => {
            const { anchor_date, ...rest } = formData;

            return {
                ...rest,
                ends_at: formData.ends_at
                    ? format(formData.ends_at, 'yyyy-MM-dd')
                    : null,
                description: formData.description || null,
                account_ids: formData.account_ids,
                items: formData.items
                    .filter(
                        (item) =>
                            item.category_id !== null && item.amount !== null,
                    )
                    .map((item) => ({
                        category_id: item.category_id,
                        amount: item.amount,
                    })),
                // Monthly budgets inherit the user's global cycle start day,
                // so the anchor date is derived server-side.
                ...(formData.frequency === 'monthly'
                    ? {}
                    : { anchor_date: format(anchor_date, 'yyyy-MM-dd') }),
            };
        });

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
                toast.success(isEdit ? 'Budget actualizado' : 'Budget creado');
            },
            onError: () => {
                toast.error(
                    isEdit
                        ? 'No se pudo actualizar el budget'
                        : 'No se pudo crear el budget',
                );
            },
        };

        if (isEdit && budget) {
            put(`/budgets/${budget.uuid}`, options);
        } else {
            post('/budgets', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="grid-rows-1 overflow-hidden p-0! max-sm:h-[100dvh] max-sm:max-h-[100dvh] max-sm:w-full max-sm:max-w-none max-sm:rounded-none! max-sm:border-0 sm:max-h-[95vh] sm:max-w-[680px]">
                <form
                    onSubmit={handleSubmit}
                    className="flex min-h-0 flex-col"
                >
                    <DialogHeader className="px-6 pt-6 max-sm:px-4">
                        <DialogTitle>
                            {isEdit ? 'Editar Budget' : 'Nuevo Budget'}
                        </DialogTitle>
                        <DialogDescription>
                            {isEdit
                                ? 'Modifica el budget y sus categorías.'
                                : 'Define un budget con frecuencia de repetición y límites por categoría.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid min-h-0 flex-1 gap-4 overflow-y-auto overscroll-contain px-6 py-4 max-sm:px-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="budget_name">Nombre</Label>
                                <div className="grid grid-cols-[3.5rem_1fr] gap-2">
                                    <Input
                                        id="budget_emoji"
                                        aria-label="Emoji"
                                        value={data.emoji ?? ''}
                                        onChange={(event) =>
                                            setData(
                                                'emoji',
                                                event.target.value || null,
                                            )
                                        }
                                        placeholder="💰"
                                        maxLength={8}
                                        className="h-9 text-center text-lg"
                                    />
                                    <input
                                        id="budget_name"
                                        value={data.name}
                                        onChange={(event) =>
                                            setData('name', event.target.value)
                                        }
                                        placeholder="Ej: Comida hogar"
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    />
                                </div>
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="frequency">Frecuencia</Label>
                                <Select
                                    value={data.frequency}
                                    onValueChange={(value) =>
                                        setData(
                                            'frequency',
                                            value as BudgetFrequency,
                                        )
                                    }
                                >
                                    <SelectTrigger id="frequency">
                                        <SelectValue placeholder="Selecciona frecuencia" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {BUDGET_FREQUENCIES.map((frequency) => (
                                            <SelectItem
                                                key={frequency.id}
                                                value={frequency.id}
                                            >
                                                {frequency.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.frequency} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="currency">Moneda</Label>
                                <Select
                                    value={data.currency}
                                    onValueChange={(value) => {
                                        setData((previous) => ({
                                            ...previous,
                                            currency: value,
                                            account_ids:
                                                previous.account_ids.filter(
                                                    (id) =>
                                                        accounts.find(
                                                            (account) =>
                                                                account.id ===
                                                                id,
                                                        )?.currency === value,
                                                ),
                                        }));
                                    }}
                                >
                                    <SelectTrigger id="currency">
                                        <SelectValue placeholder="Selecciona moneda" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {currencies.map((currency) => (
                                            <SelectItem
                                                key={currency.code}
                                                value={currency.code}
                                            >
                                                {currency.code} -{' '}
                                                {currency.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.currency} />
                            </div>

                            {data.frequency === 'monthly' ? (
                                <div className="space-y-2">
                                    <Label>Inicio del ciclo</Label>
                                    <div className="flex h-9 items-center rounded-md border border-input bg-muted/40 px-3 text-sm text-muted-foreground">
                                        Según tu configuración mensual
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Los budgets mensuales siguen tu{' '}
                                        <Link
                                            href="/settings/preferences"
                                            className="underline underline-offset-2"
                                        >
                                            día de inicio de ciclo
                                        </Link>
                                        .
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <Label>Fecha de inicio del ciclo</Label>
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button
                                                variant="outline"
                                                className="w-full justify-start text-left font-normal"
                                            >
                                                <CalendarIcon className="h-4 w-4" />
                                                {format(
                                                    data.anchor_date,
                                                    'PPP',
                                                    {
                                                        locale: es,
                                                    },
                                                )}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent
                                            className="w-auto p-0"
                                            align="start"
                                        >
                                            <Calendar
                                                mode="single"
                                                selected={data.anchor_date}
                                                onSelect={(date) =>
                                                    date &&
                                                    setData('anchor_date', date)
                                                }
                                                initialFocus
                                            />
                                        </PopoverContent>
                                    </Popover>
                                    <InputError message={errors.anchor_date} />
                                </div>
                            )}
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label>Fecha finalización (opcional)</Label>
                                {data.ends_at && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setData('ends_at', null)}
                                    >
                                        Quitar fecha
                                    </Button>
                                )}
                            </div>
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className={cn(
                                            'w-full justify-start text-left font-normal',
                                            !data.ends_at &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        <CalendarIcon className="h-4 w-4" />
                                        {data.ends_at ? (
                                            format(data.ends_at, 'PPP', {
                                                locale: es,
                                            })
                                        ) : (
                                            <span>
                                                Sin fecha de finalización
                                            </span>
                                        )}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    className="w-auto p-0"
                                    align="start"
                                >
                                    <Calendar
                                        mode="single"
                                        selected={data.ends_at ?? undefined}
                                        onSelect={(date) =>
                                            setData('ends_at', date ?? null)
                                        }
                                        initialFocus
                                    />
                                </PopoverContent>
                            </Popover>
                            <InputError message={errors.ends_at} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Descripción</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                rows={3}
                                placeholder="Objetivo de este budget..."
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="space-y-2">
                            <Label>Cuentas</Label>
                            <p className="text-xs text-muted-foreground">
                                El gasto de este budget se mide solo sobre las
                                cuentas seleccionadas.
                            </p>
                            {currencyAccounts.length === 0 ? (
                                <p className="rounded-md border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">
                                    No hay cuentas en {data.currency}.
                                </p>
                            ) : (
                                <div className="flex flex-wrap gap-2">
                                    {currencyAccounts.map((account) => {
                                        const selected =
                                            data.account_ids.includes(
                                                account.id,
                                            );
                                        return (
                                            <button
                                                key={account.id}
                                                type="button"
                                                onClick={() =>
                                                    toggleAccount(account.id)
                                                }
                                                className={cn(
                                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs transition-colors',
                                                    selected
                                                        ? 'border-foreground bg-foreground text-background'
                                                        : 'border-input bg-background text-foreground hover:bg-muted',
                                                )}
                                            >
                                                {account.emoji ?? '💳'}
                                                {account.name}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                            <InputError message={errors.account_ids} />
                        </div>

                        <div className="space-y-2">
                            <Label>Color</Label>
                            <div className="flex flex-wrap gap-2">
                                {DEFAULT_COLORS.map((color) => (
                                    <button
                                        key={color}
                                        type="button"
                                        onClick={() => setData('color', color)}
                                        className={`h-8 w-8 rounded-full border-2 ${
                                            data.color === color
                                                ? 'border-foreground'
                                                : 'border-transparent'
                                        }`}
                                        style={{ backgroundColor: color }}
                                    />
                                ))}
                            </div>
                            <InputError message={errors.color} />
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <Label>Categorías y montos máximos</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addItem}
                                >
                                    <PlusIcon className="h-4 w-4" />
                                    Agregar categoría
                                </Button>
                            </div>

                            {data.items.map((item, index) => (
                                <div
                                    key={index}
                                    className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_180px_auto]"
                                >
                                    <div className="space-y-2">
                                        <Label>Categoría</Label>
                                        <CategoryPicker
                                            categories={categories}
                                            value={item.category_id}
                                            onChange={(id) =>
                                                updateItem(index, {
                                                    category_id: id,
                                                })
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Monto máximo</Label>
                                        <MoneyInput
                                            currency={data.currency}
                                            locale="es-CL"
                                            value={item.amount}
                                            onValueChange={(value) =>
                                                updateItem(index, {
                                                    amount: value,
                                                })
                                            }
                                            placeholder="0"
                                        />
                                    </div>

                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-9 w-9"
                                            onClick={() => removeItem(index)}
                                            disabled={data.items.length === 1}
                                        >
                                            <Trash2Icon className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            <InputError message={errors.items} />
                        </div>
                    </div>

                    <DialogFooter className="border-t bg-background px-6 py-4 max-sm:flex-row max-sm:px-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="max-sm:flex-1"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="max-sm:flex-1"
                        >
                            {processing
                                ? 'Guardando...'
                                : isEdit
                                  ? 'Guardar cambios'
                                  : 'Crear budget'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
