import { Head, useForm, Link } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/input-error';
import type { BreadcrumbItem, AccountType } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cuentas', href: '/accounts' },
    { title: 'Nueva Cuenta', href: '/accounts/create' },
];

const accountTypes: { value: AccountType; label: string }[] = [
    { value: 'checking', label: 'Cuenta Corriente' },
    { value: 'savings', label: 'Cuenta de Ahorro' },
    { value: 'cash', label: 'Efectivo' },
    { value: 'investment', label: 'Inversión' },
];

const defaultColors = [
    '#EF4444', '#F97316', '#F59E0B', '#84CC16', '#22C55E',
    '#10B981', '#14B8A6', '#06B6D4', '#0EA5E9', '#3B82F6',
    '#6366F1', '#8B5CF6', '#A855F7', '#D946EF', '#EC4899',
];

export default function AccountCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'checking' as AccountType,
        currency: 'CLP',
        initial_balance: 0,
        color: '#3B82F6',
        icon: 'wallet',
        is_active: true,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/accounts');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Cuenta" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="mx-auto w-full max-w-2xl">
                    <Card>
                        <CardHeader>
                            <CardTitle>Nueva Cuenta</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Nombre</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Ej: Cuenta Corriente Banco Estado"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="type">Tipo de Cuenta</Label>
                                    <Select
                                        value={data.type}
                                        onValueChange={(value: AccountType) => setData('type', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Selecciona un tipo" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {accountTypes.map((type) => (
                                                <SelectItem key={type.value} value={type.value}>
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.type} />
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="currency">Moneda</Label>
                                        <Select
                                            value={data.currency}
                                            onValueChange={(value) => setData('currency', value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Selecciona moneda" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="CLP">CLP - Peso Chileno</SelectItem>
                                                <SelectItem value="USD">USD - Dólar</SelectItem>
                                                <SelectItem value="EUR">EUR - Euro</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.currency} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="initial_balance">Balance Inicial</Label>
                                        <Input
                                            id="initial_balance"
                                            type="number"
                                            value={data.initial_balance}
                                            onChange={(e) => setData('initial_balance', parseInt(e.target.value) || 0)}
                                            placeholder="0"
                                        />
                                        <p className="text-muted-foreground text-xs">
                                            Ingresa el monto en la moneda seleccionada
                                        </p>
                                        <InputError message={errors.initial_balance} />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label>Color</Label>
                                    <div className="flex flex-wrap gap-2">
                                        {defaultColors.map((color) => (
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

                                <div className="flex justify-end gap-4">
                                    <Button variant="outline" asChild>
                                        <Link href="/accounts">Cancelar</Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        Crear Cuenta
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
