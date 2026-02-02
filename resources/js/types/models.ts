// Account types
export const ACCOUNT_TYPES = [
    { id: 'checking', label: 'Cuenta Corriente' },
    { id: 'savings', label: 'Cuenta de Ahorro' },
    { id: 'cash', label: 'Efectivo' },
    { id: 'investment', label: 'Inversión' },
] as const;
export type AccountType = typeof ACCOUNT_TYPES[number]['id'];

// Transaction types
export const TRANSACTION_TYPES = [
    { id: 'expense', label: 'Gasto' },
    { id: 'income', label: 'Ingreso' },
    { id: 'transfer', label: 'Transferencia' },
    { id: 'transfer_out', label: 'Transferencia Saliente' },
    { id: 'transfer_in', label: 'Transferencia Entrante' },
    { id: 'settlement', label: 'Liquidación' },
] as const;
export type TransactionType = typeof TRANSACTION_TYPES[number]['id'];

// Category types
export const CATEGORY_TYPES = [
    { id: 'expense', label: 'Gasto' },
    { id: 'income', label: 'Ingreso' },
    { id: 'system', label: 'Sistema' },
] as const;
export type CategoryType = typeof CATEGORY_TYPES[number]['id'];

// Payment method types
export const PAYMENT_METHOD_TYPES = [
    { id: 'credit_card', label: 'Tarjeta de Crédito' },
    { id: 'debit_card', label: 'Tarjeta de Débito' },
    { id: 'prepaid_card', label: 'Tarjeta Prepago' },
    { id: 'cash', label: 'Efectivo' },
    { id: 'transfer', label: 'Transferencia' },
] as const;
export type PaymentMethodType = typeof PAYMENT_METHOD_TYPES[number]['id'];

// Base model with timestamps
export interface Model {
    id: number;
    uuid: string;
    created_at: string;
    updated_at: string;
}

export interface Currency {
    code: string;
    name: string;
    locale: string;
}

// Account model
export interface Account extends Model {
    user_id: number;
    name: string;
    type: AccountType;
    currency: string;
    currency_locale?: string;
    current_balance: number;
    color: string;
    icon: string;
    is_active: boolean;
    is_default: boolean;
    // Computed
    formatted_balance?: string;
    // Relations
    transactions?: Transaction[];
    payment_methods?: PaymentMethod[];
}

// Category model
export interface Category extends Model {
    user_id: number | null;
    parent_id: number | null;
    name: string;
    type: CategoryType;
    icon: string;
    color: string;
    is_system: boolean;
    // Relations
    parent?: Category;
    children?: Category[];
    transactions?: Transaction[];
}

// Payment Method model
export interface PaymentMethod extends Model {
    user_id: number;
    linked_account_id: number | null;
    name: string;
    type: PaymentMethodType;
    currency: string;
    currency_locale?: string;
    credit_limit: number | null;
    billing_cycle_day: number | null;
    payment_due_day: number | null;
    color: string;
    icon: string | null;
    last_four_digits: string | null;
    is_active: boolean;
    is_default: boolean;
    sort_order: number;
    // Computed
    current_debt?: number;
    available_credit?: number | null;
    // Relations
    linkedAccount?: Account;
    transactions?: Transaction[];
}

// Transaction model
export interface Transaction extends Model {
    user_id: number;
    account_id: number | null;
    payment_method_id: number | null;
    category_id: number | null;
    linked_transaction_id: number | null;
    type: TransactionType;
    amount: number;
    currency: string;
    currency_locale?: string;
    description: string | null;
    notes: string | null;
    transaction_date: string;
    // Computed
    formatted_amount?: string;
    // Relations
    account?: Account;
    payment_method?: PaymentMethod;
    category?: Category;
    linked_transaction?: Transaction;
    attachments?: Attachment[];
}

// Budget model
export interface Budget extends Model {
    user_id: number;
    name: string;
    currency: string;
    period_start: string;
    period_end: string;
    is_active: boolean;
    // Computed
    total_budgeted?: number;
    total_spent?: number;
    // Relations
    items?: BudgetItem[];
}

// Budget Item model
export interface BudgetItem extends Model {
    budget_id: number;
    category_id: number;
    amount: number;
    // Computed
    spent?: number;
    remaining?: number;
    percentage?: number;
    // Relations
    budget?: Budget;
    category?: Category;
}

// Recurring Transaction model
export interface RecurringTransaction extends Model {
    user_id: number;
    account_id: number;
    payment_method_id: number | null;
    category_id: number | null;
    amount: number;
    currency: string;
    description: string;
    frequency: 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'yearly';
    day_of_month: number | null;
    day_of_week: number | null;
    start_date: string;
    end_date: string | null;
    next_due_date: string;
    auto_create: boolean;
    is_active: boolean;
    // Relations
    account?: Account;
    payment_method?: PaymentMethod;
    category?: Category;
    transactions?: Transaction[];
}

// Attachment model
export interface Attachment extends Model {
    transaction_id: number;
    filename: string;
    path: string;
    mime_type: string;
    size: number;
    // Computed
    url?: string;
    formatted_size?: string;
    // Relations
    transaction?: Transaction;
}

// User Settings model
export interface UserSettings extends Model {
    user_id: number;
    default_currency: string;
    default_account_id: number | null;
    default_payment_method_id: number | null;
    locale: string;
    timezone: string;
    date_format: string;
    time_format: string;
    first_day_of_week: number;
    budget_cycle_start_day: number;
    // Relations
    default_account?: Account;
    default_payment_method?: PaymentMethod;
}

// Pagination types
export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: PaginationMeta;
}

// Form data types for creating/updating resources
export interface AccountFormData {
    name: string;
    type: AccountType;
    currency: string;
    initial_balance: number | null;
    color: string;
    icon: string;
    is_active: boolean;
}

export interface CategoryFormData {
    parent_id: number | null;
    name: string;
    type: CategoryType;
    icon: string;
    color: string;
}

export interface PaymentMethodFormData {
    linked_account_id: number | null;
    name: string;
    type: PaymentMethodType;
    currency: string;
    credit_limit: number | null;
    billing_cycle_day: number | null;
    payment_due_day: number | null;
    color: string;
    icon: string;
    last_four_digits: string | null;
    is_active: boolean;
}

export interface TransactionFormData {
    account_id: number | null;
    payment_method_id: number | null;
    category_id: number | null;
    type: TransactionType;
    amount: number;
    currency: string;
    description: string;
    notes: string | null;
    transaction_date: string;
}

// Dashboard summary types
export interface FinancialSummary {
    total_balance: number;
    total_income: number;
    total_expenses: number;
    net_flow: number;
    accounts_count: number;
    transactions_count: number;
    period: {
        start: string;
        end: string;
    };
}

export interface AccountSummary {
    account: Account;
    income: number;
    expenses: number;
    net_flow: number;
}

export interface CategorySummary {
    category: Category;
    amount: number;
    percentage: number;
    transactions_count: number;
}
