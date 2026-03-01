# Spendo MCP Plan: Replace Two Finance Apps via ChatGPT/Claude

## Context
- Current user workflow uses two iOS apps:
  - House finance
  - Personal finance
- Target workflow is MCP-first:
  - Setup budgets from chat
  - Register transactions from chat
  - Request metrics from chat (remaining amount/percentage, category remaining, history by date range)

## Goal
Enable Spendo to fully operate as a chat-native personal finance system through MCP, so setup and daily operations do not depend on manual web UI usage.

## Success Criteria
1. User can create and maintain two monthly CLP budgets (`House`, `Personal`) entirely via MCP tools.
2. User can register all core transaction types from chat (`expense`, `income`, `transfer`, `settlement`).
3. User can request:
- Budget remaining amount and percentage
- Category-level remaining amount and percentage
- Transaction history by date range, optionally filtered by budget/category/account/payment method
4. OAuth-based MCP usage from ChatGPT/Claude works reliably end-to-end.

## Current State Snapshot

### Existing MCP tools
- `GetFinancialSummaryTool`
- `GetAccountsTool`
- `GetPaymentMethodsTool`
- `GetCategoriesTool`
- `GetTransactionsTool`
- `CreateTransactionTool` (currently expense/income-oriented)

### Existing domain capability in app (web-side)
- Budgets, budget items, accounts, payment methods, categories, transactions are already implemented in Laravel controllers/models/requests.
- Budget logic already computes cycle ranges and category progress in `BudgetController`.

### Known gaps / risks
1. MCP web auth route uses `auth:api`, but `api` guard is not defined in `config/auth.php`.
2. Money-unit contract is inconsistent across MCP descriptions/tests/formatting behavior.
3. MCP setup tools for accounts/payment methods/categories/budgets do not exist yet.
4. Budget metrics tooling is not exposed as first-class MCP tools.
5. Transaction MCP write coverage is incomplete for full lifecycle operations.

## External Review (Claude CLI) Incorporated
- Treat MCP auth as a hard blocker before any other phase.
- Freeze money-unit contract up front to avoid rework.
- Ship each tool together with:
  - prompt usage
  - feature test coverage
  in the same PR whenever possible.

## Product Decisions (Locked Before Implementation)

### 1) Money Contract Decision
- MCP input/output uses **major currency units** (example: `572000` CLP).
- Database remains integer cents internally.
- Serialization boundary handles conversion consistently.
- Tool descriptions and examples must reflect major-unit contract.

### 2) Budget Separation Model
- Keep two independent budgets:
  - `House`
  - `Personal`
- Each can optionally be account-scoped or global.
- Both remain monthly by default (unless user explicitly changes frequency).

### 3) Deterministic IDs in Chat Flows
- All read tools must return stable IDs and UUIDs to allow LLM tool chaining without ambiguity.

## Detailed Scope

### In Scope
- MCP auth reliability
- MCP write/setup tools for core domain entities
- Expanded transaction write support
- Budget and category metrics tools
- Better transaction history querying
- MCP prompts for guided setup and daily operations
- Feature tests and rollout controls

### Out of Scope (for this plan version)
- Telegram OCR flows
- Mobile app features
- Multi-user/shared budgets
- FX conversion and multi-currency budget aggregation

## Phased Implementation Plan

## Phase 0 - Blockers (Must Complete First)

### 0.1 MCP Auth Reliability
Deliverables:
- Define and configure a valid API guard strategy for MCP web route auth.
- Ensure `/api/mcp` validates OAuth bearer token correctly.
- Keep unauthenticated requests correctly denied.

Tests:
- MCP web authenticated call returns success.
- MCP web unauthenticated call returns auth error.
- OAuth authorization screen/redirect flow remains intact.

Definition of done:
- ChatGPT/Claude can connect and execute at least one read tool against `/api/mcp` using OAuth.

### 0.2 Money Contract Normalization
Deliverables:
- Remove contradictory centavos guidance from server/tool descriptions if using major units.
- Align all tool input schema descriptions and response formatting with one contract.
- Audit create + read + summary tools for double conversion.

Tests:
- Create transaction amount round-trip test.
- Summary totals format/value consistency tests.
- Account/payment method/tool output amount consistency tests.

Definition of done:
- One documented money contract used by all MCP tools.

## Phase 1 - Setup Tools (MCP replaces initial configuration UI)

### 1.1 Accounts
Add:
- `CreateAccountTool`
- `UpdateAccountTool`
- (Optional) `DeactivateAccountTool` or use update flag

Input fields:
- `name`, `type`, `currency`, `initial_balance`, `is_default`, `is_active`, optional display props

Validation:
- Ownership, unique name per user (consistent with FormRequest rules), valid currency/type.

### 1.2 Payment Methods
Add:
- `CreatePaymentMethodTool`
- `UpdatePaymentMethodTool`

Input fields:
- `name`, `type`, `linked_account_id`, `currency`, `credit_limit`, `billing_cycle_day`, `payment_due_day`, `is_default`, `is_active`

Validation:
- Ownership of linked account.
- Credit-card specific constraints.

### 1.3 Categories
Add:
- `CreateCategoryTool`
- `UpdateCategoryTool`

Input fields:
- `name`, `type`, optional `parent_id`, optional visual props

Validation:
- Ownership/system category constraints.
- Parent/child type consistency.

### 1.4 Budgets
Add:
- `CreateBudgetTool`
- `UpdateBudgetTool`
- `GetBudgetsTool`

Input fields:
- `name`, `description`, `currency`, `frequency`, `anchor_date`, optional `ends_at`, optional `account_id`, `items[]`

`items[]` fields:
- `category_id`, `amount`

Validation:
- Reuse existing rules:
  - category ownership and type checks
  - parent+child overlap prohibition
  - account-currency compatibility

Definition of done:
- A user can bootstrap both `House` and `Personal` budgets entirely via chat.

## Phase 2 - Transaction Registration Coverage

### 2.1 Transaction Types
Extend MCP write support for:
- `expense`
- `income`
- `transfer` (origin and destination accounts)
- `settlement` (credit card payment/liquidation)

### 2.2 Budget Interaction
- Preserve `exclude_from_budget` behavior for expenses.
- Settlement/transfer must not distort budget category consumption.

### 2.3 Idempotency
Add optional idempotency key:
- `idempotency_key` on create operations
- Deduplicate accidental repeated chat submissions

Definition of done:
- Daily ledger operations are fully executable through MCP without web UI fallback.

## Phase 3 - Metrics and Query Tools

### 3.1 Budget Summary Metrics
Add `GetBudgetMetricsTool`

Inputs:
- `budget_uuid` (or `budget_id`)
- `scope`: `current | history | custom`
- `start_date`, `end_date` (required for custom)

Outputs:
- `budgeted_amount`
- `spent_amount`
- `remaining_amount`
- `spent_percentage`
- `remaining_percentage`
- `cycle_start`, `cycle_end`

### 3.2 Category Progress Metrics
Add `GetBudgetCategoryMetricsTool` (or embed in budget metrics response)

Outputs per category item:
- `category_id`, `category_name`
- `budgeted_amount`
- `spent_amount`
- `remaining_amount`
- `spent_percentage`
- `remaining_percentage`

### 3.3 Transaction History Query
Enhance `GetTransactionsTool`:
- Date range filters (already present, keep)
- Optional filters:
  - `budget_uuid`
  - `category_ids[]`
  - `account_ids[]`
  - `payment_method_ids[]`
  - `type`
- Pagination:
  - `page`, `per_page` (or cursor model)
- Totals block for filtered dataset:
  - count
  - total debit
  - total credit

Definition of done:
- User can ask common questions in plain language and receive accurate metrics/history.

## Phase 4 - Prompt Layer for Chat UX

Add MCP prompts that orchestrate tools:

1. `setup_house_budget`
- Ensures required accounts/payment methods/categories exist
- Creates House budget with all category caps
- Returns summary and missing-field feedback

2. `setup_personal_budget`
- Same flow for personal budget

3. `register_transaction_and_show_impact`
- Creates transaction
- Immediately returns updated remaining values (budget + category)

4. `budget_status_summary`
- Returns concise overview for selected budget and period

Prompt requirements:
- Deterministic parameter extraction
- Explicit recovery when required data is missing
- Do not hallucinate IDs; always fetch candidates first

## Phase 5 - Testing, Hardening, and Rollout

### 5.1 Feature Tests (Pest)
For each new tool:
- Auth success/failure
- Validation success/failure
- Ownership boundaries
- Date range boundaries
- Amount conversion consistency
- Category hierarchy constraints
- Idempotency behavior

### 5.2 Operational Controls
- Structured logging for MCP tool invocations (safe fields only)
- Rate limits for write tools
- Observability counters:
  - tool call count
  - error rate by tool
  - validation failure rate

### 5.3 Release Sequence
1. Phase 0 (auth + money contract)
2. Setup tools
3. Transaction expansion
4. Metrics tools
5. Prompt layer
6. End-to-end acceptance on real scenarios

## MCP Tool Contract Draft (Target)

### `CreateBudgetTool` (draft)
Input:
```json
{
  "name": "House",
  "description": "Monthly house budget",
  "currency": "CLP",
  "frequency": "monthly",
  "anchor_date": "2026-03-01",
  "account_id": null,
  "items": [
    { "category_id": 12, "amount": 572000 },
    { "category_id": 13, "amount": 95000 }
  ]
}
```

Output:
```json
{
  "success": true,
  "budget": {
    "uuid": "....",
    "name": "House",
    "currency": "CLP",
    "frequency": "monthly",
    "total_budgeted": 1610205
  }
}
```

### `GetBudgetMetricsTool` (draft)
Input:
```json
{
  "budget_uuid": "....",
  "scope": "current"
}
```

Output:
```json
{
  "budget": {
    "uuid": "....",
    "name": "House"
  },
  "cycle": {
    "start": "2026-03-01",
    "end": "2026-03-31"
  },
  "summary": {
    "budgeted_amount": 1610205,
    "spent_amount": 803200,
    "remaining_amount": 807005,
    "spent_percentage": 49.88,
    "remaining_percentage": 50.12
  }
}
```

Note:
- Replace placeholder output with production-safe values and validated formatting.

## First Bootstrapped Budget Data (House)

Planned initial budget for chat setup:
- Rent: `572000`
- Housing: `95000`
- Water: `18000`
- Internet: `25000`
- Telephone: `17080`
- Electricity: `40000`
- Gas (utilities): `40000`
- Car insurance: `40000`
- Gas (car fuel): `85000`
- Car costs: `35000`
- Pet: `70000`
- Groceries: `330000`
- Taxes: `221125`
- Bank cost: `22000`

Total monthly House budget: `1610205 CLP`

## Acceptance Scenarios (E2E)
1. Setup:
- User says: "Create House budget with these categories and amounts."
- System creates budget and confirms totals.

2. Transaction:
- User says: "Log groceries 58900 CLP today on debit card."
- System creates expense and returns updated budget/category remaining.

3. Budget status:
- User says: "How much remains in House this month?"
- System returns remaining amount and percentage.

4. Category status:
- User says: "How much remains in Groceries in House?"
- System returns category-level remaining amount and percentage.

5. History:
- User says: "Show House transactions from 2026-03-01 to 2026-03-15."
- System returns filtered, paginated transactions + totals.

## Implementation Notes
- Reuse existing FormRequest/domain logic where possible to avoid duplicate rules.
- Keep tool responses both human-readable and machine-parseable.
- Prefer UUID usage in external tool contracts to reduce ambiguity and improve safety.
