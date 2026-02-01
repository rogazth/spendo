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
import type { BreadcrumbItem, Category, CategoryType } from '@/types';

interface Props {
    parentCategories: Category[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Categorías', href: '/categories' },
    { title: 'Nueva Categoría', href: '/categories/create' },
];

const categoryTypes: { value: CategoryType; label: string }[] = [
    { value: 'expense', label: 'Gasto' },
    { value: 'income', label: 'Ingreso' },
];

const defaultColors = [
    '#EF4444', '#F97316', '#F59E0B', '#84CC16', '#22C55E',
    '#10B981', '#14B8A6', '#06B6D4', '#0EA5E9', '#3B82F6',
    '#6366F1', '#8B5CF6', '#A855F7', '#D946EF', '#EC4899',
];

export default function CategoryCreate({ parentCategories }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        parent_id: null as number | null,
        name: '',
        type: 'expense' as CategoryType,
        icon: 'tag',
        color: '#3B82F6',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/categories');
    };

    const filteredParents = parentCategories.filter((cat) => cat.type === data.type);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Categoría" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="mx-auto w-full max-w-2xl">
                    <Card>
                        <CardHeader>
                            <CardTitle>Nueva Categoría</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="type">Tipo</Label>
                                    <Select
                                        value={data.type}
                                        onValueChange={(value: CategoryType) => {
                                            setData('type', value);
                                            setData('parent_id', null);
                                        }}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Selecciona un tipo" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categoryTypes.map((type) => (
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
                                        placeholder="Ej: Alimentación"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="parent_id">Categoría Padre (opcional)</Label>
                                    <Select
                                        value={data.parent_id?.toString() || 'none'}
                                        onValueChange={(value) =>
                                            setData('parent_id', value === 'none' ? null : parseInt(value))
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Sin categoría padre" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">Sin categoría padre</SelectItem>
                                            {filteredParents.map((category) => (
                                                <SelectItem key={category.id} value={category.id.toString()}>
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className="h-3 w-3 rounded-full"
                                                            style={{ backgroundColor: category.color }}
                                                        />
                                                        {category.name}
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-muted-foreground text-xs">
                                        Selecciona una categoría padre para crear una subcategoría
                                    </p>
                                    <InputError message={errors.parent_id} />
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
                                        <Link href="/categories">Cancelar</Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        Crear Categoría
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
