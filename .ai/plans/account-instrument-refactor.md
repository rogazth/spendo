# Account → Instrument Refactor Plan

> Reviewed by Codex (gpt-5.3-codex) and Gemini 3 Pro. Critical issues incorporated below.

## Goal

Rethink the core financial model. The current `accounts` + `payment_methods` split is conceptually wrong. Replace it with:

- **Account** — a logical financial entity ("Personal", "Casa", "US Savings"). This is what budgets and transactions belong to. Optional budget. Tracks net balance via its transactions.
- **Instrument** — a physical bank account or credit card (optional). Tracks which physical card/account was used per transaction. Multiple accounts can share the same instrument.

Transactions always belong to an **Account** (except pure instrument-to-instrument transfers). Optionally, they reference an **Instrument**.

---

## New Mental Model

```
User
 └── Account ("Personal", CLP)         ← logical bucket, has a balance, optional budget
       └── Transactions                 ← expense/income — tagged to this account
             └── Instrument (optional)  ← Mercado Pago, Santander TDC, etc.

 └── Account ("Casa", CLP)
 └── Account ("US Savings", USD)
```

**Account balance** = sum of all expense/income/settlement transactions tagged to it.
- Income: adds to balance
- Expense: subtracts from balance
- Settlement: does NOT affect account balance (spending already recognized at purchase)
- Transfer: does NOT affect account balance (net worth unchanged)

**Instrument balance** = sum of all transactions using that instrument.
- For credit cards: outstanding debt = sum(expenses) - sum(settlements on that CC)
- For bank instruments: physical balance = income deposits - expenses - ATM withdrawals - settlements paid out

**Instruments are optional** — users can track just accounts and categories without any physical setup.

---

## CC Recognition Policy (Decision 1)

**Recognize spending at purchase time, not at settlement time.**

When you buy something with Santander TDC:
- Account ("Personal") drops immediately → the budget knows money is gone
- CC instrument debt increases

When you settle the CC bill:
- Account balance is **not touched** — spending was already recognized
- CC instrument debt decreases (settlement clears the card)
- Paying bank instrument (Mercado Pago) decreases

This prevents double-reduction: the account is only ever debited once per purchase.

---

## Settlement Semantics (Revised)

A settlement = paying off a credit card bill. It is a **purely instrument-level event**.

Fields:
- `type`: `settlement`
- `account_id`: **null** — does not affect any logical account balance
- `instrument_id`: the credit card being paid off (Santander TDC)
- `from_instrument_id`: the bank paying the bill (Mercado Pago) ← **new field**
- `amount`: how much is being paid

Effect:
- CC instrument debt: decreases by amount
- Paying bank instrument balance: decreases by amount
- Account balance: **unchanged**

---

## Transfer Semantics (Revised)

Two kinds of transfers:

**Inter-account transfer** (logical): Moving budget allocation from "Personal" to "Casa".
- Creates `transfer_out` + `transfer_in` transaction pair
- `account_id` is set (source and destination accounts)
- No instrument involvement needed

**Inter-instrument transfer** (physical): ATM withdrawal (Checking → Cash), bank-to-bank move.
- `account_id`: **null** — budget is unaffected, net worth is unchanged
- `instrument_id`: source instrument
- `to_instrument_id`: destination instrument ← **new field**
- These do NOT count as spending

---

## Data Model Changes

### `accounts` table (becomes the logical entity)

Remove `type` enum (`checking`, `savings`, `cash`, `investment`). An account is just a named bucket.

**Keep:** `id`, `uuid`, `user_id`, `name`, `currency`, `color`, `icon`, `is_active`, `is_default`, `sort_order`
**Remove:** `type`

### `instruments` table (renamed from `payment_methods`, absorbs physical bank types)

Replaces `payment_methods`. Physical bank accounts and credit cards both live here.

**Types:** `checking`, `savings`, `cash`, `investment`, `credit_card`, `prepaid_card`

Note: `debit_card` removed — a debit card is just a card face for a `checking` account, not a separate pool of money. `transfer` removed — it is a transaction type, not a physical instrument.

**Columns:**
- `id`, `uuid`, `user_id`
- `name` — "Mercado Pago", "Santander Visa"
- `type` — enum above
- `currency`
- `credit_limit` (nullable, credit cards only)
- `billing_cycle_day` (nullable, credit cards only)
- `payment_due_day` (nullable, credit cards only)
- `last_four_digits` (nullable)
- `color`, `icon`
- `is_active`, `is_default`, `sort_order`

No `linked_account_id` — instruments are independent. The account is chosen per-transaction.

### `transactions` table

- `account_id` → **nullable**. Required for expense/income. Null for settlements and inter-instrument transfers.
- `payment_method_id` → renamed to `instrument_id`, nullable. The primary instrument on the transaction.
- `from_instrument_id` (new, nullable) — the paying instrument on settlements and inter-instrument transfers.
- `exchange_rate` (new, nullable decimal) — FX rate when account currency ≠ instrument currency.
- `instrument_amount` (new, nullable bigint) — amount in instrument's currency when it differs from account currency.

### `budgets` table

No change. Already links to `account_id`.

---

## Sign Conventions

Consistent rule across all balances:

| Transaction type | Account effect | Instrument (primary) effect | Instrument (from) effect |
|---|---|---|---|
| expense | −amount | −amount (bank) / +debt (CC) | — |
| income | +amount | +amount | — |
| transfer_out | −amount | — | — |
| transfer_in | +amount | — | — |
| settlement | none | −debt (CC cleared) | −amount (bank paying) |
| inter-instrument transfer | none | −amount (source) | — (to_instrument +amount) |

Credit card outstanding debt is always expressed as a **positive number** (how much you owe).

---

## Opening Balance

To onboard an existing account/instrument that already has a balance, use a synthetic `income` transaction:
- `type`: `income`
- `category`: "Opening Balance" (system category)
- `account_id`: the logical account
- `instrument_id`: the instrument (if applicable)
- `amount`: current balance
- `transaction_date`: account creation date

This is the standard approach (used by YNAB and others). No special transaction type needed.

---

## Scope Boundaries (Out of Scope for Now)

- **Split transactions** — one purchase split across two logical accounts. Future feature.
- **Refunds/chargebacks** — record as income on the same account/instrument. Behavior is implicit.
- **Solvency check** — no enforcement that account allocations ≤ physical instrument balances. Informational dashboard only.
- **`from_instrument_id` on expense** — not tracking which bank paid for a non-CC expense. `instrument_id` is sufficient.

---

## Files to DELETE

### Pages (UI — will be remade as read-only)
```
resources/js/pages/accounts/show.tsx
resources/js/pages/payment-methods/           ← entire folder
resources/js/pages/budgets/show.tsx
```

### Controllers & Requests
```
app/Http/Controllers/PaymentMethodController.php
app/Http/Requests/StorePaymentMethodRequest.php
app/Http/Requests/UpdatePaymentMethodRequest.php
```

### MCP Tools (payment_method variants)
```
app/Mcp/Tools/CreatePaymentMethodTool.php
app/Mcp/Tools/UpdatePaymentMethodTool.php
app/Mcp/Tools/GetPaymentMethodsTool.php
```

---

## Files to UPDATE (Backend)

### Migrations (modify in-place, dev project)
- `2026_02_01_000002_create_accounts_table.php` — remove `type` enum
- `2026_02_01_000004_create_payment_methods_table.php` — rename table to `instruments`, update type enum (remove `debit_card`, `transfer`; add `checking`, `savings`, `cash`, `investment`), remove `linked_account_id`
- `2026_02_01_000005_create_transactions_table.php` — rename `payment_method_id` → `instrument_id`, make `account_id` nullable, add `from_instrument_id`, `exchange_rate`, `instrument_amount`

### Models
- `app/Models/Account.php` — remove `type`, update balance calculation (expense/income only, exclude settlements/transfers)
- `app/Models/PaymentMethod.php` → rename to `app/Models/Instrument.php` — update types, remove linked_account relationship, add balance/outstanding calculation
- `app/Models/Transaction.php` — rename `paymentMethod()` → `instrument()`, add `fromInstrument()`, update scope exclusions

### Controllers
- `app/Http/Controllers/AccountController.php` — remove type field, update index to include balance per account
- `app/Http/Controllers/TransactionController.php` — rename `payment_method_id` → `instrument_id`

### Form Requests
- `app/Http/Requests/StoreAccountRequest.php` — remove `type` validation
- `app/Http/Requests/UpdateAccountRequest.php` — remove `type` validation
- `app/Http/Requests/StoreTransactionRequest.php` — rename field, add `from_instrument_id`
- `app/Http/Requests/UpdateTransactionRequest.php` — same

### Routes (`routes/web.php`)
- Remove all `payment-methods` routes
- Add `instruments` routes (index — read-only)

### Factories & Seeders
- `AccountFactory` — remove `type`
- `PaymentMethodFactory` → `InstrumentFactory` — update types

### MCP Server
- `app/Mcp/Servers/SpendoServer.php` — swap payment method tools for instrument tools

---

## Files to UPDATE (Frontend — strip to read-only)

### Keep and simplify:
- `resources/js/pages/accounts/index.tsx` — account list with balance per account + total by currency. No create/edit buttons.
- `resources/js/pages/categories/index.tsx` — flat read-only list.
- `resources/js/pages/transactions/index.tsx` — read-only list with filters.
- `resources/js/pages/budgets/index.tsx` — read-only budget progress.

### Add:
- `resources/js/pages/instruments/index.tsx` — read-only list of instruments with balance / outstanding debt.

---

## New MCP Tools

| Tool | Description |
|------|-------------|
| `CreateInstrumentTool` | Create a bank account or credit card instrument |
| `UpdateInstrumentTool` | Update instrument details |
| `GetInstrumentsTool` | List instruments with current balance/outstanding debt |

---

## Updated MCP Tools

| Tool | Changes |
|------|---------|
| `CreateAccountTool` | Remove `type` field |
| `UpdateAccountTool` | Remove `type` field |
| `GetAccountsTool` | Remove `type`, add balance (expense/income only) |
| `CreateTransactionTool` | `account_id` nullable, `payment_method_id` → `instrument_id`, add `from_instrument_id` |
| `GetTransactionsTool` | Field rename in output |
| `GetFinancialSummaryTool` | Account balances + instrument outstanding debt |
| `BulkCreateTransactionsTool` | Field rename |

---

## Tests to Update

- `tests/Feature/McpTransactionToolsTest.php` — rename `payment_method_id` → `instrument_id`, update fixtures, add settlement/transfer test cases
- `tests/Feature/McpToolsTest.php` — update account tests (no type), add instrument tests
- `tests/Feature/McpSetupToolsTest.php` — swap payment method setup for instrument setup

---

## Execution Order

1. **Migrations** — modify in-place, run `php artisan migrate:fresh --seed`
2. **Models** — rename PaymentMethod → Instrument, update Account, Transaction
3. **Form Requests** — update field names
4. **Controllers** — update AccountController, TransactionController; delete PaymentMethodController
5. **Routes** — update web.php and any Wayfinder references
6. **MCP Tools** — delete PM tools, create Instrument tools, update existing tools
7. **MCP Server** — re-register tools
8. **UI** — delete listed pages, strip remaining pages to read-only
9. **Tests** — update to match new model
10. **Pint + TypeScript check** — `vendor/bin/pint --dirty` and `npx tsc --noEmit`
