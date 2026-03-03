import { Head } from '@inertiajs/react';
import { CreditCardIcon } from 'lucide-react';
import { useMemo } from 'react';
import { getInstrumentColumns } from '@/components/data-table/columns/instrument-columns';
import { DataTable } from '@/components/data-table/data-table';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Instrument } from '@/types';

interface Props {
    instruments: Instrument[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Instrumentos', href: '/instruments' },
];

export default function InstrumentsIndex({ instruments }: Props) {
    const columns = useMemo(() => getInstrumentColumns(), []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Instrumentos" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-bold text-balance">Instrumentos</h1>

                {instruments.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <CreditCardIcon />
                            </EmptyMedia>
                            <EmptyTitle>No tienes instrumentos registrados</EmptyTitle>
                            <EmptyDescription>
                                Los instrumentos se crean mediante el asistente de IA.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <DataTable
                        columns={columns}
                        data={instruments}
                        searchKey="name"
                        searchPlaceholder="Buscar instrumento..."
                    />
                )}
            </div>
        </AppLayout>
    );
}
