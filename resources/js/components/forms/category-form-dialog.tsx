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
import type { Category, CategoryType } from '@/types';
import { CATEGORY_TYPES } from '@/types';
import { DEFAULT_COLORS } from '@/constants/colors';

interface CategoryFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    category?: Category;
    parentCategories: Category[];
}

const categoryTypes = CATEGORY_TYPES.filter((t) => t.id !== 'system');

export function CategoryFormDialog({
    open,
    onOpenChange,
    category,
    parentCategories,
}: CategoryFormDialogProps) {
    const isEditing = !!category;

    const { data, setData, post, put, processing, errors, reset } = useForm<{
        parent_id: number | null;
        name: string;
        type: CategoryType;
        icon: string;
        color: string;
    }>({
        parent_id: null,
        name: '',
        type: 'expense',
        icon: 'tag',
        color: '#3B82F6',
    });

    useEffect(() => {
        if (open) {
            setData({
                parent_id: category?.parent_id ?? null,
                name: category?.name ?? '',
                type: category?.type ?? 'expense',
                icon: category?.icon ?? 'tag',
                color: category?.color ?? '#3B82F6',
            });
        }
    }, [open, category]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
                toast.success(
                    isEditing ? 'Categoría actualizada' : 'Categoría creada',
                );
            },
            onError: () => {
                toast.error('Error al guardar la categoría');
            },
        };

        if (isEditing) {
            put(`/categories/${category.uuid}`, options);
        } else {
            post('/categories', options);
        }
    };

    const handleOpenChange = (open: boolean) => {
        onOpenChange(open);
    };

    const filteredParents = parentCategories.filter(
        (cat) => cat.type === data.type && cat.id !== category?.id,
    );

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {isEditing ? 'Editar Categoría' : 'Nueva Categoría'}
                        </DialogTitle>
                        <DialogDescription>
                            {isEditing
                                ? 'Actualiza los datos de la categoría.'
                                : 'Crea una nueva categoría para organizar tus gastos e ingresos.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
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
                                placeholder="Ej: Alimentación"
                            />
                            <InputError message={errors.name} />
                        </div>

                        {filteredParents.length > 0 && (
                            <div className="space-y-2">
                                <Label htmlFor="parent_id">
                                    Categoría Padre (opcional)
                                </Label>
                                <Select
                                    value={data.parent_id?.toString() || 'none'}
                                    onValueChange={(value) =>
                                        setData(
                                            'parent_id',
                                            value === 'none'
                                                ? null
                                                : parseInt(value),
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Sin categoría padre" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Sin categoría padre
                                        </SelectItem>
                                        {filteredParents.map((cat) => (
                                            <SelectItem
                                                key={cat.id}
                                                value={cat.id.toString()}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className="h-3 w-3 rounded-full"
                                                        style={{
                                                            backgroundColor:
                                                                cat.color,
                                                        }}
                                                    />
                                                    {cat.name}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.parent_id} />
                            </div>
                        )}

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
