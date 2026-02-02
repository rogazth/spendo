import { useForm } from '@inertiajs/react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import type { Account, PaymentMethod, PaymentMethodType } from '@/types';
import { PAYMENT_METHOD_TYPES } from '@/types';
import { DEFAULT_COLORS } from '@/constants/colors';

interface PaymentMethodFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    paymentMethod?: PaymentMethod;
    accounts: Account[];
}

export function PaymentMethodFormDialog({
    open,
    onOpenChange,
    paymentMethod,
    accounts,
}: PaymentMethodFormDialogProps) {
    const isEditing = !!paymentMethod;

    const { data, setData, post, put, processing, errors, reset } = useForm<{
        linked_account_id: number | null;
        name: string;
        type: PaymentMethodType;
        last_four_digits: string;
        credit_limit: number | null;
        billing_cycle_day: number | null;
        payment_due_day: number | null;
        color: string;
        is_active: boolean;
        is_default: boolean;
    }>({
        linked_account_id: null,
        name: '',
        type: 'debit_card',
        last_four_digits: '',
        credit_limit: null,
        billing_cycle_day: null,
        payment_due_day: null,
        color: '#10B981',
        is_active: true,
        is_default: false,
    });

    useEffect(() => {
        if (open) {
            setData({
                linked_account_id: paymentMethod?.linked_account_id ?? null,
                name: paymentMethod?.name ?? '',
                type: paymentMethod?.type ?? 'debit_card',
                last_four_digits: paymentMethod?.last_four_digits ?? '',
                credit_limit: paymentMethod?.credit_limit ?? null,
                billing_cycle_day: paymentMethod?.billing_cycle_day ?? null,
                payment_due_day: paymentMethod?.payment_due_day ?? null,
                color: paymentMethod?.color ?? '#10B981',
                is_active: paymentMethod?.is_active ?? true,
                is_default: paymentMethod?.is_default ?? false,
            });
        }
    }, [open, paymentMethod]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
                toast.success(
                    isEditing
                        ? 'Método de pago actualizado'
                        : 'Método de pago creado',
                );
            },
            onError: () => {
                toast.error('Error al guardar el método de pago');
            },
        };

        if (isEditing) {
            put(`/payment-methods/${paymentMethod.uuid}`, options);
        } else {
            post('/payment-methods', options);
        }
    };

    const handleOpenChange = (open: boolean) => {
        onOpenChange(open);
    };

    const isCreditCard = data.type === 'credit_card';
    const requiresLinkedAccount = [
        'debit_card',
        'prepaid_card',
        'cash',
        'transfer',
    ].includes(data.type);

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {isEditing
                                ? 'Editar Método de Pago'
                                : 'Nuevo Método de Pago'}
                        </DialogTitle>
                        <DialogDescription>
                            {isEditing
                                ? 'Actualiza los datos del método de pago.'
                                : 'Agrega un nuevo método de pago.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="type">Tipo</Label>
                            <Select
                                value={data.type}
                                onValueChange={(value: PaymentMethodType) => {
                                    setData('type', value);
                                    if (value === 'credit_card') {
                                        setData('linked_account_id', null);
                                    }
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecciona un tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    {PAYMENT_METHOD_TYPES.map((type) => (
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

                        <div className="space-y-2">
                            <Label htmlFor="name">Nombre</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Ej: Visa Banco Estado"
                            />
                            <InputError message={errors.name} />
                        </div>

                        {requiresLinkedAccount && accounts.length > 0 && (
                            <div className="space-y-2">
                                <Label htmlFor="linked_account_id">
                                    Cuenta Vinculada
                                </Label>
                                <Select
                                    value={
                                        data.linked_account_id?.toString() || ''
                                    }
                                    onValueChange={(value) =>
                                        setData(
                                            'linked_account_id',
                                            value ? parseInt(value) : null,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona una cuenta" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accounts.map((account) => (
                                            <SelectItem
                                                key={account.id}
                                                value={account.id.toString()}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <span>{account.name}</span>
                                                    {account.is_default && (
                                                        <span className="text-muted-foreground text-xs">
                                                            (Por defecto)
                                                        </span>
                                                    )}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={errors.linked_account_id}
                                />
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="last_four_digits">
                                Últimos 4 dígitos (opcional)
                            </Label>
                            <Input
                                id="last_four_digits"
                                value={data.last_four_digits ?? ''}
                                onChange={(e) =>
                                    setData(
                                        'last_four_digits',
                                        e.target.value.slice(0, 4),
                                    )
                                }
                                placeholder="1234"
                                maxLength={4}
                            />
                            <InputError message={errors.last_four_digits} />
                        </div>

                        {isCreditCard && (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="credit_limit">
                                        Límite de Crédito
                                    </Label>
                                    <Input
                                        id="credit_limit"
                                        type="number"
                                        step="0.01"
                                        value={data.credit_limit ?? ''}
                                        onChange={(e) =>
                                            setData(
                                                'credit_limit',
                                                e.target.value
                                                    ? parseFloat(e.target.value)
                                                    : null,
                                            )
                                        }
                                        placeholder="0"
                                    />
                                    <InputError message={errors.credit_limit} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="billing_cycle_day">
                                            Día de Facturación
                                        </Label>
                                        <Input
                                            id="billing_cycle_day"
                                            type="number"
                                            min={1}
                                            max={31}
                                            value={data.billing_cycle_day ?? ''}
                                            onChange={(e) =>
                                                setData(
                                                    'billing_cycle_day',
                                                    e.target.value
                                                        ? parseInt(
                                                              e.target.value,
                                                          )
                                                        : null,
                                                )
                                            }
                                            placeholder="15"
                                        />
                                        <InputError
                                            message={errors.billing_cycle_day}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="payment_due_day">
                                            Día de Pago
                                        </Label>
                                        <Input
                                            id="payment_due_day"
                                            type="number"
                                            min={1}
                                            max={31}
                                            value={data.payment_due_day ?? ''}
                                            onChange={(e) =>
                                                setData(
                                                    'payment_due_day',
                                                    e.target.value
                                                        ? parseInt(
                                                              e.target.value,
                                                          )
                                                        : null,
                                                )
                                            }
                                            placeholder="1"
                                        />
                                        <InputError
                                            message={errors.payment_due_day}
                                        />
                                    </div>
                                </div>
                            </>
                        )}

                        <div className="space-y-3">
                            <div className="flex items-center justify-between rounded-lg border px-3 py-2">
                                <Label
                                    htmlFor="is_active"
                                    className="text-sm font-medium"
                                >
                                    Método activo
                                </Label>
                                <Switch
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', checked === true)
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between rounded-lg border px-3 py-2">
                                <Label
                                    htmlFor="is_default"
                                    className="text-sm font-medium"
                                >
                                    Método por defecto
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
