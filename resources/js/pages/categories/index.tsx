import { Head } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

function CategoryItem({ category }: { category: Category }) {
    return (
        <div className="space-y-2">
            <div className="flex items-center rounded-lg border p-3">
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
            </div>
            {category.children && category.children.length > 0 && (
                <div className="ml-6 space-y-2">
                    {category.children.map((child) => (
                        <div
                            key={child.uuid}
                            className="flex items-center rounded-lg border border-dashed p-2"
                        >
                            <div className="flex items-center gap-2">
                                <ChevronRightIcon className="text-muted-foreground h-4 w-4" />
                                <span
                                    className="h-3 w-3 rounded-full"
                                    style={{ backgroundColor: child.color }}
                                />
                                <span className="text-sm">{child.name}</span>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function CategoriesIndex({ expenseCategories, incomeCategories }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categorías" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-bold">Categorías</h1>

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
