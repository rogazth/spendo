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

Insights responde una sola pregunta: **en qué se fue la plata**. Dos bloques, cada uno
con sección por currency, alimentados por `InsightsService`. Nada de cumplimiento de
budgets: eso vive en `/budgets`.

### Bloque 1 — El año (`?year=YYYY`)

1. KPIs: gastado, ingresado, ahorro neto y promedio por ciclo del año.
2. Barras ingreso/gasto + línea de neto, **un punto por ciclo, nunca por mes calendario**.
   `InsightsService::yearPeriods()` toma los ciclos que *cierran* dentro del año, así que
   con corte el 28 "enero" corre del 28-dic al 27-ene. El año en curso llega hasta el ciclo
   en progreso.
3. Gasto por categoría del año, con delta contra el año anterior **a la misma altura**
   (mismo número de ciclos), para que un año en curso no compita contra uno completo.

### Bloque 2 — El ciclo (`?cycle=YYYY-MM-DD`)

4. Por defecto el ciclo en curso; el selector ofrece los últimos 24 con flechas ‹ › para
   saltar de a uno.
5. Gastado / ingresado / neto del ciclo, y días restantes si está abierto.
6. Gasto por categoría con delta contra el ciclo anterior, más los 5 gastos más grandes.

### Reglas del desglose por categoría

- Agrupa por **categoría raíz**; las subcategorías se despliegan dentro. El gasto cargado
  directo a la raíz aparece como "Sin subcategoría" para que las partes sumen el total.
- Cuenta **todo el gasto**, incluido `exclude_from_budget`: la pregunta es dónde se fue la
  plata, no si el cap aguantó. Los números pueden no coincidir con los de un budget.
- Transferencias y saldos iniciales quedan fuera (solo `type = regular` con monto negativo).
- Cada fila linkea a `/transactions` con la categoría y el rango ya filtrados.

Los dos bloques son props lazy: cambiar de ciclo recarga solo `cycle` y cambiar de año
solo `year`.

## Dashboard

El dashboard responde dos preguntas y nada más: cuánta plata hay por cuenta y cuánta
queda libre. Por currency: disponible (`total − reservado`), lista de cuentas con saldo,
y solo los budgets excedidos o sobre el 90%. El detalle de budgets vive en `/budgets`
y el de cuentas en `/accounts`.
