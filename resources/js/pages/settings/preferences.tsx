import { Head, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editUserSettings } from '@/routes/user-settings';
import type { BreadcrumbItem, UserSettings } from '@/types';

interface Props {
    settings: UserSettings;
    currencies: { value: string; label: string }[];
    timezones: { value: string; label: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Preferencias',
        href: editUserSettings().url,
    },
];

export default function Preferences({ settings, currencies, timezones }: Props) {
    const { data, setData, patch, errors, processing } = useForm({
        default_currency: settings.default_currency ?? 'CLP',
        budget_cycle_start_day: settings.budget_cycle_start_day ?? 1,
        timezone: settings.timezone ?? 'America/Santiago',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch('/settings/preferences', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Preferencias actualizadas');
            },
            onError: () => {
                toast.error('Error al actualizar las preferencias');
            },
        });
    };

    const dayOptions = Array.from({ length: 28 }, (_, i) => i + 1);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Preferencias" />

            <h1 className="sr-only">Preferencias</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Preferencias"
                        description="Configura tu moneda, zona horaria y ciclo de presupuesto"
                    />

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-2">
                            <Label htmlFor="default_currency">Moneda predeterminada</Label>
                            <Select
                                value={data.default_currency}
                                onValueChange={(value) => setData('default_currency', value)}
                            >
                                <SelectTrigger id="default_currency" className="w-full">
                                    <SelectValue placeholder="Selecciona una moneda" />
                                </SelectTrigger>
                                <SelectContent>
                                    {currencies.map((currency) => (
                                        <SelectItem key={currency.value} value={currency.value}>
                                            {currency.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.default_currency} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="timezone">Zona horaria</Label>
                            <Select
                                value={data.timezone}
                                onValueChange={(value) => setData('timezone', value)}
                            >
                                <SelectTrigger id="timezone" className="w-full">
                                    <SelectValue placeholder="Selecciona una zona horaria" />
                                </SelectTrigger>
                                <SelectContent>
                                    {timezones.map((tz) => (
                                        <SelectItem key={tz.value} value={tz.value}>
                                            {tz.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.timezone} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="budget_cycle_start_day">Día de inicio del ciclo de presupuesto</Label>
                            <Select
                                value={String(data.budget_cycle_start_day)}
                                onValueChange={(value) => setData('budget_cycle_start_day', parseInt(value))}
                            >
                                <SelectTrigger id="budget_cycle_start_day" className="w-full">
                                    <SelectValue placeholder="Selecciona un día" />
                                </SelectTrigger>
                                <SelectContent>
                                    {dayOptions.map((day) => (
                                        <SelectItem key={day} value={String(day)}>
                                            Día {day}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-muted-foreground text-sm">
                                El día del mes en que comienza tu ciclo de presupuesto mensual.
                            </p>
                            <InputError message={errors.budget_cycle_start_day} />
                        </div>

                        <Button type="submit" disabled={processing}>
                            {processing ? 'Guardando...' : 'Guardar preferencias'}
                        </Button>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
