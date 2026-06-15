// Transaction form modes (UI-only). The DB column `type` carries the kind of
// transaction; direction comes from the sign of `amount`.
export const TRANSACTION_MODES = [
    { id: 'movement', label: 'Movimiento' },
    { id: 'transfer', label: 'Transferencia' },
] as const;
export type TransactionMode = (typeof TRANSACTION_MODES)[number]['id'];

export const TRANSACTION_TYPES = [
    { id: 'regular', label: 'Regular' },
    { id: 'transfer', label: 'Transferencia' },
    { id: 'initial_balance', label: 'Balance inicial' },
] as const;
export type TransactionType = (typeof TRANSACTION_TYPES)[number]['id'];

export const TRANSACTION_DIRECTIONS = [
    { id: 'expense', label: 'Gasto' },
    { id: 'income', label: 'Ingreso' },
] as const;
export type TransactionDirection =
    (typeof TRANSACTION_DIRECTIONS)[number]['id'];

// Budget frequencies
export const BUDGET_FREQUENCIES = [
    { id: 'weekly', label: 'Semanal' },
    { id: 'biweekly', label: 'Quincenal' },
    { id: 'monthly', label: 'Mensual' },
    { id: 'bimonthly', label: 'Bimensual' },
] as const;
export type BudgetFrequency = (typeof BUDGET_FREQUENCIES)[number]['id'];

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
    currency: string;
    currency_locale?: string;
    current_balance: number;
    color: string;
    emoji: string | null;
    is_active: boolean;
    is_default: boolean;
    // Computed
    formatted_balance?: string;
    // Relations
    transactions?: Transaction[];
}

// Category model
export interface Category extends Model {
    user_id: number;
    parent_id: number | null;
    name: string;
    emoji: string | null;
    color: string;
    // Relations
    parent?: Category;
    children?: Category[];
    transactions?: Transaction[];
}

// Transaction model. `amount` is signed: negative = outflow, positive = inflow.
// `type` discriminates between regular movements, transfers (both legs), and
// initial balance entries. `linked_transaction_id` points to the other leg of
// a transfer.
export interface Transaction extends Model {
    user_id: number;
    account_id: number | null;
    category_id: number | null;
    linked_transaction_id: number | null;
    type: TransactionType;
    amount: number;
    currency: string;
    currency_locale?: string;
    description: string | null;
    notes: string | null;
    exclude_from_budget: boolean;
    transaction_date: string;
    // Computed
    formatted_amount?: string;
    // Relations
    account?: Account;
    category?: Category;
    linked_transaction?: Transaction;
    attachments?: Attachment[];
}

export function isTransfer(tx: Pick<Transaction, 'type'>): boolean {
    return tx.type === 'transfer';
}

export function isInitialBalance(tx: Pick<Transaction, 'type'>): boolean {
    return tx.type === 'initial_balance';
}

export function isRegular(tx: Pick<Transaction, 'type'>): boolean {
    return tx.type === 'regular';
}

export function isExpense(tx: Pick<Transaction, 'amount' | 'type'>): boolean {
    return isRegular(tx) && tx.amount < 0;
}

export function isIncome(tx: Pick<Transaction, 'amount' | 'type'>): boolean {
    return isRegular(tx) && tx.amount > 0;
}

// Budget model
export interface Budget extends Model {
    user_id: number;
    name: string;
    color: string;
    emoji: string | null;
    description: string | null;
    currency: string;
    frequency: BudgetFrequency;
    anchor_date: string;
    ends_at: string | null;
    is_active: boolean;
    // Computed
    total_budgeted?: number;
    current_cycle_spent?: number;
    current_cycle_percentage?: number;
    current_cycle_start?: string;
    current_cycle_end?: string;
    total_spent?: number;
    // Relations
    items?: BudgetItem[];
    account_ids?: number[];
    accounts?: Pick<Account, 'id' | 'uuid' | 'name' | 'currency' | 'color' | 'emoji'>[];
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
    default_currency: string;
    timezone: string;
    budget_cycle_start_day: number;
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
    currency: string;
    initial_balance: number | null;
    color: string;
    emoji: string | null;
    is_active: boolean;
}

export interface CategoryFormData {
    parent_id: number | null;
    name: string;
    emoji: string | null;
    color: string;
}

export interface TransactionFormData {
    account_id: number | null;
    category_id: number | null;
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
