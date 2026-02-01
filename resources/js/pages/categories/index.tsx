import { Head, Link, router } from '@inertiajs/react';
import { Plus, MoreHorizontal, Pencil, Trash2, ChevronRight } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { BreadcrumbItem, Category } from '@/types';

interface Props {
    expenseCategories: Category[];
    incomeCategories: Category[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Categorías', href: '/categories' },
];

function getCategoryTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        expense: 'Gasto',
        income: 'Ingreso',
        system: 'Sistema',
    };
    return labels[type] || type;
}

function CategoryItem({ category, onDelete }: { category: Category; onDelete: (cat: Category) => void }) {
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
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem asChild>
                                <Link href={`/categories/${category.uuid}/edit`}>
                                    <Pencil className="mr-2 h-4 w-4" />
                                    Editar
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => onDelete(category)}
                                className="text-destructive"
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
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
                                <ChevronRight className="text-muted-foreground h-4 w-4" />
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
                                            <MoreHorizontal className="h-3 w-3" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem asChild>
                                            <Link href={`/categories/${child.uuid}/edit`}>
                                                <Pencil className="mr-2 h-4 w-4" />
                                                Editar
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onClick={() => onDelete(child)}
                                            className="text-destructive"
                                        >
                                            <Trash2 className="mr-2 h-4 w-4" />
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

export default function CategoriesIndex({ expenseCategories, incomeCategories }: Props) {
    const handleDelete = (category: Category) => {
        if (confirm(`¿Estás seguro de eliminar la categoría "${category.name}"?`)) {
            router.delete(`/categories/${category.uuid}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categorías" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Categorías</h1>
                    <Button asChild>
                        <Link href="/categories/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Nueva Categoría
                        </Link>
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
                                        onDelete={handleDelete}
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
                                        onDelete={handleDelete}
                                    />
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
