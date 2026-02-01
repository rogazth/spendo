import { Head, useForm, Link } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/input-error';
import type { BreadcrumbItem, Account, PaymentMethod, PaymentMethodType } from '@/types';

interface Props {
    paymentMethod: PaymentMethod;
    accounts: Account[];
}

const paymentMethodTypes: { value: PaymentMethodType; label: string }[] = [
    { value: 'credit_card', label: 'Tarjeta de Crédito' },
    { value: 'debit_card', label: 'Tarjeta de Débito' },
    { value: 'prepaid_card', label: 'Tarjeta Prepago' },
    { value: 'cash', label: 'Efectivo' },
    { value: 'transfer', label: 'Transferencia' },
];

export default function PaymentMethodEdit({ paymentMethod, accounts }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Métodos de Pago', href: '/payment-methods' },
        { title: paymentMethod.name, href: `/payment-methods/${paymentMethod.uuid}` },
        { title: 'Editar', href: `/payment-methods/${paymentMethod.uuid}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        linked_account_id: paymentMethod.linked_account_id,
        name: paymentMethod.name,
        type: paymentMethod.type,
        last_four_digits: paymentMethod.last_four_digits || '',
        credit_limit: paymentMethod.credit_limit,
        billing_cycle_day: paymentMethod.billing_cycle_day,
        payment_due_day: paymentMethod.payment_due_day,
        is_active: paymentMethod.is_active,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/payment-methods/${paymentMethod.uuid}`);
    };

    const isCreditCard = data.type === 'credit_card';
    const requiresLinkedAccount = ['debit_card', 'prepaid_card', 'cash', 'transfer'].includes(data.type);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${paymentMethod.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="mx-auto w-full max-w-2xl">
                    <Card>
                        <CardHeader>
                            <CardTitle>Editar Método de Pago</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
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
                                            {paymentMethodTypes.map((type) => (
                                                <SelectItem key={type.value} value={type.value}>
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
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Ej: Visa Banco Estado"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                {requiresLinkedAccount && (
                                    <div className="space-y-2">
                                        <Label htmlFor="linked_account_id">Cuenta Vinculada</Label>
                                        <Select
                                            value={data.linked_account_id?.toString() || ''}
                                            onValueChange={(value) =>
                                                setData('linked_account_id', value ? parseInt(value) : null)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Selecciona una cuenta" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {accounts.map((account) => (
                                                    <SelectItem key={account.id} value={account.id.toString()}>
                                                        {account.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.linked_account_id} />
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="last_four_digits">Últimos 4 dígitos (opcional)</Label>
                                    <Input
                                        id="last_four_digits"
                                        value={data.last_four_digits}
                                        onChange={(e) => setData('last_four_digits', e.target.value.slice(0, 4))}
                                        placeholder="1234"
                                        maxLength={4}
                                    />
                                    <InputError message={errors.last_four_digits} />
                                </div>

                                {isCreditCard && (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor="credit_limit">Límite de Crédito</Label>
                                            <Input
                                                id="credit_limit"
                                                type="number"
                                                value={data.credit_limit || ''}
                                                onChange={(e) =>
                                                    setData('credit_limit', e.target.value ? parseInt(e.target.value) : null)
                                                }
                                                placeholder="0"
                                            />
                                            <InputError message={errors.credit_limit} />
                                        </div>

                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="billing_cycle_day">Día de Facturación</Label>
                                                <Input
                                                    id="billing_cycle_day"
                                                    type="number"
                                                    min={1}
                                                    max={31}
                                                    value={data.billing_cycle_day || ''}
                                                    onChange={(e) =>
                                                        setData('billing_cycle_day', e.target.value ? parseInt(e.target.value) : null)
                                                    }
                                                    placeholder="15"
                                                />
                                                <InputError message={errors.billing_cycle_day} />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="payment_due_day">Día de Pago</Label>
                                                <Input
                                                    id="payment_due_day"
                                                    type="number"
                                                    min={1}
                                                    max={31}
                                                    value={data.payment_due_day || ''}
                                                    onChange={(e) =>
                                                        setData('payment_due_day', e.target.value ? parseInt(e.target.value) : null)
                                                    }
                                                    placeholder="1"
                                                />
                                                <InputError message={errors.payment_due_day} />
                                            </div>
                                        </div>
                                    </>
                                )}

                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="is_active"
                                        checked={data.is_active}
                                        onCheckedChange={(checked) => setData('is_active', checked === true)}
                                    />
                                    <Label htmlFor="is_active" className="cursor-pointer">
                                        Método activo
                                    </Label>
                                </div>

                                <div className="flex justify-end gap-4">
                                    <Button variant="outline" asChild>
                                        <Link href="/payment-methods">Cancelar</Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        Guardar Cambios
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
