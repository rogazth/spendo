# Budget Fixes Plan

Four fixes for the budget feature: account filtering, category dropdown scroll, radial charts, and histórico UUID bug.

---

## 1. Account Select — Filter by Selected Currency

**Files:** `resources/js/components/forms/budget-form-dialog.tsx`, `app/Http/Requests/StoreBudgetRequest.php`

**Problem:** The account dropdown shows all accounts regardless of the selected currency. When creating a budget with currency USD, CLP accounts shouldn't appear.

**Frontend fix:**
- Derive `filteredAccounts` from `accounts` filtered by `data.currency`
- Render `filteredAccounts` in the account `<Select>` instead of all `accounts`
- Clear `account_id` synchronously in the currency `onValueChange` handler when the selected account doesn't match the new currency (avoid stale state from useEffect)
- Keep the "Todas las cuentas" option always visible (it works with any currency)

**Backend fix:**
- Add server-side validation in `StoreBudgetRequest` to enforce that when `account_id` is provided, the account's currency must match the submitted `currency` field. This prevents crafted requests from bypassing the UI filter.

---

## 2. Category Dropdown — Fix Vertical Growth

**File:** `resources/js/components/forms/budget-form-dialog.tsx`

**Problem:** The category `<SelectContent>` grows vertically when scrolling through many categories instead of keeping a fixed height and scrolling internally.

**Root cause:** Radix Select's default `position="item-aligned"` tries to align the selected item with the trigger, which can cause the popup to expand rather than scroll.

**Fix:**
- Add `position="popper"` to the category `<SelectContent>` in the budget items loop (line ~430)
- The `SelectContent` component already handles `position="popper"` styling (see `select.tsx:66-67`). The viewport inside it already gets `scroll-my-1` for popper mode.
- If the viewport height is too short in popper mode, adjust with a className override.

---

## 3. Radial Charts for Category Progress

**File:** `resources/js/pages/budgets/show.tsx`

**Problem:** Category progress is shown as a grouped BarChart. The user wants individual radial charts per category, with the main chart showing remaining budget amount in its center.

**Approach:**
- Replace the "Progreso general" BarChart with a `RadialBarChart` showing overall budget usage as a single radial arc. Display the remaining budget amount as centered text using a custom `<text>` element inside the chart.
- Replace the "Progreso por categoría" BarChart with a grid of individual `RadialBarChart` components — one per `categoryProgress` item.
- Each category gets its own `ChartContainer` wrapping its `RadialBarChart` (since `ChartContainer` wraps a single `ResponsiveContainer`).
- Each category radial uses the category's `category_color` as the fill color and shows `percentage` as the arc fill.
- Below each radial, show the category name and spent/budgeted amounts.

**New imports:**
```tsx
import { RadialBar, RadialBarChart, PolarAngleAxis } from 'recharts';
```

**Main budget radial (overall progress):**
- Single radial arc representing `summary.percentage` out of 100
- Center text: formatted `summary.remaining` amount with "Disponible" label
- Use `PolarAngleAxis` with `domain={[0, 100]}` for percentage scale
- `startAngle={90}` / `endAngle={-270}` for a top-starting clockwise arc
- **Over-budget:** When `summary.remaining < 0`, show the remaining as a negative amount in red text. The arc stays full (100% capped by backend).

**Category radials (grid):**
- `grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4` layout
- Each item: one `ChartContainer` wrapping a small `RadialBarChart` (~120px) with the category color
- Center text: percentage
- Below chart: category name, "Gastado / Presupuestado" amounts
- No tooltips needed on individual radials — the text below provides the data

---

## 4. Histórico Tab — UUID "undefined" Bug

**Files:** `resources/js/pages/budgets/show.tsx`

**Problem:** Clicking "Histórico" sends `GET /budgets/undefined?scope=history` because `budget.uuid` evaluates to `undefined` in JavaScript, causing a PostgreSQL error.

**Root cause:** Unknown — `BudgetResource` includes `uuid`, the TS type requires it, and route-model binding uses it. The exact serialization issue needs diagnosis.

**Fix:**
1. Add a temporary `console.log('budget prop:', budget)` at the top of the component to diagnose the actual payload structure. This will be removed after confirming the fix.
2. Use Wayfinder's type-safe route function instead of manual URL construction. The project already has generated Wayfinder routes at `@/actions/App/Http/Controllers/BudgetController`:

```tsx
import { show } from '@/actions/App/Http/Controllers/BudgetController';

// In button onClick:
router.get(
    show.url(budget, { query: { scope: 'history' } }),
    {},
    { preserveScroll: true, preserveState: true, replace: true }
);
```

Wayfinder's `show()` accepts `{ uuid: string }` objects directly and properly resolves the UUID. Even if this doesn't fix the underlying issue, it aligns with project conventions. The console.log will help diagnose if `budget.uuid` is truly missing.

Also update the "Ciclo actual" and "Volver" buttons, and the breadcrumb to use Wayfinder routes for consistency.

---

## Execution Order

1. **Fixes 1 + 2** together in `budget-form-dialog.tsx` + backend validation
2. **Fix 4** (Histórico UUID bug + Wayfinder migration) in `show.tsx`
3. **Fix 3** (Radial charts) in `show.tsx` — largest change, applied last

## Files Modified

| File | Fixes |
|------|-------|
| `resources/js/components/forms/budget-form-dialog.tsx` | 1, 2 |
| `app/Http/Requests/StoreBudgetRequest.php` | 1 (backend validation) |
| `resources/js/pages/budgets/show.tsx` | 3, 4 |
