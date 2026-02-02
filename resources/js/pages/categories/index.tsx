import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { PlusIcon, MoreHorizontalIcon, PencilIcon, Trash2Icon, ChevronRightIcon } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { CategoryFormDialog } from '@/components/forms/category-form-dialog';
import type { BreadcrumbItem, Category } from '@/types';

interface Props {
    expenseCategories: Category[];
    incomeCategories: Category[];
    parentCategories: Category[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Categorías', href: '/categories' },
];

function CategoryItem({
    category,
    onEdit,
    onDelete,
}: {
    category: Category;
    onEdit: (cat: Category) => void;
    onDelete: (cat: Category) => void;
}) {
    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between rounded-lg border p-3">
                <div className="flex items-center gap-3">
                    <span
                        className="flex h-8 w-8 items-center justify-center rounded-full"
                        style={{ backgroundColor: category.color + '20' }}
                    >
                        <span
                            className="h-4 w-4 rounded-full"
                            style={{ backgroundColor: category.color }}
                        />
                    </span>
                    <div>
                        <p className="font-medium">{category.name}</p>
                        {category.is_system && (
                            <span className="text-muted-foreground text-xs">Sistema</span>
                        )}
                    </div>
                </div>
                {!category.is_system && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon">
                                <MoreHorizontalIcon className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => onEdit(category)}>
                                <PencilIcon className="h-4 w-4" />
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => onDelete(category)}>
                                <Trash2Icon className="h-4 w-4" />
                                Eliminar
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
            {category.children && category.children.length > 0 && (
                <div className="ml-6 space-y-2">
                    {category.children.map((child) => (
                        <div
                            key={child.uuid}
                            className="flex items-center justify-between rounded-lg border border-dashed p-2"
                        >
                            <div className="flex items-center gap-2">
                                <ChevronRightIcon className="text-muted-foreground h-4 w-4" />
                                <span
                                    className="h-3 w-3 rounded-full"
                                    style={{ backgroundColor: child.color }}
                                />
                                <span className="text-sm">{child.name}</span>
                            </div>
                            {!child.is_system && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" size="icon" className="h-8 w-8">
                                            <MoreHorizontalIcon className="h-3 w-3" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem onClick={() => onEdit(child)}>
                                            <PencilIcon className="h-4 w-4" />
                                            Editar
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => onDelete(child)}>
                                            <Trash2Icon className="h-4 w-4" />
                                            Eliminar
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function CategoriesIndex({ expenseCategories, incomeCategories, parentCategories }: Props) {
    const [formDialogOpen, setFormDialogOpen] = useState(false);
    const [editingCategory, setEditingCategory] = useState<Category | undefined>();
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deletingCategory, setDeletingCategory] = useState<Category | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleCreate = () => {
        setEditingCategory(undefined);
        setFormDialogOpen(true);
    };

    const handleEdit = (category: Category) => {
        setEditingCategory(category);
        setFormDialogOpen(true);
    };

    const handleDeleteClick = (category: Category) => {
        setDeletingCategory(category);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (!deletingCategory) return;

        setIsDeleting(true);
        router.delete(`/categories/${deletingCategory.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteDialogOpen(false);
                toast.success('Categoría eliminada');
            },
            onError: () => {
                toast.error('Error al eliminar la categoría');
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    const handleDeleteDialogOpenChange = (open: boolean) => {
        setDeleteDialogOpen(open);
        if (!open) {
            setDeletingCategory(null);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categorías" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Categorías</h1>
                    <Button onClick={handleCreate}>
                        <PlusIcon className="h-4 w-4" />
                        Nueva Categoría
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-red-500" />
                                Gastos
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {expenseCategories.length === 0 ? (
                                <p className="text-muted-foreground py-4 text-center text-sm">
                                    No hay categorías de gastos
                                </p>
                            ) : (
                                expenseCategories.map((category) => (
                                    <CategoryItem
                                        key={category.uuid}
                                        category={category}
                                        onEdit={handleEdit}
                                        onDelete={handleDeleteClick}
                                    />
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-green-500" />
                                Ingresos
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {incomeCategories.length === 0 ? (
                                <p className="text-muted-foreground py-4 text-center text-sm">
                                    No hay categorías de ingresos
                                </p>
                            ) : (
                                incomeCategories.map((category) => (
                                    <CategoryItem
                                        key={category.uuid}
                                        category={category}
                                        onEdit={handleEdit}
                                        onDelete={handleDeleteClick}
                                    />
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <CategoryFormDialog
                open={formDialogOpen}
                onOpenChange={setFormDialogOpen}
                category={editingCategory}
                parentCategories={parentCategories}
            />

            <ConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={handleDeleteDialogOpenChange}
                title="Eliminar categoría"
                description={
                    <>
                        ¿Estás seguro de eliminar la categoría <span className="font-semibold">{deletingCategory?.name}</span>? Esta acción no se puede deshacer.
                    </>
                }
                confirmLabel="Eliminar"
                variant="destructive"
                onConfirm={handleDeleteConfirm}
                loading={isDeleting}
            />
        </AppLayout>
    );
}
