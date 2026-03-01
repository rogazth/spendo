import { useForm, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { CalendarIcon, PlusIcon, Trash2Icon } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import { toast } from 'sonner';
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
import { Label } from '@/components/ui/label';
import { MoneyInput } from '@/components/ui/money-input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import {
    BUDGET_FREQUENCIES,
    type Account,
    type BudgetFrequency,
    type Category,
    type Currency,
} from '@/types';

interface BudgetFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    accounts: Account[];
    categories: Category[];
}

export function BudgetFormDialog({
    open,
    onOpenChange,
    accounts,
    categories,
}: BudgetFormDialogProps) {
    const { currencies = [] } = usePage<{ currencies?: Currency[] }>().props;
    const defaultAccount = accounts.find((account) => account.is_default) ?? accounts[0];

    const { data, setData, post, processing, errors, reset, transform } = useForm<{
        name: string;
        description: string;
        account_id: number | null;
        currency: string;
        frequency: BudgetFrequency;
        anchor_date: Date;
        ends_at: Date | null;
        items: Array<{
            category_id: number | null;
            amount: number | null;
        }>;
    }>({
        name: '',
        description: '',
        account_id: null,
        currency: defaultAccount?.currency ?? 'CLP',
        frequency: 'monthly',
        anchor_date: new Date(),
        ends_at: null,
        items: [{ category_id: null, amount: null }],
    });

    useEffect(() => {
        if (open) {
            setData({
                name: '',
                description: '',
                account_id: null,
                currency: defaultAccount?.currency ?? 'CLP',
                frequency: 'monthly',
                anchor_date: new Date(),
                ends_at: null,
                items: [{ category_id: null, amount: null }],
            });
        }
    }, [open]);

    const filteredAccounts = useMemo(
        () => accounts.filter((a) => a.currency === data.currency),
        [accounts, data.currency],
    );

    const categoryOptions = useMemo(() => {
        return categories.flatMap((category) => {
            const parent = {
                id: category.id,
                label: category.name,
                depth: 0,
                color: category.color,
            };
            const children = (category.children ?? []).map((child) => ({
                id: child.id,
                label: child.name,
                depth: 1,
                color: child.color,
            }));

            return [parent, ...children];
        });
    }, [categories]);

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

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();

        transform((formData) => ({
            ...formData,
            anchor_date: format(formData.anchor_date, 'yyyy-MM-dd'),
            ends_at: formData.ends_at
                ? format(formData.ends_at, 'yyyy-MM-dd')
                : null,
            description: formData.description || null,
            items: formData.items
                .filter(
                    (item) =>
                        item.category_id !== null && item.amount !== null,
                )
                .map((item) => ({
                    category_id: item.category_id,
                    amount: item.amount,
                })),
        }));

        post('/budgets', {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
                toast.success('Budget creado');
            },
            onError: () => {
                toast.error('No se pudo crear el budget');
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[680px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>Nuevo Budget</DialogTitle>
                        <DialogDescription>
                            Define un budget con frecuencia de repetición y
                            límites por categoría.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="budget_name">Nombre</Label>
                                <input
                                    id="budget_name"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    placeholder="Ej: Comida hogar"
                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                />
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

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="account_id">Cuenta (opcional)</Label>
                                <Select
                                    value={data.account_id?.toString() ?? 'all'}
                                    onValueChange={(value) => {
                                        if (value === 'all') {
                                            setData('account_id', null);
                                            return;
                                        }

                                        const accountId = parseInt(value, 10);
                                        setData('account_id', accountId);
                                        const selectedAccount = accounts.find(
                                            (account) =>
                                                account.id === accountId,
                                        );
                                        if (selectedAccount?.currency) {
                                            setData(
                                                'currency',
                                                selectedAccount.currency,
                                            );
                                        }
                                    }}
                                >
                                    <SelectTrigger id="account_id">
                                        <SelectValue placeholder="Todas las cuentas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todas las cuentas
                                        </SelectItem>
                                        {filteredAccounts.map((account) => (
                                            <SelectItem
                                                key={account.id}
                                                value={account.id.toString()}
                                            >
                                                {account.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.account_id} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="currency">Moneda</Label>
                                <Select
                                    value={data.currency}
                                    onValueChange={(value) => {
                                        setData('currency', value);
                                        const currentAccount = accounts.find(
                                            (a) => a.id === data.account_id,
                                        );
                                        if (
                                            currentAccount &&
                                            currentAccount.currency !== value
                                        ) {
                                            setData('account_id', null);
                                        }
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
                                                {currency.code} - {currency.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.currency} />
                            </div>

                            <div className="space-y-2">
                                <Label>Fecha ancla</Label>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="w-full justify-start text-left font-normal"
                                        >
                                            <CalendarIcon className="h-4 w-4" />
                                            {format(data.anchor_date, 'PPP', {
                                                locale: es,
                                            })}
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
                                            <span>Sin fecha de finalización</span>
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
                                        <Select
                                            value={
                                                item.category_id?.toString() ?? ''
                                            }
                                            onValueChange={(value) =>
                                                updateItem(index, {
                                                    category_id: parseInt(
                                                        value,
                                                        10,
                                                    ),
                                                })
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Selecciona categoría" />
                                            </SelectTrigger>
                                            <SelectContent position="popper" className="max-h-60">
                                                {categoryOptions.map((category) => (
                                                    <SelectItem
                                                        key={category.id}
                                                        value={category.id.toString()}
                                                    >
                                                        <span
                                                            className={cn(
                                                                'inline-flex items-center gap-2',
                                                                category.depth ===
                                                                    1 && 'pl-4',
                                                            )}
                                                        >
                                                            <span
                                                                className="h-2.5 w-2.5 rounded-full"
                                                                style={{
                                                                    backgroundColor:
                                                                        category.color,
                                                                }}
                                                            />
                                                            {category.label}
                                                        </span>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
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

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Guardando...' : 'Crear budget'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
