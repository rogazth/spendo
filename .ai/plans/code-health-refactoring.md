# Code Health Refactoring Plan

> **Diagnosis date**: 2026-02-23
> **Status**: Reviewed by Codex (gpt-5.3-codex) — Updated with findings
> **Principle**: Correctness first, tests first, abstraction second.

The codebase has grown with business logic scattered across controllers, manual authorization checks, inconsistent resource usage, duplicated frontend patterns, and **critical correctness gaps** (cross-tenant FK validation, transfer lifecycle integrity). This plan aligns the code with the project's own documented standards in `docs/SPENDO.md`:

- **Actions** para lógica de negocio compleja
- **Services** para lógica reutilizable
- **API Resources** para responses
- **Form Requests** para validación

---

## Phase 1: Regression Tests & Safety Net

**Goal**: Lock down current behavior before any refactoring. Tests go first, not last.

### 1.1 Transfer Lifecycle Tests

Write tests covering:
- Creating a transfer creates both `transfer_out` and `transfer_in` rows
- Updating a transfer updates both legs
- Deleting a transfer deletes both legs (currently broken — only deletes one)
- Converting transfer → non-transfer cleans up the counterpart (currently broken — orphans it)
- Attachment behavior on transfer operations

### 1.2 Ownership Validation Tests

Write tests proving:
- A user **cannot** create a transaction with another user's `account_id`, `payment_method_id`, or `category_id`
- A user **cannot** create a payment method with another user's `linked_account_id`
- A user **cannot** create a budget with another user's `account_id` or category IDs
- These should all return 422/403

### 1.3 Default Entity Tests

Write tests covering:
- Setting account as default unsets previous default
- Setting payment method as default unsets previous default
- Race condition consideration: document whether DB-level partial unique index is needed

### 1.4 Existing Test Baseline

- Run all existing tests (`tests/Feature/BudgetTest.php`, `tests/Feature/TransactionFiltersTest.php`, `tests/Feature/RouteAccessTest.php`) and ensure green baseline before proceeding

---

## Phase 2: Correctness & Security Fixes

**Goal**: Fix data integrity and security issues. These are higher priority than any abstraction.

### 2.1 Cross-Tenant Foreign Key Validation (CRITICAL)

**Problem**: `StoreTransactionRequest` and `UpdateTransactionRequest` use generic `exists` rules for `account_id`, `payment_method_id`, and `category_id`. A user can reference another user's records.

**Fix**: Add ownership validation (like `StoreBudgetRequest` already does) to:
- `StoreTransactionRequest` — validate `account_id`, `payment_method_id`, `category_id`, `origin_account_id`, `destination_account_id` belong to current user
- `UpdateTransactionRequest` — same
- `StorePaymentMethodRequest` — validate `linked_account_id` belongs to current user
- `UpdatePaymentMethodRequest` — same

### 2.2 Transfer Lifecycle Integrity (CRITICAL)

**Problem**: Deleting a transfer deletes only one row, orphaning the paired transaction. Editing transfer→non-transfer also orphans the counterpart.

**Fix in `TransactionController` (or future `TransactionService`):**
- `destroy()`: When deleting a transfer, delete both legs in a DB transaction
- `update()`: When changing type away from transfer, delete the counterpart
- `update()`: When updating transfer fields, update both legs

### 2.3 Transfer Currency Validation

**Problem**: UI allows selecting any two accounts for transfer. Backend writes both legs using origin currency. No validation that currencies match.

**Fix**: Either:
- Add validation rule: origin and destination accounts must have same currency
- Or: Support cross-currency transfers explicitly (different amounts per leg)

**Needs product decision.**

### 2.4 Default Entity Race Protection

**Problem**: Default toggling is "unset others then set one" without DB constraint.

**Fix**: Wrap in DB transaction. Consider adding a Postgres partial unique index:
```sql
CREATE UNIQUE INDEX accounts_one_default_per_user ON accounts (user_id) WHERE is_default = true;
```

---

## Phase 3: Data Contract Cleanup

**Goal**: Fix schema/resource/type mismatches that cause silent bugs.

### 3.1 UserSettingsResource Schema Mismatch (CRITICAL)

`UserSettingsResource` exposes 6 fields that **don't exist in the database**:
- `default_account_id`, `default_payment_method_id`, `locale`, `date_format`, `time_format`, `first_day_of_week`

It also references relations not defined on `UserSettings` model. Frontend types (`resources/js/types/models.ts:222-231`) assume these fields exist.

**Fix**: Product decision needed — either:
- Add columns to `user_settings` migration + update model `$fillable` + add relationships
- Remove from resource + remove from frontend types

### 3.2 Dead Frontend Pages Audit

`resources/js/pages/accounts/show.tsx` and `resources/js/pages/payment-methods/show.tsx` exist but their routes return 405 (confirmed by `RouteAccessTest`). These are dead code.

**Fix**: Delete the dead page files, or re-enable the routes if show pages are wanted.

---

## Phase 4: Performance & Query Hygiene

**Goal**: Fix N+1 queries and repeated DB calls.

### 4.1 Cache `Currency::localeFor()`

`Currency::localeFor()` rebuilds a DB-backed map on every call. It's called in `AccountResource`, `PaymentMethodResource`, `TransactionResource`, `TransactionController`, and `BudgetController`.

**Fix**: Add static caching (request-level) or `once()`:
```php
public static function localeFor(string $code): string
{
    return once(fn () => static::pluck('locale', 'code')->all())[$code] ?? 'en-US';
}
```

### 4.2 Budget `total_budgeted` Accessor N+1

`Budget::getTotalBudgetedAttribute()` calls `$this->items()->sum('amount')` — triggers a query even when items are already eager-loaded.

**Fix**: Use loaded relation when available:
```php
public function getTotalBudgetedAttribute(): int
{
    return $this->relationLoaded('items')
        ? $this->items->sum('amount')
        : $this->items()->sum('amount');
}
```

### 4.3 Category `full_name` N+1

`CategoryResource` reads `full_name` accessor which lazy-loads `parent`. When used inside `TransactionResource` (which only eager-loads `category`, not `category.parent`), this creates N+1.

**Fix**: Eager load `category.parent` in `TransactionController::index()`.

### 4.4 Dashboard Accessor Queries

`DashboardController` loops over accounts/payment methods hitting `current_balance` and `current_debt` accessors per row (each runs a DB query).

**Fix**: Use `withSum` or `withAggregate` on the query, or batch-load balance data.

---

## Phase 5: Authorization (Policies)

**Goal**: Replace manual `abort(403)` checks with proper Laravel policies.

Currently every controller has a private `authorize*()` method:
```php
private function authorizeAccount(Account $account): void
{
    if ($account->user_id !== Auth::id()) abort(403);
}
```

### Create Policies

| Policy | Model | Methods |
|--------|-------|---------|
| `AccountPolicy` | Account | viewAny, view, create, update, delete, makeDefault |
| `PaymentMethodPolicy` | PaymentMethod | viewAny, view, create, update, delete, makeDefault |
| `TransactionPolicy` | Transaction | viewAny, view, create, update, delete |
| `CategoryPolicy` | Category | view, update, delete (skip system categories) |
| `BudgetPolicy` | Budget | viewAny, view, create, update, delete |

Then use route model binding + `$this->authorize()` in controllers.

**Note**: Policies handle read/update/delete authorization. They do **not** solve store-time FK ownership issues (that's Phase 2.1).

---

## Phase 6: Model Layer (Scopes, Relationships, Enums)

**Goal**: Move query logic from controllers into models.

### 6.1 Transaction Scopes

**File**: `app/Models/Transaction.php`

```php
public function scopeForAccounts(Builder $query, array $ids): Builder
public function scopeForPaymentMethods(Builder $query, array $ids): Builder
public function scopeForCategories(Builder $query, array $ids): Builder
public function scopeDateRange(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
public function scopeExpenses(Builder $query): Builder
public function scopeForBudget(Builder $query, Budget $budget, CarbonImmutable $start, CarbonImmutable $end): Builder
```

### 6.2 Category Scopes

**File**: `app/Models/Category.php`

The `where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', Auth::id()))` pattern repeats in 3+ controllers.

```php
// Pass user explicitly — do NOT use Auth::id() inside scope
public function scopeAccessibleBy(Builder $query, int $userId): Builder
public function scopeExpense(Builder $query): Builder
public function scopeIncome(Builder $query): Builder
public function scopeParentsOnly(Builder $query): Builder
```

### 6.3 Account & PaymentMethod Scopes

**Note**: `User` model already has `activeAccounts()` and `activePaymentMethods()` relationships, so `scopeActive()` is low priority. Add only if needed for non-User query contexts.

### 6.4 Budget Frequency Enum

**Create**: `app/Enums/BudgetFrequency.php`

Replace raw strings (`'weekly'`, `'biweekly'`, `'monthly'`, `'bimonthly'`). Update `StoreBudgetRequest`, factories, controller comparisons, and model cast together.

### 6.5 Model Helper Methods

```php
// Account.php
public function makeDefault(): void  // wraps in DB transaction, replaces logic repeated 3x

// PaymentMethod.php
public function makeDefault(): void  // wraps in DB transaction, replaces logic repeated 3x
```

### 6.6 Budget Cycle Methods

Move date arithmetic from `BudgetController` private methods into the `Budget` model. Use `CarbonImmutable` (matching current code):

```php
// Budget.php
public function resolveCycleRange(?string $cycleKey = null): array  // returns [CarbonImmutable, CarbonImmutable]
public function resolveHistoryRange(CarbonImmutable $referenceDate): array
```

---

## Phase 7: Service & Action Layer

**Goal**: Extract complex business logic from controllers into testable, reusable classes.

### 7.1 TransactionService

**Create**: `app/Services/TransactionService.php`

Extract transfer creation/update logic. **Must define lifecycle semantics:**
- Editing one leg edits both
- Deleting one leg deletes both
- Converting transfer ↔ non-transfer handles counterpart cleanup
- Cross-currency behavior is validated

```php
class TransactionService
{
    public function createTransaction(User $user, array $data): Transaction
    public function createTransfer(User $user, array $data): Transaction
    public function updateTransaction(Transaction $transaction, array $data): Transaction
    public function updateTransfer(Transaction $transaction, array $data): Transaction
    public function deleteTransaction(Transaction $transaction): void  // handles paired deletion
}
```

### 7.2 BudgetService

**Create**: `app/Services/BudgetService.php`

Extract private methods from `BudgetController`. Use `CarbonImmutable` and match actual return types:

```php
class BudgetService
{
    public function calculateBudgetSpent(Budget $budget, CarbonImmutable $start, CarbonImmutable $end): float
    public function buildCategoryProgress(Budget $budget, CarbonImmutable $start, CarbonImmutable $end): Collection
    public function budgetItemCategoryGroups(Budget $budget, CarbonImmutable $start, CarbonImmutable $end): Collection
    public function buildBudgetTransactionsQuery(Budget $budget, array $filters): Builder
}
```

### 7.3 AccountService

**Create**: `app/Services/AccountService.php`

```php
class AccountService
{
    public function createWithInitialBalance(User $user, array $data): Account
}
```

---

## Phase 8: API Resource Consistency

**Goal**: Reduce manual `.map()` in controllers, but **respect the raw-array convention for form dropdowns**.

### Important Convention

Per `CLAUDE.md`: "Return raw arrays for dropdowns (not ResourceCollection)." The `.map()` calls for dropdown options (accounts, payment methods, categories in index views) are **intentional** and should NOT be replaced with full Resources (which would bloat payloads with computed fields like `current_balance`, `current_debt`, etc.).

### 8.1 Resources to Create

| Resource | Purpose |
|----------|---------|
| `BudgetItemResource` | Replace inline item mapping in `BudgetResource` |

**Note**: `CategoryResource` already exists. `DashboardResource` is not a good fit — dashboard output is an aggregate view model, not a model resource. Dashboard should use a dedicated DTO/array structure or a `DashboardService`.

### 8.2 Controllers to Improve

| Controller | Issue | Fix |
|------------|-------|-----|
| `DashboardController::index()` | Uses `.map()` for data that could use Resources | Use `AccountResource::collection()` and `TransactionResource::collection()` for display data. Keep raw arrays for dropdown-style data. |
| `BudgetResource` | Inline item mapping (lines 47-67) | Use `BudgetItemResource::collection()` |

### 8.3 Use Existing Resource Fields in Frontend

`TransactionResource` already provides `type_label`, `type_icon`, and `is_debit`. Frontend currently re-implements these. Frontend should use the backend fields instead.

---

## Phase 9: Frontend Cleanup

### 9.1 Eliminate Duplicated Label Maps

Frontend has label constants in `resources/js/types/models.ts` (`ACCOUNT_TYPES`, `PAYMENT_METHOD_TYPES`, `TRANSACTION_TYPES`). Use these instead of duplicating in:
- `transaction-columns.tsx`
- `accounts/show.tsx`
- `payment-methods/show.tsx`

Also use backend `type_label`, `type_icon`, `is_debit` fields from `TransactionResource` instead of re-implementing.

### 9.2 Use Existing Pagination Component

`resources/js/components/ui/pagination.tsx` already exists. The 3 pages that duplicate manual pagination markup should use it.

### 9.3 Use Wayfinder for Route Paths

Replace hardcoded route strings:
- `account-columns.tsx` → `/transactions?account_ids[]=...`
- `payment-method-columns.tsx` → `/transactions?payment_method_ids[]=...`

### 9.4 Simplify Transaction Form Transfer Logic

`transaction-form-dialog.tsx` has ~100 lines of transfer-specific logic. Backend should normalize transfer data before sending to frontend (return `origin_account_id` and `destination_account_id` on the resource for transfer types).

---

## Phase 10: Factory Improvements

### 10.1 Relational Integrity (Priority)

| Factory | Issue | Fix |
|---------|-------|-----|
| `TransactionFactory` | Creates unrelated user, account, paymentMethod, category | Ensure all belong to same user |
| `PaymentMethodFactory` | Linked account states can create different-user accounts | Ensure `linked_account_id` belongs to same user |
| `BudgetItemFactory` | Creates unrelated budget/category pairs | Ensure category belongs to budget's user |
| `CategoryFactory` | `subcategory()` doesn't ensure type inheritance or ownership match | Fix both |

### 10.2 Missing States

| Factory | State | Purpose |
|---------|-------|---------|
| `AccountFactory` | `withInitialBalance()` | Creates opening balance transaction (mirrors controller behavior) |
| `BudgetItemFactory` | `forCategory()`, `withAmount()` | Test helpers |

---

## Execution Order & Priority

| Priority | Phase | Effort | Risk | Why this order |
|----------|-------|--------|------|----------------|
| 1 | Phase 1: Regression Tests | Medium | Low | Safety net before touching anything |
| 2 | Phase 2: Correctness Fixes | High | Medium | Security/data integrity — highest business impact |
| 3 | Phase 3: Data Contract Cleanup | Low | Low | Fix silent bugs in resource/schema |
| 4 | Phase 4: Performance/Query Hygiene | Medium | Low | Fix N+1 issues while patterns are fresh |
| 5 | Phase 5: Policies | Medium | Low | Centralize authorization |
| 6 | Phase 6: Model Scopes & Enums | Medium | Low | Clean up query patterns |
| 7 | Phase 7: Services & Actions | High | Medium | Major refactor, needs tests in place first |
| 8 | Phase 8: API Resources | Low | Low | Polish |
| 9 | Phase 9: Frontend Cleanup | Medium | Low | Polish |
| 10 | Phase 10: Factory Improvements | Low | Low | Test infrastructure |

---

## Out of Scope

- New features (Telegram bot, recurring transactions, budget update/delete UI)
- Budget update/delete is **feature work**, not refactoring — tracked separately
- Performance optimization beyond N+1 fixes (Redis caching, etc.)
- CI/CD setup
- Database schema changes beyond fixing UserSettings mismatch

---

## Codex Review Summary

Reviewed by `gpt-5.3-codex` on 2026-02-23. Key findings that shaped this revision:

1. **Cross-tenant FK validation missing** — Form requests use generic `exists` rules, allowing users to reference other users' records
2. **Transfer lifecycle broken** — Delete/edit only affects one leg, orphaning the counterpart
3. **Original plan was abstraction-first** — Reordered to correctness-first, tests-first
4. **"Use Resources everywhere" was too broad** — Conflicts with project convention of raw arrays for dropdowns
5. **Several items were stale** — `CategoryResource` already exists, `pagination.tsx` already exists, show pages are dead code
6. **N+1 patterns underestimated** — `Currency::localeFor()`, `total_budgeted`, `full_name`, dashboard accessors
7. **BudgetService signatures mismatched** — Fixed to use `CarbonImmutable` and correct return types
8. **Factory integrity worse than listed** — Cross-user relationships in defaults across multiple factories
