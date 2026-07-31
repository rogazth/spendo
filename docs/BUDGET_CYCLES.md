# Ciclos de budget, cierre e insights

## Decisión de modelo: intención congelada, hechos derivados

Un ciclo cerrado se guarda en `budget_cycles` + `budget_cycle_items`, pero **solo se
congela la intención**:

| Dato | Naturaleza | Dónde vive |
|---|---|---|
| Caps por categoría, currency, account, rango de fechas, categorías cubiertas | Intención del usuario en ese momento | Congelado en el snapshot |
| Gasto (`spent`) | Hecho, y los hechos se corrigen | Siempre recalculado desde `transactions` |

Consecuencias:

- Subir un cap hoy **no reescribe** el resultado de un ciclo ya cerrado.
- Registrar o editar una transacción con `transaction_date` dentro de un ciclo cerrado
  **sí** corrige ese ciclo — el filtro es por `transaction_date`, nunca por `created_at`.
- Ningún total se persiste. `BudgetCycleService::review()` recalcula en cada lectura.

Alternativas descartadas: recomputar todo con los caps actuales (pasado falso) y
versionar `budget_items` con `effective_from` (sobre-ingeniería para un solo usuario).

## Materialización

1. **Job diario**: `budgets:close-cycles` (schedule `00:30`, en `routes/console.php`)
   snapshotea el ciclo recién cerrado de cada budget. `is_backfilled = false`.
2. **Lazy**: ver un ciclo pasado que nunca se snapshoteó lo crea al vuelo con los caps
   actuales y marca `is_backfilled = true` — la UI lo declara explícitamente.

`budgets:close-cycles --lookback=N` camina N ciclos hacia atrás por budget.

## Post-mortem de ciclo (`/budgets/{uuid}?cycle=YYYY-MM-DD`)

Sin `cycle` la página muestra el ciclo activo. Con `cycle` (y solo si ese ciclo ya
cerró) cambia a modo post-mortem:

1. Veredicto en una línea: sobrante o exceso, y cuántas categorías se pasaron.
2. Barras divergentes por categoría: sobrante a la izquierda, exceso a la derecha.
3. Curva de ritmo: gasto acumulado real vs. el ritmo lineal que aguantaba el budget.
4. Los 5 gastos más grandes del ciclo.
5. Delta por categoría contra el ciclo anterior (top 5 por magnitud).

El selector de ciclo es un `FilterPill` + `SelectList` y navega por querystring.

## Insights (`/insights`)

Sección por currency, alimentada por `InsightsService`:

1. **Ingreso vs gasto** — 12 **ciclos** (nunca meses calendario): los buckets salen de
   `budget_cycle_start_day` vía `InsightsService::cyclePeriods()`. Con corte el 28, el 31 de
   julio cae en el ciclo que abrió el 28. El ciclo en curso se marca como tal en el tooltip.
2. **Racha de cumplimiento** — un cuadro por ciclo cerrado (cumplido / al límite /
   excedido); cada cuadro linkea a su post-mortem.
3. **Categorías que se están moviendo** — sparkline por ciclo y desvío del último ciclo
   **cerrado** contra el promedio de los anteriores. El ciclo en curso se excluye: tres días
   de ciclo no son una tendencia.
4. **Límites que no cuadran** — promedio gastado por ciclo vs. el cap actual, cuando la
   brecha supera el 15%.

## Dashboard

El dashboard responde dos preguntas y nada más: cuánta plata hay por cuenta y cuánta
queda libre. Por currency: disponible (`total − reservado`), lista de cuentas con saldo,
y solo los budgets excedidos o sobre el 90%. El detalle de budgets vive en `/budgets`
y el de cuentas en `/accounts`.
