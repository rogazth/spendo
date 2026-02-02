import { useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { getCurrencyLocale } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { Account, AccountType, Currency } from '@/types';
import { ACCOUNT_TYPES } from '@/types';
import { DEFAULT_COLORS } from '@/constants/colors';

interface AccountFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    account?: Account;
}

export function AccountFormDialog({
    open,
    onOpenChange,
    account,
}: AccountFormDialogProps) {
    const isEditing = !!account;
    const { currencies = [] } = usePage<{ currencies?: Currency[] }>().props;
    const currencyOptions = currencies.length
        ? currencies
        : [
              { code: 'CLP', name: 'Peso chileno', locale: 'es-CL' },
              { code: 'USD', name: 'Dólar estadounidense', locale: 'en-US' },
              { code: 'EUR', name: 'Euro', locale: 'es-ES' },
          ];

    const { data, setData, post, put, processing, errors, reset } = useForm<{
        name: string;
        type: AccountType;
        currency: string;
        initial_balance: number | null;
        color: string;
        icon: string;
        is_active: boolean;
        is_default: boolean;
    }>({
        name: '',
        type: 'checking',
        currency: 'CLP',
        initial_balance: null,
        color: '#3B82F6',
        icon: 'wallet',
        is_active: true,
        is_default: false,
    });

    useEffect(() => {
        if (open) {
            setData({
                name: account?.name ?? '',
                type: account?.type ?? 'checking',
                currency: account?.currency ?? 'CLP',
                initial_balance: null,
                color: account?.color ?? '#3B82F6',
                icon: account?.icon ?? 'wallet',
                is_active: account?.is_active ?? true,
                is_default: account?.is_default ?? false,
            });
        }
    }, [open, account]);

    const currencyLocale = getCurrencyLocale(data.currency, currencyOptions);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
                toast.success(
                    isEditing ? 'Cuenta actualizada' : 'Cuenta creada',
                );
            },
            onError: () => {
                toast.error('Error al guardar la cuenta');
            },
        };

        if (isEditing) {
            put(`/accounts/${account.uuid}`, options);
        } else {
            post('/accounts', options);
        }
    };

    const handleOpenChange = (open: boolean) => {
        onOpenChange(open);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {isEditing ? 'Editar Cuenta' : 'Nueva Cuenta'}
                        </DialogTitle>
                        <DialogDescription>
                            {isEditing
                                ? 'Actualiza los datos de tu cuenta.'
                                : 'Agrega una nueva cuenta para administrar tus finanzas.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">Nombre</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Ej: Cuenta Corriente Banco Estado"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="type">Tipo de Cuenta</Label>
                            <Select
                                value={data.type}
                                onValueChange={(value: AccountType) =>
                                    setData('type', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecciona un tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    {ACCOUNT_TYPES.map((type) => (
                                        <SelectItem
                                            key={type.id}
                                            value={type.id}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.type} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="currency">Moneda</Label>
                                <Select
                                    value={data.currency}
                                    onValueChange={(value) =>
                                        setData('currency', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona moneda" />
                                    </SelectTrigger>
                                <SelectContent>
                                    {currencyOptions.map((currency) => (
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

                            {!isEditing && (
                                <div className="space-y-2">
                                    <Label htmlFor="initial_balance">
                                        Balance Inicial
                                    </Label>
                                    <MoneyInput
                                        id="initial_balance"
                                        currency={data.currency}
                                        locale={currencyLocale}
                                        value={data.initial_balance}
                                        onValueChange={(value) =>
                                            setData('initial_balance', value)
                                        }
                                        placeholder="0"
                                    />
                                    <InputError
                                        message={errors.initial_balance}
                                    />
                                </div>
                            )}
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between rounded-lg border px-3 py-2">
                                <Label
                                    htmlFor="is_default"
                                    className="text-sm font-medium"
                                >
                                    Cuenta por defecto
                                </Label>
                                <Switch
                                    id="is_default"
                                    checked={data.is_default}
                                    onCheckedChange={(checked) =>
                                        setData('is_default', checked === true)
                                    }
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Color</Label>
                            <div className="flex flex-wrap gap-2">
                                {DEFAULT_COLORS.map((color) => (
                                    <button
                                        key={color}
                                        type="button"
                                        onClick={() => setData('color', color)}
                                        className={cn(
                                            'size-8 rounded-full border-2',
                                            data.color === color
                                                ? 'border-foreground'
                                                : 'border-transparent',
                                        )}
                                        style={{ backgroundColor: color }}
                                    />
                                ))}
                            </div>
                            <InputError message={errors.color} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleOpenChange(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Guardando...'
                                : isEditing
                                  ? 'Guardar'
                                  : 'Crear'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
