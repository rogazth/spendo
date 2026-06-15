import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { TransactionFormDialog } from '@/components/forms/transaction-form-dialog';
import type { Account, Category } from '@/types';

interface CreateTransactionFormData {
    accounts: Account[];
    categories: Category[];
}

/**
 * Module-level open subscribers and lazy data cache. Decoupling the trigger
 * from React context lets any screen open the dialog — including page
 * components that render the layout (and therefore sit above the provider in
 * the tree) — without a context-ancestor requirement. The fetched payload is
 * cached across navigations so it loads at most once per session.
 */
const openListeners = new Set<() => void>();
let cachedFormData: CreateTransactionFormData | null = null;

function loadCreateTransactionData(): Promise<CreateTransactionFormData> {
    if (cachedFormData) {
        return Promise.resolve(cachedFormData);
    }

    return fetch('/transactions/create-data', {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('request failed');
            }
            return response.json() as Promise<CreateTransactionFormData>;
        })
        .then((payload) => {
            cachedFormData = payload;
            return payload;
        });
}

/**
 * Owns the global "new transaction" dialog so it can be triggered from any
 * screen (e.g. the mobile bottom toolbar or a page header button). Mounted once
 * inside the authenticated layout.
 */
export function CreateTransactionProvider({ children }: { children: ReactNode }) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [data, setData] = useState<CreateTransactionFormData | null>(
        cachedFormData,
    );
    const [loading, setLoading] = useState(false);

    const open = useCallback(() => {
        setDialogOpen(true);

        if (cachedFormData) {
            setData(cachedFormData);
            return;
        }

        setLoading(true);
        loadCreateTransactionData()
            .then((payload) => setData(payload))
            .catch(() =>
                toast.error('No se pudieron cargar las cuentas y categorías.'),
            )
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => {
        openListeners.add(open);
        return () => {
            openListeners.delete(open);
        };
    }, [open]);

    return (
        <>
            {children}
            <TransactionFormDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                accounts={data?.accounts ?? []}
                categories={data?.categories ?? []}
                loading={loading && !data}
            />
        </>
    );
}

/**
 * Returns a stable `open` function that triggers the global create dialog.
 * Safe to call from anywhere — it does not require a context ancestor.
 */
export function useCreateTransaction(): { open: () => void } {
    return {
        open: () => openListeners.forEach((listener) => listener()),
    };
}
