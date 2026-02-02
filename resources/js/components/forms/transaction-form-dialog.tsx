import { useForm, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { CalendarIcon } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { MoneyInput } from '@/components/ui/money-input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { getCurrencyLocale } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type {
    Account,
    Category,
    Currency,
    PaymentMethod,
    Transaction,
    TransactionType,
} from '@/types';

interface TransactionFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transaction?: Transaction;
    accounts: Account[];
    paymentMethods: PaymentMethod[];
    categories: Category[];
}

export function TransactionFormDialog({
    open,
    onOpenChange,
    transaction,
    accounts,
    paymentMethods,
    categories,
}: TransactionFormDialogProps) {
    const isEditing = !!transaction;
    const { currencies = [] } = usePage<{ currencies?: Currency[] }>().props;

    const defaultAccount = accounts.find(a => a.is_default) ?? accounts[0];
    const defaultPaymentMethod = paymentMethods.find(p => p.is_default) ?? paymentMethods[0];
    const fallbackDestinationAccount = accounts.find(
        (account) => account.id !== defaultAccount?.id,
    );

    const { data, setData, post, put, processing, errors, reset, transform } = useForm<{
        account_id: number | null;
        origin_account_id: number | null;
        destination_account_id: number | null;
        payment_method_id: number | null;
        category_id: number | null;
        type: TransactionType;
        amount: number | null;
        currency: string;
        description: string;
        transaction_date: Date;
        attachments: File[];
    }>({
        account_id: null,
        origin_account_id: null,
        destination_account_id: null,
        payment_method_id: null,
        category_id: null,
        type: 'expense',
        amount: null,
        currency: 'CLP',
        description: '',
        transaction_date: new Date(),
        attachments: [],
    });

    useEffect(() => {
        if (open) {
            const transferOrigin =
                transaction?.type === 'transfer_out'
                    ? transaction.account_id
                    : transaction?.linked_transaction?.account_id ?? defaultAccount?.id ?? null;
            const transferDestination =
                transaction?.type === 'transfer_in'
                    ? transaction.account_id
                    : transaction?.linked_transaction?.account_id ??
                      fallbackDestinationAccount?.id ??
                      null;

            setData({
                account_id: transaction?.account_id ?? defaultAccount?.id ?? null,
                origin_account_id: transferOrigin ?? defaultAccount?.id ?? null,
                destination_account_id:
                    transferDestination ??
                    fallbackDestinationAccount?.id ??
                    null,
                payment_method_id: transaction?.payment_method_id ?? defaultPaymentMethod?.id ?? null,
                category_id: transaction?.category_id ?? null,
                type:
                    transaction?.type === 'expense' ||
                    transaction?.type === 'income'
                        ? transaction.type
                        : transaction?.type === 'transfer_out' ||
                            transaction?.type === 'transfer_in'
                            ? 'transfer'
                            : 'expense',
                amount: transaction?.amount ?? null,
                currency: transaction?.currency ?? 'CLP',
                description: transaction?.description ?? '',
                transaction_date: transaction?.transaction_date
                    ? new Date(transaction.transaction_date)
                    : new Date(),
                attachments: [],
            });
        }
    }, [open, transaction]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
                toast.success(
                    isEditing
                        ? 'Transacción actualizada'
                        : 'Transacción creada',
                );
            },
            onError: () => {
                toast.error('Error al guardar la transacción');
            },
        };

        // Transform the data before submitting
        transform((formData) => ({
            ...formData,
            transaction_date: format(formData.transaction_date, 'yyyy-MM-dd'),
            payment_method_id:
                formData.type === 'income' || formData.type === 'transfer'
                    ? null
                    : formData.payment_method_id,
            category_id:
                formData.type === 'transfer' ? null : formData.category_id,
            account_id:
                formData.type === 'transfer' ? null : formData.account_id,
            description: formData.description || null,
            attachments: formData.attachments,
        }));

        if (isEditing) {
            put(`/transactions/${transaction.uuid}`, options);
        } else {
            post('/transactions', options);
        }
    };

    const handleOpenChange = (open: boolean) => {
        onOpenChange(open);
    };

    const handleTypeChange = (type: TransactionType) => {
        setData('type', type);
        setData('category_id', null);
        // Clear payment method for income
        if (type === 'income') {
            setData('payment_method_id', null);
        } else if (type === 'transfer') {
            setData('payment_method_id', null);
            if (!data.origin_account_id) {
                setData('origin_account_id', defaultAccount?.id ?? null);
            }
            if (!data.destination_account_id) {
                setData(
                    'destination_account_id',
                    fallbackDestinationAccount?.id ??
                        defaultAccount?.id ??
                        null,
                );
            }
        } else if (!data.payment_method_id && paymentMethods.length > 0) {
            setData('payment_method_id', paymentMethods[0].id);
        }
    };

    const filteredCategories = categories.flatMap((cat) => {
        const matchesType =
            (data.type === 'expense' && cat.type === 'expense') ||
            (data.type === 'income' && cat.type === 'income');

        if (!matchesType) return [];

        const children = (
            cat.children?.filter((child) => child.type === cat.type) ?? []
        ).map((child) => ({ ...child, depth: 1 as const }));

        return [{ ...cat, depth: 0 as const }, ...children];
    });

    const isExpense = data.type === 'expense';
    const isTransfer = data.type === 'transfer';
    const originAccount = accounts.find(
        (account) => account.id === data.origin_account_id,
    );
    const selectedAccount = accounts.find(
        (account) => account.id === data.account_id,
    );
    const destinationAccounts = useMemo(
        () =>
            accounts.filter(
                (account) => account.id !== data.origin_account_id,
            ),
        [accounts, data.origin_account_id],
    );

    useEffect(() => {
        if (isTransfer) {
            setData('currency', originAccount?.currency ?? 'CLP');
            return;
        }
        if (selectedAccount?.currency) {
            setData('currency', selectedAccount.currency);
        }
    }, [isTransfer, originAccount?.currency, selectedAccount?.currency]);

    useEffect(() => {
        if (!isTransfer) return;
        if (
            data.origin_account_id &&
            data.destination_account_id === data.origin_account_id
        ) {
            const nextDestination = accounts.find(
                (account) => account.id !== data.origin_account_id,
            );
            setData('destination_account_id', nextDestination?.id ?? null);
        }
    }, [
        accounts,
        data.destination_account_id,
        data.origin_account_id,
        isTransfer,
    ]);

    const currencyLocale = isTransfer
        ? originAccount?.currency_locale ??
          getCurrencyLocale(originAccount?.currency ?? data.currency, currencies)
        : selectedAccount?.currency_locale ??
          getCurrencyLocale(data.currency, currencies);

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {isEditing
                                ? 'Editar Transacción'
                                : 'Nueva Transacción'}
                        </DialogTitle>
                        <DialogDescription>
                            {isEditing
                                ? 'Actualiza los datos de la transacción.'
                                : 'Registra un nuevo gasto o ingreso.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="space-y-2">
                            <Label>Tipo</Label>
                            <Tabs
                                value={data.type}
                                onValueChange={(value) => {
                                    if (
                                        value === 'expense' ||
                                        value === 'income' ||
                                        value === 'transfer'
                                    ) {
                                        handleTypeChange(value);
                                    }
                                }}
                            >
                                <TabsList className="w-full">
                                    <TabsTrigger value="expense" className="flex-1">
                                        Gasto
                                    </TabsTrigger>
                                    <TabsTrigger value="income" className="flex-1">
                                        Ingreso
                                    </TabsTrigger>
                                    <TabsTrigger value="transfer" className="flex-1">
                                        Transferencia
                                    </TabsTrigger>
                                </TabsList>
                            </Tabs>
                            <InputError message={errors.type} />
                        </div>

                        {!isTransfer && (
                            <div className="space-y-2">
                                <Label htmlFor="account_id">Cuenta</Label>
                                <Select
                                    value={data.account_id?.toString() || ''}
                                    onValueChange={(value) =>
                                        setData(
                                            'account_id',
                                            value ? parseInt(value) : null,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona una cuenta" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accounts.map((account) => (
                                            <SelectItem
                                                key={account.id}
                                                value={account.id.toString()}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <span>{account.name}</span>
                                                    {account.is_default && (
                                                        <span className="text-muted-foreground text-xs">
                                                            (Por defecto)
                                                        </span>
                                                    )}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.account_id} />
                            </div>
                        )}

                        {isTransfer && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="origin_account_id">
                                        Cuenta origen
                                    </Label>
                                    <Select
                                        value={
                                            data.origin_account_id?.toString() ||
                                            ''
                                        }
                                        onValueChange={(value) => {
                                            const nextOrigin = value
                                                ? parseInt(value)
                                                : null;
                                            setData(
                                                'origin_account_id',
                                                nextOrigin,
                                            );
                                            if (
                                                nextOrigin &&
                                                nextOrigin ===
                                                    data.destination_account_id
                                            ) {
                                                const nextDestination = accounts.find(
                                                    (account) =>
                                                        account.id !==
                                                        nextOrigin,
                                                );
                                                setData(
                                                    'destination_account_id',
                                                    nextDestination?.id ??
                                                        null,
                                                );
                                            }
                                        }}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Selecciona una cuenta" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {accounts.map((account) => (
                                                <SelectItem
                                                    key={account.id}
                                                    value={account.id.toString()}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <span>{account.name}</span>
                                                        {account.is_default && (
                                                            <span className="text-muted-foreground text-xs">
                                                                (Por defecto)
                                                            </span>
                                                        )}
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.origin_account_id}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="destination_account_id">
                                        Cuenta destino
                                    </Label>
                                    <Select
                                        value={
                                            data.destination_account_id?.toString() ||
                                            ''
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'destination_account_id',
                                                value ? parseInt(value) : null,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Selecciona una cuenta" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {destinationAccounts.map((account) => (
                                                <SelectItem
                                                    key={account.id}
                                                    value={account.id.toString()}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <span>{account.name}</span>
                                                        {account.is_default && (
                                                            <span className="text-muted-foreground text-xs">
                                                                (Por defecto)
                                                            </span>
                                                        )}
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.destination_account_id}
                                    />
                                </div>
                            </div>
                        )}

                        {isExpense && (
                            <div className="space-y-2">
                                <Label htmlFor="payment_method_id">
                                    Método de Pago
                                </Label>
                                <Select
                                    value={data.payment_method_id?.toString() || ''}
                                    onValueChange={(value) =>
                                        setData(
                                            'payment_method_id',
                                            value ? parseInt(value) : null,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona un método de pago" />
                                    </SelectTrigger>
                                    <SelectContent>
                                    {paymentMethods.map((method) => (
                                        <SelectItem
                                            key={method.id}
                                            value={method.id.toString()}
                                        >
                                            <div className="flex items-center gap-2">
                                                <span>{method.name}</span>
                                                {method.is_default && (
                                                    <span className="text-muted-foreground text-xs">
                                                        (Por defecto)
                                                    </span>
                                                )}
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                                <InputError message={errors.payment_method_id} />
                            </div>
                        )}

                        {!isTransfer && (
                            <div className="space-y-2">
                                <Label htmlFor="category_id">Categoría</Label>
                                <Select
                                    value={data.category_id?.toString() || ''}
                                    onValueChange={(value) =>
                                        setData(
                                            'category_id',
                                            value ? parseInt(value) : null,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona una categoría" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {filteredCategories.map((category) => (
                                            <SelectItem
                                                key={category.id}
                                                value={category.id.toString()}
                                            >
                                                <div
                                                    className={cn(
                                                        'flex items-center gap-2',
                                                        category.depth === 1 &&
                                                            'pl-4',
                                                    )}
                                                >
                                                    <span
                                                        className="h-3 w-3 rounded-full"
                                                        style={{
                                                            backgroundColor:
                                                                category.color,
                                                        }}
                                                    />
                                                    {category.name}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.category_id} />
                            </div>
                        )}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="amount">Monto</Label>
                            <MoneyInput
                                id="amount"
                                currency={data.currency}
                                locale={currencyLocale}
                                value={data.amount}
                                onValueChange={(value) =>
                                    setData('amount', value)
                                }
                                placeholder="0"
                            />
                                <InputError message={errors.amount} />
                            </div>

                            <div className="space-y-2">
                                <Label>Fecha</Label>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className={cn(
                                                'w-full justify-start text-left font-normal',
                                                !data.transaction_date && 'text-muted-foreground'
                                            )}
                                        >
                                            <CalendarIcon className="h-4 w-4" />
                                            {data.transaction_date ? (
                                                format(data.transaction_date, 'PPP', { locale: es })
                                            ) : (
                                                <span>Selecciona una fecha</span>
                                            )}
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-auto p-0" align="start">
                                        <Calendar
                                            mode="single"
                                            selected={data.transaction_date}
                                            onSelect={(date) => date && setData('transaction_date', date)}
                                            initialFocus
                                        />
                                    </PopoverContent>
                                </Popover>
                                <InputError message={errors.transaction_date} />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Descripción (opcional)</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder="Ej: Compra supermercado"
                                rows={2}
                            />
                            <InputError message={errors.description} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="attachments">Adjuntos (opcional)</Label>
                            <input
                                id="attachments"
                                type="file"
                                multiple
                                onChange={(event) =>
                                    setData(
                                        'attachments',
                                        Array.from(event.target.files ?? []),
                                    )
                                }
                                className={cn(
                                    'file:text-foreground text-sm text-muted-foreground',
                                    'file:border-input file:bg-background hover:file:bg-accent file:inline-flex file:h-9 file:cursor-pointer file:items-center file:rounded-md file:border file:px-3 file:font-medium',
                                )}
                            />
                            <InputError message={errors.attachments} />
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
