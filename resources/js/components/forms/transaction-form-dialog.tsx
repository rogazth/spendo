import { useForm, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    CalendarIcon,
    PaperclipIcon,
    UploadCloudIcon,
    XIcon,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
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
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { getCurrencyLocale } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type {
    Account,
    Category,
    Currency,
    Instrument,
    Transaction,
    TransactionType,
} from '@/types';

interface TransactionFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transaction?: Transaction;
    accounts: Account[];
    instruments: Instrument[];
    categories: Category[];
}

function formatFileSize(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }
    if (bytes >= 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${bytes} B`;
}

export function TransactionFormDialog({
    open,
    onOpenChange,
    transaction,
    accounts,
    instruments,
    categories,
}: TransactionFormDialogProps) {
    const isEditing = !!transaction;
    const { currencies = [] } = usePage<{ currencies?: Currency[] }>().props;
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [isDropzoneActive, setIsDropzoneActive] = useState(false);

    const defaultAccount = accounts.find((account) => account.is_default) ?? accounts[0];
    const defaultInstrument = instruments.find((instrument) => instrument.is_default) ?? instruments[0];
    const fallbackDestinationAccount = accounts.find(
        (account) => account.id !== defaultAccount?.id,
    );

    const { data, setData, post, put, processing, errors, reset, transform } = useForm<{
        account_id: number | null;
        origin_account_id: number | null;
        destination_account_id: number | null;
        instrument_id: number | null;
        category_id: number | null;
        type: TransactionType;
        amount: number | null;
        currency: string;
        description: string;
        exclude_from_budget: boolean;
        transaction_date: Date;
        attachments: File[];
    }>({
        account_id: null,
        origin_account_id: null,
        destination_account_id: null,
        instrument_id: null,
        category_id: null,
        type: 'expense',
        amount: null,
        currency: 'CLP',
        description: '',
        exclude_from_budget: false,
        transaction_date: new Date(),
        attachments: [],
    });

    useEffect(() => {
        if (!open) {
            return;
        }

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
            instrument_id:
                transaction?.instrument_id ?? defaultInstrument?.id ?? null,
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
            exclude_from_budget: transaction?.exclude_from_budget ?? false,
            transaction_date: transaction?.transaction_date
                ? new Date(transaction.transaction_date)
                : new Date(),
            attachments: [],
        });
    }, [open, transaction]);

    const isExpense = data.type === 'expense';
    const isTransfer = data.type === 'transfer';

    const originAccount = accounts.find(
        (account) => account.id === data.origin_account_id,
    );
    const selectedAccount = accounts.find(
        (account) => account.id === data.account_id,
    );
    const destinationAccounts = useMemo(
        () => accounts.filter((account) => account.id !== data.origin_account_id),
        [accounts, data.origin_account_id],
    );

    const filteredCategories = categories.flatMap((category) => {
        const matchesType =
            (data.type === 'expense' && category.type === 'expense') ||
            (data.type === 'income' && category.type === 'income');

        if (!matchesType) return [];

        const children = (
            category.children?.filter((child) => child.type === category.type) ?? []
        ).map((child) => ({ ...child, depth: 1 as const }));

        return [{ ...category, depth: 0 as const }, ...children];
    });

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

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);
    };

    const handleTypeChange = (type: TransactionType) => {
        setData('type', type);
        setData('category_id', null);

        if (type === 'income') {
            setData('instrument_id', null);
            setData('exclude_from_budget', false);
            return;
        }

        if (type === 'transfer') {
            setData('instrument_id', null);
            setData('exclude_from_budget', false);
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
            return;
        }

        if (!data.instrument_id && instruments.length > 0) {
            setData('instrument_id', instruments[0].id);
        }
    };

    const appendAttachments = (files: File[]) => {
        if (files.length === 0) return;

        const nextAttachments = [...data.attachments, ...files].slice(0, 5);
        setData('attachments', nextAttachments);
    };

    const removeAttachment = (index: number) => {
        setData(
            'attachments',
            data.attachments.filter((_, currentIndex) => currentIndex !== index),
        );
    };

    const handleDrop = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        event.stopPropagation();
        setIsDropzoneActive(false);
        appendAttachments(Array.from(event.dataTransfer.files ?? []));
    };

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
                toast.success(isEditing ? 'Transacción actualizada' : 'Transacción creada');
            },
            onError: () => {
                toast.error('Error al guardar la transacción');
            },
        };

        transform((formData) => ({
            ...formData,
            transaction_date: format(formData.transaction_date, 'yyyy-MM-dd'),
            instrument_id:
                formData.type === 'income' || formData.type === 'transfer'
                    ? null
                    : formData.instrument_id,
            category_id: formData.type === 'transfer' ? null : formData.category_id,
            account_id: formData.type === 'transfer' ? null : formData.account_id,
            exclude_from_budget:
                formData.type === 'expense' ? formData.exclude_from_budget : false,
            description: formData.description || null,
            attachments: formData.attachments,
        }));

        if (isEditing) {
            put(`/transactions/${transaction.uuid}`, options);
            return;
        }

        post('/transactions', options);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-[560px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {isEditing ? 'Editar Transacción' : 'Nueva Transacción'}
                        </DialogTitle>
                        <DialogDescription>
                            {isEditing
                                ? 'Actualiza los datos de la transacción.'
                                : 'Registra un nuevo gasto o ingreso.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="amount" className="text-center text-base">
                                Monto
                            </Label>
                            <MoneyInput
                                id="amount"
                                currency={data.currency}
                                locale={currencyLocale}
                                value={data.amount}
                                onValueChange={(value) => setData('amount', value)}
                                placeholder="0"
                                groupClassName="h-16"
                                addonClassName="text-sm"
                                className="text-center text-4xl font-semibold tracking-tight"
                            />
                            <InputError message={errors.amount} />
                        </div>

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

                        <div className="space-y-2">
                            <Label>Fecha</Label>
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className={cn(
                                            'w-full justify-start text-left font-normal',
                                            !data.transaction_date &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        <CalendarIcon className="h-4 w-4" />
                                        {data.transaction_date ? (
                                            format(data.transaction_date, 'PPP', {
                                                locale: es,
                                            })
                                        ) : (
                                            <span>Selecciona una fecha</span>
                                        )}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0" align="start">
                                    <Calendar
                                        mode="single"
                                        selected={data.transaction_date}
                                        onSelect={(date) =>
                                            date && setData('transaction_date', date)
                                        }
                                        initialFocus
                                    />
                                </PopoverContent>
                            </Popover>
                            <InputError message={errors.transaction_date} />
                        </div>

                        {!isTransfer && (
                            <div className="space-y-2">
                                <Label htmlFor="account_id">Cuenta</Label>
                                <Select
                                    value={data.account_id?.toString() || ''}
                                    onValueChange={(value) =>
                                        setData(
                                            'account_id',
                                            value ? parseInt(value, 10) : null,
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
                                        value={data.origin_account_id?.toString() || ''}
                                        onValueChange={(value) => {
                                            const nextOrigin = value
                                                ? parseInt(value, 10)
                                                : null;
                                            setData('origin_account_id', nextOrigin);

                                            if (
                                                nextOrigin &&
                                                nextOrigin === data.destination_account_id
                                            ) {
                                                const nextDestination = accounts.find(
                                                    (account) =>
                                                        account.id !== nextOrigin,
                                                );
                                                setData(
                                                    'destination_account_id',
                                                    nextDestination?.id ?? null,
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
                                    <InputError message={errors.origin_account_id} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="destination_account_id">
                                        Cuenta destino
                                    </Label>
                                    <Select
                                        value={
                                            data.destination_account_id?.toString() || ''
                                        }
                                        onValueChange={(value) =>
                                            setData(
                                                'destination_account_id',
                                                value ? parseInt(value, 10) : null,
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
                                <Label htmlFor="instrument_id">
                                    Instrumento
                                </Label>
                                <Select
                                    value={data.instrument_id?.toString() || ''}
                                    onValueChange={(value) =>
                                        setData(
                                            'instrument_id',
                                            value ? parseInt(value, 10) : null,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona un instrumento" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {instruments.map((instrument) => (
                                            <SelectItem
                                                key={instrument.id}
                                                value={instrument.id.toString()}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <span>{instrument.name}</span>
                                                    {instrument.is_default && (
                                                        <span className="text-muted-foreground text-xs">
                                                            (Por defecto)
                                                        </span>
                                                    )}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.instrument_id} />
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
                                            value ? parseInt(value, 10) : null,
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
                                                        category.depth === 1 && 'pl-4',
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

                        {isExpense && (
                            <div className="space-y-3">
                                <div className="flex items-center justify-between rounded-lg border px-3 py-2">
                                    <Label
                                        htmlFor="exclude_from_budget"
                                        className="text-sm font-medium"
                                    >
                                        Excluir transacción del budget
                                    </Label>
                                    <Switch
                                        id="exclude_from_budget"
                                        checked={data.exclude_from_budget}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'exclude_from_budget',
                                                checked === true,
                                            )
                                        }
                                    />
                                </div>
                                <InputError message={errors.exclude_from_budget} />
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="description">Descripción (opcional)</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                placeholder="Ej: Compra supermercado"
                                rows={2}
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="attachments">Adjuntos (opcional)</Label>
                            <input
                                ref={fileInputRef}
                                id="attachments"
                                type="file"
                                multiple
                                className="hidden"
                                onChange={(event) => {
                                    appendAttachments(
                                        Array.from(event.target.files ?? []),
                                    );
                                    event.currentTarget.value = '';
                                }}
                            />
                            <div
                                role="button"
                                tabIndex={0}
                                onClick={() => fileInputRef.current?.click()}
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'Enter' ||
                                        event.key === ' '
                                    ) {
                                        event.preventDefault();
                                        fileInputRef.current?.click();
                                    }
                                }}
                                onDragEnter={(event) => {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    setIsDropzoneActive(true);
                                }}
                                onDragOver={(event) => {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    setIsDropzoneActive(true);
                                }}
                                onDragLeave={(event) => {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    setIsDropzoneActive(false);
                                }}
                                onDrop={handleDrop}
                                className={cn(
                                    'cursor-pointer rounded-lg border border-dashed p-4 transition-colors',
                                    isDropzoneActive
                                        ? 'border-primary bg-primary/5'
                                        : 'border-muted-foreground/30 hover:border-primary/40',
                                )}
                            >
                                <div className="flex flex-col items-center gap-2 text-center">
                                    <UploadCloudIcon className="h-5 w-5 text-muted-foreground" />
                                    <p className="text-sm font-medium">
                                        Arrastra archivos aquí o haz click para seleccionar
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Máximo 5 archivos, 5 MB cada uno
                                    </p>
                                </div>
                            </div>
                            <InputError message={errors.attachments} />
                            {data.attachments.length > 0 && (
                                <div className="space-y-2">
                                    {data.attachments.map((file, index) => (
                                        <div
                                            key={`${file.name}-${index}`}
                                            className="flex items-center justify-between rounded-md border px-3 py-2"
                                        >
                                            <div className="flex min-w-0 items-center gap-2">
                                                <PaperclipIcon className="h-4 w-4 text-muted-foreground" />
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">
                                                        {file.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatFileSize(file.size)}
                                                    </p>
                                                </div>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="h-7 w-7"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    removeAttachment(index);
                                                }}
                                            >
                                                <XIcon className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
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
