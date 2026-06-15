import { WalletIcon } from 'lucide-react';
import { useMemo } from 'react';
import { SelectField } from '@/components/ui/select-field';
import { SelectList, type SelectOption } from '@/components/ui/select-list';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { Account } from '@/types';

interface AccountAvatarProps {
    emoji: string | null;
    size?: 'sm' | 'md';
}

export function AccountAvatar({ emoji, size = 'sm' }: AccountAvatarProps) {
    const boxClass = size === 'sm' ? 'size-5 text-[12px]' : 'size-7 text-[15px]';
    const iconClass = size === 'sm' ? 'size-3' : 'size-3.5';

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-md bg-secondary leading-none text-secondary-foreground',
                boxClass,
            )}
            aria-hidden
        >
            {emoji ?? <WalletIcon className={cn(iconClass, 'opacity-70')} />}
        </span>
    );
}

interface AccountToOptionConfig {
    showBalance?: boolean;
}

export function accountToOption(
    account: Account,
    { showBalance = false }: AccountToOptionConfig = {},
): SelectOption {
    return {
        id: account.id,
        label: account.name,
        keywords: [account.name],
        leading: <AccountAvatar emoji={account.emoji} size="md" />,
        sublabel: showBalance ? (
            <span className="font-mono text-[11px] text-muted-foreground tabular-nums">
                {formatCurrency(
                    account.current_balance ?? 0,
                    account.currency,
                    account.currency_locale ?? 'es-CL',
                )}
            </span>
        ) : undefined,
    };
}

interface AccountFieldProps {
    accounts: Account[];
    value: number | null;
    onChange: (id: number | null) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    triggerClassName?: string;
    disabled?: boolean;
    id?: string;
    showBalance?: boolean;
}

export function AccountField({
    accounts,
    value,
    onChange,
    placeholder = 'Cuenta',
    searchPlaceholder = 'Buscar cuenta...',
    emptyMessage = 'Sin cuentas',
    triggerClassName,
    disabled,
    id,
    showBalance = false,
}: AccountFieldProps) {
    const options = useMemo(
        () =>
            accounts.map((account) => accountToOption(account, { showBalance })),
        [accounts, showBalance],
    );
    const selected = accounts.find((account) => account.id === value);

    return (
        <SelectField
            id={id}
            disabled={disabled}
            placeholder={placeholder}
            title={placeholder}
            triggerClassName={triggerClassName}
            value={
                selected ? (
                    <>
                        <AccountAvatar emoji={selected.emoji} />
                        <span className="truncate text-sm font-medium">
                            {selected.name}
                        </span>
                    </>
                ) : undefined
            }
        >
            {({ close }) => (
                <SelectList
                    mode="single"
                    options={options}
                    value={value}
                    onChange={onChange}
                    onSelect={close}
                    searchPlaceholder={searchPlaceholder}
                    emptyMessage={emptyMessage}
                />
            )}
        </SelectField>
    );
}
