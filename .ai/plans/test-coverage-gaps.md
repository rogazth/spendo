# Test Coverage Gaps Plan

_Reviewed by: Claude (own), Codex (gpt-5.3-codex), Gemini 3 Pro_
_Date: 2026-03-03_

---

## Summary of Reviews

All three reviewers agreed on the same high-priority gaps. The current test suite is well-structured but misses coverage for:

1. **Core business logic regressions** — settlement accounting, transfer integrity, budget filtering
2. **Authorization boundary enforcement** — cross-user access in both web and MCP layers
3. **Validation completeness** — amount bounds, multi-currency requirements, read-only routes
4. **MCP tool correctness** — numeric assertions, data isolation, missing type coverage

The app was recently refactored from `PaymentMethod → Instrument`. The refactor is correct and test files are updated, but the new model's business rules lack full regression protection.

---

## MCP Status Check

- **SpendoServer.php**: ✅ Clean — all instrument tools registered, no PaymentMethod references
- **Prompts fixed**: `SetupBudgetPrompt` and `RegisterTransactionPrompt` were still referencing `GetPaymentMethodsTool` → fixed to `GetInstrumentsTool`
- **All MCP Tools**: ✅ No stale `PaymentMethod`, `payment_method`, or `AccountType` references

---

## Priority 1 — Critical Business Logic (high regression risk)

These tests directly encode the domain model. If any of these fail after a business logic change, we want to know immediately.

### 1.1 Account Balance Formula
**File:** `tests/Unit/ModelTest.php` — add to "Account current_balance" describe block

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Balance includes `transfer_in` and excludes `transfer_out` | Two accounts: A gets `transfer_out`, B gets `transfer_in` of same amount. `A.balance = income - transfer_out`, `B.balance = transfer_in` |
| B | Settlement does NOT affect account balance | Add income to account, add settlement with `account_id=null`. `account.current_balance` equals income only |
| C | Soft-deleted transactions are excluded from balance | Create income + expense, soft-delete income. Balance equals `-expense` |

### 1.2 Instrument Debt & Balance
**File:** `tests/Unit/ModelTest.php` — add to "Instrument" describe block

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Debt isolation between two credit cards | Card A has 2 expenses, Card B has 1 expense. `cardA.current_debt` ignores Card B's expense |
| B | Multiple expenses + multiple settlements sum correctly | 2 expenses (500+300), 2 settlements (200+100). `current_debt == 400` |
| C | `available_credit` is `null` for non-CC instruments | `Instrument::factory()->checking()`. `available_credit === null` |
| D | `available_credit` is `null` when `credit_limit` is null | `Instrument::factory()->creditCard()->create(['credit_limit' => null])`. `available_credit === null` |
| E | `current_balance` for CC is `-current_debt` | Expense of 500 on CC. `cc.current_balance == -500` |
| F | `current_balance` for bank instrument subtracts outgoing settlements | Bank has income 1000. Settlement `from_instrument_id=bank` for 300. `bank.current_balance == 700` |

### 1.3 Instrument Amount & Exchange Rate Fields
**File:** `tests/Unit/ModelTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | `instrument_amount` round-trips through accessor | Set 500 major units → raw value is 50000 cents → read back as 500 |
| B | `exchange_rate` persists as-is (decimal, no accessor) | Set 0.00125 → read back 0.00125 |

### 1.4 Settlement Accounting (Critical Path)
**Files:** `tests/Feature/TransactionControllerTest.php`, `tests/Unit/ModelTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Web controller creates settlement with `account_id=null` | POST `/transactions` with `type=settlement`. DB has `account_id=null`, `instrument_id=creditCard.id`, `from_instrument_id=bank.id` |
| B | Settlement reduces CC debt without touching account balance | Expense 1000 → account balance -1000, CC debt 1000. Settlement 500 → account balance STILL -1000, CC debt 500 |
| C | Settlement is excluded from budget spending | Add settlement matching a budget category+date. `summary.spent == 0` |

### 1.5 Transfer Integrity
**File:** `tests/Feature/TransactionControllerTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Both transfer legs have identical amounts | `transfer_out.amount == transfer_in.amount` (amounts not duplicated from accessor) |
| B | Deleting one transfer leg soft-deletes the linked leg | Delete `transfer_out`. `transfer_in` is also soft-deleted |

### 1.6 Budget Spending Rules
**Files:** `tests/Feature/BudgetTest.php`, `tests/Feature/BudgetControllerTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Only `expense` type counts toward spending | Add `income` + `transfer_out` + `settlement` in same category+date. `summary.spent == 0` |
| B | Cycle boundary: exact start date IS included | Transaction on `anchor_date` exactly. `summary.spent > 0` |
| C | Cycle boundary: exact end date IS included | Transaction on last day of cycle. `summary.spent > 0` |
| D | Transaction one day before cycle start is excluded | `summary.spent == 0` |
| E | Account-scoped budget filters by `account_id` | Two accounts, budget scoped to Account A. Expense on Account B same category. `summary.spent == 0` |
| F | Biweekly frequency computes correct cycle range | Anchor March 1, test date March 10 → range is March 1–14 |

---

## Priority 2 — Authorization & Security

### 2.1 Web Controller Cross-User Access
**Files:** `tests/Feature/TransactionControllerTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Settlement with `from_instrument_id` from another user → 403 | Instrument belongs to User A, request from User B → `assertForbidden()` |
| B | Transfer with origin/destination account from another user → 403 | Both accounts belong to User A, request from User B → `assertForbidden()` |
| C | Store expense with `category_id` from another user → OK (categories are shared) or rejected | Verify current behavior is intentional |

### 2.2 MCP Tool Data Isolation
**Files:** `tests/Feature/McpToolsTest.php`, `tests/Feature/McpTransactionToolsTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | `GetAccountsTool` returns only user's accounts | User A has "Account A", User B has "Account B". Acting as A, response contains "Account A" and NOT "Account B" |
| B | `GetInstrumentsTool` returns only user's instruments | Same pattern as above |
| C | `GetTransactionsTool` returns only user's transactions | Same pattern |
| D | `CreateTransactionTool` with cross-user `account_id` → error | Acting as User B, provide User A's account_id. Response has errors, no transaction created |
| E | `CreateTransactionTool` with cross-user `instrument_id` → error | Same pattern |

---

## Priority 3 — Validation Completeness

### 3.1 Transaction Validation
**File:** `tests/Feature/TransactionControllerTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Settlement requires `instrument_id` | POST settlement without `instrument_id` → `assertSessionHasErrors('instrument_id')` |
| B | Amount must be positive | POST with `amount=0` or `amount=-1` → `assertSessionHasErrors('amount')` |
| C | `transaction_date` must be a valid date | POST with `transaction_date='not-a-date'` → `assertSessionHasErrors('transaction_date')` |
| D | Multi-currency: `instrument_amount` required when `currency ≠ account.currency` | If this validation is intended, add test for it; if not, document the relaxed rule |

### 3.2 Account Validation
**File:** `tests/Feature/AccountControllerTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Negative `initial_balance` is rejected | `assertSessionHasErrors('initial_balance')` |

### 3.3 Budget Validation
**File:** `tests/Feature/BudgetControllerTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Budget item with `amount <= 0` is rejected | `assertSessionHasErrors('items.0.amount')` |
| B | Duplicate category in same budget is rejected | Two items with same `category_id` → validation error |

### 3.4 Instrument Routes Are Read-Only
**File:** `tests/Feature/PaymentMethodControllerTest.php` (or rename to `InstrumentControllerTest.php`)

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | `POST /instruments` returns 405 | `assertStatus(405)` |
| B | `PUT /instruments/{uuid}` returns 405 or 404 | Route does not exist |

---

## Priority 4 — MCP Tool Coverage

### 4.1 GetFinancialSummaryTool — Numeric Assertions
**File:** `tests/Feature/McpToolsTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Returns exact `total_account_balance` in major units | Set income 1000 CLP → `total_account_balance == 1000` (not 100000 cents) |
| B | Returns exact `total_credit_debt` in major units | Expense 500 on CC → `total_credit_debt == 500` |
| C | Returns `net_balance = total_account_balance - total_credit_debt` | Verify computation |

### 4.2 GetInstrumentsTool — Inactive Filter
**File:** `tests/Feature/McpToolsTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Inactive instruments excluded by default | Create inactive instrument. Default call doesn't include it |
| B | `include_inactive=true` includes inactive | Inactive instrument appears |

### 4.3 CreateInstrumentTool — Type Coverage
**File:** `tests/Feature/McpSetupToolsTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Create `cash` type instrument | DB has `type=cash`, no credit fields |
| B | Create `investment` type instrument | DB has `type=investment` |
| C | Create `prepaid_card` with `last_four_digits` | DB has `last_four_digits` set |

### 4.4 UpdateInstrumentTool — Edge Cases
**File:** `tests/Feature/McpSetupToolsTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Duplicate name is rejected | Two instruments same name → error response |
| B | Setting `is_default=true` clears previous default | Old default becomes false |

### 4.5 MCP Transfer Linkage
**File:** `tests/Feature/McpTransactionToolsTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | MCP transfer creates bidirectional `linked_transaction_id` | `transfer_out.linked_transaction_id == transfer_in.id` AND `transfer_in.linked_transaction_id == transfer_out.id` |

### 4.6 BulkCreateTransactionsTool — Settlement & Transfer
**File:** `tests/Feature/McpTransactionToolsTest.php`

| # | Scenario | Key Assertion |
|---|----------|---------------|
| A | Bulk includes a settlement → `account_id=null`, debt reduced | Settlement in batch. DB has `account_id=null` for that row |
| B | Bulk includes a transfer → both legs created and linked | Transfer in batch. Two rows created with matching `linked_transaction_id` |
| C | Partial success: valid + invalid rows → reports both counts | 1 valid income + 1 settlement missing `instrument_id` → `succeeded=1, failed=1` |

---

## Priority 5 — Model Edge Cases (Optional / Nice to Have)

| # | File | Scenario | Key Assertion |
|---|------|----------|---------------|
| 1 | `McpSetupToolsTest.php` | `CreateAccountTool` `initial_balance` stored in cents | `getRawOriginal('amount') == 1000000 * 100` for 1,000,000 CLP |
| 2 | `McpSetupToolsTest.php` | `CreateBudgetTool` item amounts stored in cents | `BudgetItem->getRawOriginal('amount') == 250000 * 100` |
| 3 | `BudgetTest.php` | Weekly frequency cycle calculation | Anchor Monday March 2, test date March 5 → range March 2–8 |

---

## File Housekeeping

| Issue | Action |
|-------|--------|
| `tests/Feature/PaymentMethodControllerTest.php` — name is stale | Rename to `InstrumentControllerTest.php` (optional, tests are correct) |
| MCP prompts had stale `GetPaymentMethodsTool` references | ✅ Fixed |

---

## Implementation Order

1. **Priority 1** — implement in one batch. These are the safety net for regressions.
2. **Priority 2** — implement in one batch. Security critical.
3. **Priority 3** — implement per-file during regular feature work.
4. **Priority 4** — implement alongside MCP changes.
5. **Priority 5** — implement only if time allows; low risk of regression.
