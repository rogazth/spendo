import { useForm, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { CalendarIcon, StickyNoteIcon, WalletIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { CategoryPicker } from '@/components/categories/category-picker';
import { AmountDisplay } from '@/components/forms/transaction-form/amount-display';
import { Numpad } from '@/components/forms/transaction-form/numpad';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
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
import { getCurrencyFractionDigits, getCurrencyLocale } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type {
    Account,
    Category,
    Currency,
    Transaction,
    TransactionDirection,
    TransactionMode,
} from '@/types';

interface TransactionFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transaction?: Transaction;
    accounts: Account[];
    categories: Category[];
}

export function TransactionFormDialog({
    open,
    onOpenChange,
    transaction,
    accounts,
    categories,
}: TransactionFormDialogProps) {
    const isEditing = !!transaction;
    const { currencies = [] } = usePage<{ currencies?: Currency[] }>().props;

    const defaultAccount =
        accounts.find((account) => account.is_default) ?? accounts[0];
    const fallbackDestinationAccount = accounts.find(
        (account) =>
            account.id !== defaultAccount?.id &&
            account.currency === defaultAccount?.currency,
    );

    const [mode, setMode] = useState<TransactionMode>('movement');
    const [direction, setDirection] = useState<TransactionDirection>('expense');
    const [cents, setCents] = useState<number>(0);
    const [descriptionOpen, setDescriptionOpen] = useState(false);
    const [dateOpen, setDateOpen] = useState(false);

    type MovementForm = {
        account_id: number | null;
        category_id: number | null;
        amount: number;
        description: string;
        exclude_from_budget: boolean;
        transaction_date: string;
    };
    type TransferForm = {
        origin_account_id: number | null;
        destination_account_id: number | null;
        amount: number;
        description: string;
        transaction_date: string;
    };

    const movement = useForm<MovementForm>({
        account_id: null,
        category_id: null,
        amount: 0,
        description: '',
        exclude_from_budget: false,
        transaction_date: format(new Date(), 'yyyy-MM-dd'),
    });
    const transfer = useForm<TransferForm>({
        origin_account_id: null,
        destination_account_id: null,
        amount: 0,
        description: '',
        transaction_date: format(new Date(), 'yyyy-MM-dd'),
    });

    useEffect(() => {
        if (!open) return;

        const incomingIsTransfer = !!transaction?.linked_transaction_id;
        const incomingMode: TransactionMode = incomingIsTransfer
            ? 'transfer'
            : 'movement';
        const initialDirection: TransactionDirection = transaction
            ? transaction.amount < 0
                ? 'expense'
                : 'income'
            : 'expense';
        const today = format(new Date(), 'yyyy-MM-dd');
        const txDate = transaction?.transaction_date
            ? format(new Date(transaction.transaction_date), 'yyyy-MM-dd')
            : today;
        const txAccountCurrency =
            (transaction?.account as Account | undefined)?.currency ??
            transaction?.currency ??
            defaultAccount?.currency ??
            'CLP';
        const locale = getCurrencyLocale(txAccountCurrency, currencies);
        const fractionDigits = getCurrencyFractionDigits(
            locale,
            txAccountCurrency,
        );
        const magnitude = Math.abs(transaction?.amount ?? 0);
        const initialCents = Math.round(
            magnitude * Math.pow(10, fractionDigits),
        );

        setMode(incomingMode);
        setDirection(initialDirection);
        setCents(initialCents);
        setDescriptionOpen(false);
        setDateOpen(false);

        movement.setData({
            account_id: transaction?.account_id ?? defaultAccount?.id ?? null,
            category_id: incomingIsTransfer
                ? null
                : (transaction?.category_id ?? null),
            amount: 0,
            description: transaction?.description ?? '',
            exclude_from_budget: transaction?.exclude_from_budget ?? false,
            transaction_date: txDate,
        });
        movement.clearErrors();

        const origin = incomingIsTransfer
            ? transaction.amount < 0
                ? transaction.account_id
                : transaction.linked_transaction?.account_id
            : (defaultAccount?.id ?? null);
        const destination = incomingIsTransfer
            ? transaction.amount > 0
                ? transaction.account_id
                : transaction.linked_transaction?.account_id
            : (fallbackDestinationAccount?.id ?? null);

        transfer.setData({
            origin_account_id: origin ?? defaultAccount?.id ?? null,
            destination_account_id:
                destination ?? fallbackDestinationAccount?.id ?? null,
            amount: 0,
            description: transaction?.description ?? '',
            transaction_date: txDate,
        });
        transfer.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, transaction?.id]);

    const activeAccount =
        mode === 'transfer'
            ? accounts.find(
                  (account) => account.id === transfer.data.origin_account_id,
              )
            : accounts.find(
                  (account) => account.id === movement.data.account_id,
              );
    const currency = activeAccount?.currency ?? 'CLP';
    const locale = useMemo(
        () => getCurrencyLocale(currency, currencies),
        [currency, currencies],
    );
    const fractionDigits = useMemo(
        () => getCurrencyFractionDigits(locale, currency),
        [currency, locale],
    );
    const magnitude = cents / Math.pow(10, fractionDigits);
    const maxCents = 99_999_999_999;

    const handleDigit = (digit: number) => {
        setCents((current) => {
            const next = current * 10 + digit;
            return Math.min(next, maxCents);
        });
    };

    const handleBackspace = () => {
        setCents((current) => Math.floor(current / 10));
    };

    const submitMovement = () => {
        const signedAmount = direction === 'expense' ? -magnitude : magnitude;
        movement.transform((data) => ({
            ...data,
            amount: signedAmount,
            description: data.description || null,
            exclude_from_budget:
                direction === 'expense' ? data.exclude_from_budget : false,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                movement.reset();
                setCents(0);
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

        if (isEditing && transaction) {
            movement.put(`/transactions/${transaction.uuid}`, options);
            return;
        }

        movement.post('/transactions', options);
    };

    const submitTransfer = () => {
        if (isEditing) {
            toast.error(
                'Las transferencias no se pueden editar. Elimínala y créala nuevamente.',
            );
            return;
        }

        transfer.transform((data) => ({
            ...data,
            amount: magnitude,
            description: data.description || null,
        }));

        transfer.post('/transfers', {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                transfer.reset();
                setCents(0);
                toast.success('Transferencia creada');
            },
            onError: () => {
                toast.error('Error al guardar la transferencia');
            },
        });
    };

    const transferEditBlocked = isEditing && mode === 'transfer';
    const canSubmit =
        magnitude > 0 &&
        !transferEditBlocked &&
        !movement.processing &&
        !transfer.processing;

    const handleSubmit = () => {
        if (transferEditBlocked) {
            toast.error(
                'Las transferencias no se pueden editar. Elimínala y créala nuevamente.',
            );
            return;
        }

        if (!canSubmit) {
            if (magnitude === 0) toast.error('Ingresa un monto');
            return;
        }
        if (mode === 'movement') submitMovement();
        else submitTransfer();
    };

    const processing = movement.processing || transfer.processing;
    const movementSign = direction === 'expense' ? 'negative' : 'positive';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[95vh] gap-4 overflow-y-auto sm:max-w-[440px]">
                <DialogHeader className="space-y-0">
                    <DialogTitle>
                        {isEditing ? 'Editar transacción' : 'Nueva transacción'}
                    </DialogTitle>
                </DialogHeader>

                {!isEditing && (
                    <Tabs
                        value={mode}
                        onValueChange={(value) => {
                            if (value === 'movement' || value === 'transfer') {
                                setMode(value);
                            }
                        }}
                    >
                        <TabsList className="w-full">
                            <TabsTrigger value="movement" className="flex-1">
                                Movimiento
                            </TabsTrigger>
                            <TabsTrigger value="transfer" className="flex-1">
                                Transferencia
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                )}

                <AmountDisplay
                    magnitude={magnitude}
                    currency={currency}
                    locale={locale}
                    sign={mode === 'transfer' ? 'neutral' : movementSign}
                    fractionDigits={fractionDigits}
                />

                {mode === 'movement' ? (
                    <MovementChips
                        accounts={accounts}
                        categories={categories}
                        accountId={movement.data.account_id}
                        categoryId={movement.data.category_id}
                        description={movement.data.description}
                        descriptionOpen={descriptionOpen}
                        setDescriptionOpen={setDescriptionOpen}
                        date={movement.data.transaction_date}
                        dateOpen={dateOpen}
                        setDateOpen={setDateOpen}
                        excludeFromBudget={movement.data.exclude_from_budget}
                        direction={direction}
                        errors={
                            movement.errors as Record<
                                string,
                                string | undefined
                            >
                        }
                        onAccountChange={(id) =>
                            movement.setData('account_id', id)
                        }
                        onCategoryChange={(id) =>
                            movement.setData('category_id', id)
                        }
                        onDescriptionChange={(value) =>
                            movement.setData('description', value)
                        }
                        onDateChange={(value) =>
                            movement.setData('transaction_date', value)
                        }
                        onExcludeFromBudgetChange={(value) =>
                            movement.setData('exclude_from_budget', value)
                        }
                    />
                ) : (
                    <TransferChips
                        accounts={accounts}
                        originId={transfer.data.origin_account_id}
                        destinationId={transfer.data.destination_account_id}
                        description={transfer.data.description}
                        descriptionOpen={descriptionOpen}
                        setDescriptionOpen={setDescriptionOpen}
                        date={transfer.data.transaction_date}
                        dateOpen={dateOpen}
                        setDateOpen={setDateOpen}
                        errors={
                            transfer.errors as Record<
                                string,
                                string | undefined
                            >
                        }
                        onOriginChange={(id) => {
                            transfer.setData('origin_account_id', id);
                            const newOrigin = accounts.find(
                                (account) => account.id === id,
                            );
                            const currentDestination = accounts.find(
                                (account) =>
                                    account.id ===
                                    transfer.data.destination_account_id,
                            );
                            const destinationIsInvalid =
                                !currentDestination ||
                                currentDestination.id === id ||
                                currentDestination.currency !==
                                    newOrigin?.currency;
                            if (id !== null && destinationIsInvalid) {
                                const next = accounts.find(
                                    (account) =>
                                        account.id !== id &&
                                        account.currency ===
                                            newOrigin?.currency,
                                );
                                transfer.setData(
                                    'destination_account_id',
                                    next?.id ?? null,
                                );
                            }
                        }}
                        onDestinationChange={(id) =>
                            transfer.setData('destination_account_id', id)
                        }
                        onDescriptionChange={(value) =>
                            transfer.setData('description', value)
                        }
                        onDateChange={(value) =>
                            transfer.setData('transaction_date', value)
                        }
                    />
                )}

                {mode === 'movement' && (
                    <Tabs
                        value={direction}
                        onValueChange={(value) => {
                            if (value === 'expense' || value === 'income') {
                                setDirection(value);
                            }
                        }}
                    >
                        <TabsList className="w-full">
                            <TabsTrigger
                                value="expense"
                                className="flex-1 data-[state=active]:bg-red-100 data-[state=active]:text-red-700 data-[state=active]:shadow-sm dark:data-[state=active]:bg-red-950/60 dark:data-[state=active]:text-red-400"
                            >
                                − Gasto
                            </TabsTrigger>
                            <TabsTrigger
                                value="income"
                                className="flex-1 data-[state=active]:bg-emerald-100 data-[state=active]:text-emerald-700 data-[state=active]:shadow-sm dark:data-[state=active]:bg-emerald-950/60 dark:data-[state=active]:text-emerald-400"
                            >
                                + Ingreso
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                )}

                <Numpad
                    onDigit={handleDigit}
                    onBackspace={handleBackspace}
                    onSubmit={handleSubmit}
                    disabled={transferEditBlocked}
                    submitting={processing}
                />

                {transferEditBlocked && (
                    <p className="rounded-lg border px-3 py-2 text-xs text-muted-foreground">
                        Las transferencias no se editan directamente. Elimínala
                        y créala nuevamente si necesitas cambiar monto, cuentas
                        o fecha.
                    </p>
                )}

                {Object.values(movement.errors).map(
                    (error) =>
                        error && (
                            <InputError
                                key={error as string}
                                message={error as string}
                            />
                        ),
                )}
                {Object.values(transfer.errors).map(
                    (error) =>
                        error && (
                            <InputError
                                key={error as string}
                                message={error as string}
                            />
                        ),
                )}
            </DialogContent>
        </Dialog>
    );
}

interface MovementChipsProps {
    accounts: Account[];
    categories: Category[];
    accountId: number | null;
    categoryId: number | null;
    description: string;
    descriptionOpen: boolean;
    setDescriptionOpen: (open: boolean) => void;
    date: string;
    dateOpen: boolean;
    setDateOpen: (open: boolean) => void;
    excludeFromBudget: boolean;
    direction: TransactionDirection;
    errors: Record<string, string | undefined>;
    onAccountChange: (id: number | null) => void;
    onCategoryChange: (id: number | null) => void;
    onDescriptionChange: (value: string) => void;
    onDateChange: (value: string) => void;
    onExcludeFromBudgetChange: (value: boolean) => void;
}

function MovementChips({
    accounts,
    categories,
    accountId,
    categoryId,
    description,
    descriptionOpen,
    setDescriptionOpen,
    date,
    dateOpen,
    setDateOpen,
    excludeFromBudget,
    direction,
    onAccountChange,
    onCategoryChange,
    onDescriptionChange,
    onDateChange,
    onExcludeFromBudgetChange,
}: MovementChipsProps) {
    const account = accounts.find((a) => a.id === accountId);

    return (
        <div className="grid grid-cols-2 gap-2">
            <AccountChip
                account={account}
                onChange={onAccountChange}
                accounts={accounts}
            />
            <CategoryPicker
                categories={categories}
                value={categoryId}
                onChange={onCategoryChange}
                placeholder="Categoría"
                triggerClassName="h-10"
            />
            <DateChip
                date={date}
                onChange={onDateChange}
                open={dateOpen}
                onOpenChange={setDateOpen}
            />
            <DescriptionChip
                value={description}
                onChange={onDescriptionChange}
                open={descriptionOpen}
                onOpenChange={setDescriptionOpen}
            />
            {direction === 'expense' && (
                <div className="col-span-2 flex items-center justify-between rounded-lg border px-3 py-2 text-sm">
                    <Label
                        htmlFor="exclude_from_budget"
                        className="cursor-pointer text-sm font-medium"
                    >
                        Excluir del budget
                    </Label>
                    <Switch
                        id="exclude_from_budget"
                        checked={excludeFromBudget}
                        onCheckedChange={(checked) =>
                            onExcludeFromBudgetChange(checked === true)
                        }
                    />
                </div>
            )}
        </div>
    );
}

interface TransferChipsProps {
    accounts: Account[];
    originId: number | null;
    destinationId: number | null;
    description: string;
    descriptionOpen: boolean;
    setDescriptionOpen: (open: boolean) => void;
    date: string;
    dateOpen: boolean;
    setDateOpen: (open: boolean) => void;
    errors: Record<string, string | undefined>;
    onOriginChange: (id: number | null) => void;
    onDestinationChange: (id: number | null) => void;
    onDescriptionChange: (value: string) => void;
    onDateChange: (value: string) => void;
}

function TransferChips({
    accounts,
    originId,
    destinationId,
    description,
    descriptionOpen,
    setDescriptionOpen,
    date,
    dateOpen,
    setDateOpen,
    onOriginChange,
    onDestinationChange,
    onDescriptionChange,
    onDateChange,
}: TransferChipsProps) {
    const origin = accounts.find((a) => a.id === originId);
    const destination = accounts.find((a) => a.id === destinationId);
    const destinationOptions = accounts.filter(
        (a) => a.id !== originId && a.currency === origin?.currency,
    );

    return (
        <div className="space-y-2">
            <div className="rounded-lg border p-3">
                <div className="mb-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                    De
                </div>
                <Select
                    value={originId?.toString() ?? ''}
                    onValueChange={(value) =>
                        onOriginChange(value ? parseInt(value, 10) : null)
                    }
                >
                    <SelectTrigger className="h-8 border-none p-0 text-sm font-medium shadow-none focus:ring-0">
                        <SelectValue>
                            {origin ? (
                                <AccountSummary account={origin} />
                            ) : (
                                'Seleccionar cuenta'
                            )}
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        {accounts.map((account) => (
                            <SelectItem
                                key={account.id}
                                value={account.id.toString()}
                            >
                                <AccountSummary account={account} />
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <div className="my-2 border-t" />
                <div className="mb-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                    A
                </div>
                <Select
                    value={destinationId?.toString() ?? ''}
                    onValueChange={(value) =>
                        onDestinationChange(value ? parseInt(value, 10) : null)
                    }
                >
                    <SelectTrigger className="h-8 border-none p-0 text-sm font-medium shadow-none focus:ring-0">
                        <SelectValue>
                            {destination ? (
                                <AccountSummary account={destination} />
                            ) : (
                                'Seleccionar cuenta'
                            )}
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        {destinationOptions.map((account) => (
                            <SelectItem
                                key={account.id}
                                value={account.id.toString()}
                            >
                                <AccountSummary account={account} />
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {destinationOptions.length === 0 && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        No tienes otra cuenta en {origin?.currency}. Las
                        transferencias deben ser entre cuentas de la misma
                        divisa.
                    </p>
                )}
            </div>
            <div className="grid grid-cols-2 gap-2">
                <DateChip
                    date={date}
                    onChange={onDateChange}
                    open={dateOpen}
                    onOpenChange={setDateOpen}
                />
                <DescriptionChip
                    value={description}
                    onChange={onDescriptionChange}
                    open={descriptionOpen}
                    onOpenChange={setDescriptionOpen}
                />
            </div>
        </div>
    );
}

function AccountSummary({ account }: { account: Account }) {
    return (
        <span className="flex items-center gap-2">
            <span aria-hidden>{account.emoji ?? '💼'}</span>
            <span className="truncate">{account.name}</span>
        </span>
    );
}

interface AccountChipProps {
    account: Account | undefined;
    onChange: (id: number | null) => void;
    accounts: Account[];
}

function AccountChip({ account, onChange, accounts }: AccountChipProps) {
    return (
        <Select
            value={account?.id?.toString() ?? ''}
            onValueChange={(value) =>
                onChange(value ? parseInt(value, 10) : null)
            }
        >
            <SelectTrigger className="h-10 justify-start gap-2 text-left">
                <WalletIcon className="size-4 shrink-0 text-muted-foreground" />
                <span className="truncate text-sm font-medium">
                    {account?.name ?? 'Cuenta'}
                </span>
            </SelectTrigger>
            <SelectContent>
                {accounts.map((option) => (
                    <SelectItem key={option.id} value={option.id.toString()}>
                        <AccountSummary account={option} />
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

interface DateChipProps {
    date: string;
    onChange: (value: string) => void;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

function DateChip({ date, onChange, open, onOpenChange }: DateChipProps) {
    const parsedDate = useMemo(() => new Date(`${date}T12:00:00`), [date]);

    return (
        <Popover open={open} onOpenChange={onOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className="h-10 justify-start gap-2 px-3 font-normal"
                >
                    <CalendarIcon className="size-4 shrink-0 text-muted-foreground" />
                    <span className="truncate text-sm font-medium">
                        {format(parsedDate, 'PPP', { locale: es })}
                    </span>
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-auto p-0">
                <Calendar
                    mode="single"
                    selected={parsedDate}
                    onSelect={(picked) => {
                        if (picked) {
                            onChange(format(picked, 'yyyy-MM-dd'));
                            onOpenChange(false);
                        }
                    }}
                    initialFocus
                />
            </PopoverContent>
        </Popover>
    );
}

interface DescriptionChipProps {
    value: string;
    onChange: (value: string) => void;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

function DescriptionChip({
    value,
    onChange,
    open,
    onOpenChange,
}: DescriptionChipProps) {
    const trimmed = value.trim();
    const display = trimmed.length > 0 ? trimmed : 'Notas / descripción';

    return (
        <Popover open={open} onOpenChange={onOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className={cn(
                        'h-10 justify-start gap-2 px-3 font-normal',
                        trimmed.length === 0 && 'text-muted-foreground',
                    )}
                >
                    <StickyNoteIcon className="size-4 shrink-0 text-muted-foreground" />
                    <span className="truncate text-sm font-medium">
                        {display}
                    </span>
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-80 space-y-2">
                <Label className="text-xs font-semibold tracking-wider uppercase">
                    Notas
                </Label>
                <Textarea
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder="Ej: Supermercado Líder"
                    rows={3}
                    autoFocus
                />
                <div className="flex justify-end">
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => onOpenChange(false)}
                    >
                        Listo
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
