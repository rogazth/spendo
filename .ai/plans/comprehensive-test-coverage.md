# Comprehensive Test Coverage Plan

## Context

The app has a `uuid` column bug (User model uses `HasUuid` trait but the separate migration `2026_02_01_000001_add_uuid_to_users_table.php` may not have been applied to the live DB), and broader test coverage is lacking. The existing web controllers (Accounts, PaymentMethods, Categories, Transactions, Budgets, Dashboard) have minimal or no feature tests. Only 2 BudgetTest tests fail (missing Vite manifest for `budgets/show.tsx`), and the MCP layer is well-tested (66 tests). We need full-stack tests so every code change can be validated with confidence.

### Current Test Inventory

| File | Tests | What it covers |
|------|-------|---------------|
| `Auth/*` (6 files) | ~20 | Registration, login, password reset, email verification, 2FA |
| `Settings/*` (3 files) | ~8 | Profile update, password update, 2FA settings |
| `DashboardTest.php` | 2 | Guest redirect, authenticated access |
| `RouteAccessTest.php` | 2 | Account/PM detail routes blocked |
| `TransactionFiltersTest.php` | 3 | Multi-PM filter, legacy filter, exclude_from_budget |
| `BudgetTest.php` | 4 | Create, parent-child reject, cycle calc, history scope |
| `McpToolsTest.php` | ~17 | Original MCP read tools |
| `McpSetupToolsTest.php` | ~40 | MCP CRUD tools + regression |
| `McpTransactionToolsTest.php` | 10 | MCP transaction tools |
| **Total** | **~106** | |

### What's NOT Tested

- **AccountController**: store, update, destroy, makeDefault (0 tests)
- **PaymentMethodController**: store, update, destroy, toggleActive, makeDefault (0 tests)
- **CategoryController**: store, update, show, destroy (0 tests)
- **TransactionController**: store (expense/income/transfer), update (all types), destroy (0 tests beyond 3 filter tests)
- **BudgetController**: index page rendering, show page with various scopes (2 tests fail due to missing Vite asset)
- **DashboardController**: data correctness (balances, monthly stats) (only access test exists)
- **Form Request validation**: StoreAccountRequest, StoreTransactionRequest, StoreBudgetRequest, StorePaymentMethodRequest, StoreCategoryRequest (0 dedicated tests)
- **Model logic**: computed attributes (current_balance, current_debt, formatted_amount, total_budgeted), HasUuid trait
- **Ownership/authorization**: cross-user access attempts on all controllers (0 tests)

---

## Phase 0: Fix Immediate Bugs

### 0.1 Merge uuid column into original users migration

The `HasUuid` trait on User causes the error. The project convention says "modify existing migrations instead of creating new ones." Merge the uuid column from `2026_02_01_000001_add_uuid_to_users_table.php` into `0001_01_01_000000_create_users_table.php`, then delete the separate migration.

**Files:**
- `database/migrations/0001_01_01_000000_create_users_table.php` — add `$table->uuid('uuid')->unique()->after('id');`
- `database/migrations/2026_02_01_000001_add_uuid_to_users_table.php` — delete

**Note:** After this, run `php artisan migrate:fresh` on local DB.

### 0.2 Fix BudgetTest Vite manifest failures

The 2 failing BudgetTest tests hit `budgets/show` route which renders an Inertia page that requires the Vite manifest. Either:
- **Option A** (recommended): Run `npm run build` before tests to generate the manifest
- **Option B**: Mock the Vite manifest in tests using `$this->withoutVite()`

We'll use `$this->withoutVite()` in the test file since it's simpler and doesn't require a build step.

**Files:**
- `tests/Feature/BudgetTest.php` — add `beforeEach(fn () => $this->withoutVite())` or wrap the relevant tests

---

## Phase 1: Model & Unit Tests

Test computed attributes and model behavior in isolation.

### 1.1 Account model tests
- `current_balance` computed attribute (sum of income - expenses for the account)
- `formatted_balance` accessor

### 1.2 Transaction model tests
- `amount` Attribute accessor (get: /100, set: *100)
- `formatted_amount` accessor
- `type` enum cast

### 1.3 PaymentMethod model tests
- `credit_limit` accessor
- `current_debt` computed attribute (sum of credit card expenses minus settlements)
- `available_credit` computed attribute

### 1.4 Budget/BudgetItem model tests
- `total_budgeted` accessor (sum of items / 100)
- `BudgetItem.amount` accessor

### 1.5 HasUuid trait test
- Verify UUID is auto-generated on creating
- Verify route key name is `uuid`

**File:** `tests/Unit/ModelTest.php`

---

## Phase 2: Controller CRUD Feature Tests

Each controller gets a dedicated test file following the pattern: `tests/Feature/{Entity}ControllerTest.php`

### 2.1 AccountControllerTest

| Test | Description |
|------|-------------|
| `index renders accounts page` | Auth user sees Inertia page with accounts |
| `guests cannot access accounts` | Redirect to login |
| `store creates an account` | POST /accounts with valid data |
| `store creates account with initial balance` | Creates income transaction |
| `store validates required fields` | Missing name/type/currency returns errors |
| `store rejects duplicate names` | Same user, same name |
| `store validates currency codes` | Invalid currency rejected (needs CurrencySeeder) |
| `update modifies account` | PUT /accounts/{uuid} |
| `update rejects another user's account` | 403 |
| `makeDefault sets account as default` | PATCH route |
| `destroy deletes account` | DELETE /accounts/{uuid} |
| `destroy rejects another user's account` | 403 |

**File:** `tests/Feature/AccountControllerTest.php`

### 2.2 PaymentMethodControllerTest

| Test | Description |
|------|-------------|
| `index renders payment methods page` | With linked accounts |
| `store creates credit card` | With credit_limit, billing_cycle_day |
| `store creates debit card linked to account` | linked_account_id |
| `store validates required fields` | Name, type |
| `store rejects duplicate names` | Same user |
| `update modifies payment method` | PUT |
| `update rejects another user's PM` | 403 |
| `makeDefault sets PM as default` | PATCH |
| `toggleActive toggles active state` | PATCH |
| `destroy deletes payment method` | DELETE |
| `destroy rejects another user's PM` | 403 |

**File:** `tests/Feature/PaymentMethodControllerTest.php`

### 2.3 CategoryControllerTest

| Test | Description |
|------|-------------|
| `index renders categories page` | Grouped by expense/income |
| `store creates expense category` | POST /categories |
| `store creates subcategory inheriting type` | With parent_id |
| `store validates required fields` | Name |
| `show renders category detail` | GET /categories/{uuid} |
| `update modifies category` | PUT |
| `update rejects system category edit` | 403 |
| `update rejects another user's category` | 403 |
| `destroy deletes category and orphans children` | Children get parent_id=null |
| `destroy rejects system category deletion` | 403 |

**File:** `tests/Feature/CategoryControllerTest.php`

### 2.4 TransactionControllerTest

| Test | Description |
|------|-------------|
| `index renders transactions page` | With filters data |
| `index filters by date range` | date_from, date_to |
| `index filters by account` | account_ids[] |
| `index filters by category` | category_ids[] |
| `store creates expense` | Standard expense with PM |
| `store creates income` | Income type |
| `store creates transfer` | Creates linked transfer_out + transfer_in |
| `store validates required fields per type` | account_id required for expense/income, origin/dest for transfer |
| `store validates currency` | Needs CurrencySeeder |
| `update modifies expense` | PUT /transactions/{uuid} |
| `update converts expense to transfer` | Type change |
| `update modifies transfer` | Updates both linked legs |
| `destroy deletes transaction` | DELETE |
| `destroy rejects another user's transaction` | 403 |

**File:** `tests/Feature/TransactionControllerTest.php`

### 2.5 BudgetControllerTest

| Test | Description |
|------|-------------|
| `index renders budgets page with cycle progress` | Inertia page with computed attributes |
| `store creates budget with items` | POST /budgets |
| `store rejects parent-child overlap` | Validation error |
| `store validates currency code` | Needs CurrencySeeder |
| `store validates account currency matches budget currency` | After validator |
| `show renders budget detail with summary` | GET /budgets/{uuid} (needs `withoutVite` or built assets) |
| `show supports history scope` | ?scope=history |
| `show rejects another user's budget` | 403 |

**File:** `tests/Feature/BudgetControllerTest.php`

### 2.6 DashboardControllerTest

| Test | Description |
|------|-------------|
| `dashboard shows correct total balance` | Sum of account balances |
| `dashboard shows correct credit debt` | Sum of credit card expenses minus settlements |
| `dashboard shows monthly stats` | Transaction count, monthly expenses |
| `dashboard shows recent transactions` | Last 10 transactions |

**File:** `tests/Feature/DashboardControllerTest.php`

---

## Phase 3: Form Request Validation Tests

Dedicated tests for validation rules to ensure they catch bad input.

### 3.1 Test validation rules for each FormRequest

One test file per request class, verifying:
- Required fields return errors when missing
- Type/enum validation catches invalid values
- Unique rules work (duplicate names)
- Cross-field validation (e.g., currency match in StoreBudgetRequest)
- Custom `withValidator` logic (StoreBudgetRequest parent-child overlap)

**Files:**
- `tests/Feature/Validation/StoreAccountValidationTest.php`
- `tests/Feature/Validation/StorePaymentMethodValidationTest.php`
- `tests/Feature/Validation/StoreTransactionValidationTest.php`
- `tests/Feature/Validation/StoreBudgetValidationTest.php`
- `tests/Feature/Validation/StoreCategoryValidationTest.php`

**Note:** These can also be covered inline in the controller tests. Decide based on complexity — if the controller test already covers validation, skip the dedicated file.

---

## Phase 4: Authorization & Security Tests

### 4.1 Cross-user access tests

For every entity (account, payment_method, category, transaction, budget), verify:
- User A cannot view/update/delete User B's resources
- Returns 403 (not 404 or data leakage)

These can be part of each controller test file (Phase 2) rather than a separate file.

### 4.2 Guest access tests

Verify all protected routes redirect to login for unauthenticated users. Can be a single test with a dataset of all routes.

**File:** `tests/Feature/AuthorizationTest.php`

---

## Phase 5: Seed & Setup Helpers

### 5.1 Create a test helper for currency seeding

Many tests need `CurrencySeeder` or manual `Currency::updateOrCreate`. Create a shared trait or use `beforeEach` in `Pest.php`.

**Option A** (recommended): Add to `tests/Pest.php`:
```php
beforeEach(function () {
    \App\Models\Currency::updateOrCreate(['code' => 'CLP'], ['name' => 'Peso chileno', 'locale' => 'es-CL']);
    \App\Models\Currency::updateOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'locale' => 'en-US']);
});
```

This ensures all feature tests have currencies available. Currently only `McpSetupToolsTest.php` does this, but `StoreAccountRequest`, `StoreTransactionRequest`, `StoreBudgetRequest`, and `StorePaymentMethodRequest` all use `Currency::codes()` in their validation rules.

**Option B**: Seed currencies in `tests/Pest.php` globally for all Feature tests.

### 5.2 withoutVite helper

Add `$this->withoutVite()` globally or per-test for tests that render Inertia pages requiring Vite assets.

---

## Execution Order

1. **Phase 0** — Fix uuid migration + BudgetTest Vite failures (unblock everything)
2. **Phase 5** — Setup test helpers (currency seeding, withoutVite)
3. **Phase 1** — Model unit tests (fast, no HTTP)
4. **Phase 2** — Controller CRUD tests (bulk of coverage)
5. **Phase 3** — Validation tests (if not covered in Phase 2)
6. **Phase 4** — Authorization tests (if not covered in Phase 2)

## Estimated Test Count

| Phase | New Tests |
|-------|-----------|
| Phase 1: Models | ~15 |
| Phase 2: Controllers | ~50 |
| Phase 3: Validation | ~20 (or 0 if covered in Phase 2) |
| Phase 4: Authorization | ~10 (or 0 if covered in Phase 2) |
| **Total new** | **~70-95** |
| **Existing** | **~116** |
| **Grand total** | **~186-211** |

## Verification

After implementation, run:
```bash
php artisan test --compact
```

All tests must pass with 0 failures. Target: **180+ tests, 400+ assertions**.

## Files Modified/Created

### Modified
- `database/migrations/0001_01_01_000000_create_users_table.php` — add uuid column
- `tests/Pest.php` — add global currency seeding + withoutVite
- `tests/Feature/BudgetTest.php` — fix Vite failures

### Deleted
- `database/migrations/2026_02_01_000001_add_uuid_to_users_table.php`

### Created
- `tests/Unit/ModelTest.php`
- `tests/Feature/AccountControllerTest.php`
- `tests/Feature/PaymentMethodControllerTest.php`
- `tests/Feature/CategoryControllerTest.php`
- `tests/Feature/TransactionControllerTest.php`
- `tests/Feature/BudgetControllerTest.php`
- `tests/Feature/DashboardControllerTest.php` (expand existing)
- `tests/Feature/AuthorizationTest.php`
