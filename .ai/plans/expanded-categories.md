# Plan: Expanded Category Seeder

## Goal

Expand the default system categories to better cover real-world spending patterns.
Reference: a competing finance app showing categories grouped under Miscellaneous, Entertainment,
Food & drinks, Housing, Income, Lifestyle, Savings, and Transportation.

## Approach

1. Update `CategorySeeder` to use `updateOrCreate` instead of `create` — safe to re-run on
   existing databases without duplicating records.
2. Add new subcategories to existing parent categories where the group already exists.
3. Add four new parent categories for groupings not currently covered.
4. Keep all names in Spanish for consistency with existing categories.
5. No categories are deleted or renamed — only additions.

---

## New Subcategories for Existing Parents

### Servicios (currently: Agua, Electricidad, Gas, Internet, Teléfono)
| Name | Icon | Color | Notes |
|------|------|-------|-------|
| Televisión | `tv` | `#6366F1` | Cable/satellite TV bill |
| Impuestos | `receipt` | `#EF4444` | Property taxes, municipal fees |

### Vivienda (currently: Arriendo, Dividendo, Mantención, Seguros)
| Name | Icon | Color | Notes |
|------|------|-------|-------|
| Gasto Común | `building-2` | `#8B5CF6` | HOA / condo fees |
| Suministros del Hogar | `package` | `#84CC16` | Cleaning supplies, household items |
| Préstamo Hipotecario | `hand-coins` | `#F59E0B` | Mortgage / home loan |

### Alimentación (currently: Supermercado, Restaurantes, Delivery, Café)
| Name | Icon | Color | Notes |
|------|------|-------|-------|
| Golosinas | `candy` | `#EC4899` | Snacks, candy, sweets |
| Bebidas | `wine` | `#7C3AED` | Alcohol, soft drinks bought standalone |

### Transporte (currently: Combustible, Transporte Público, Uber/Taxi, Estacionamiento, Peajes)
| Name | Icon | Color | Notes |
|------|------|-------|-------|
| Seguro de Auto | `shield-check` | `#10B981` | Car insurance premiums |
| Préstamo Auto | `car` | `#F59E0B` | Car loan installments |
| Vuelos | `plane` | `#6366F1` | Domestic/international flights |
| Reparación | `wrench` | `#F97316` | Car repairs and servicing |

### Entretenimiento (currently: Streaming, Juegos, Salidas, Hobbies)
| Name | Icon | Color | Notes |
|------|------|-------|-------|
| Cine | `clapperboard` | `#E11D48` | Movie tickets |
| Boliche | `circle-dot` | `#EC4899` | Bowling alley |
| Conciertos | `mic` | `#EC4899` | Live music / events |
| Discoteca | `beer` | `#7C3AED` | Nightclubs, bars |
| Deportes | `trophy` | `#3B82F6` | Sports events (watching) |
| Gimnasio | `dumbbell` | `#EC4899` | Gym / fitness memberships |
| Vacaciones | `palm-tree` | `#10B981` | Holiday spending |

### Salud (currently: Médico, Farmacia, Seguro de Salud)
| Name | Icon | Color | Notes |
|------|------|-------|-------|
| Dentista | `tooth` | `#EC4899` | Dental care |
| Óptica | `glasses` | `#06B6D4` | Eye care, glasses, contacts |
| Psicólogo | `brain` | `#8B5CF6` | Mental health |

### Compras (currently: Ropa, Tecnología, Hogar)
| Name | Icon | Color | Notes |
|------|------|-------|-------|
| Electrónica | `cpu` | `#6366F1` | Electronics purchases |
| Accesorios | `watch` | `#F97316` | Jewelry, bags, accessories |
| Deportes y Outdoors | `bike` | `#3B82F6` | Sporting goods |

---

## New Parent Categories

### 1. Mascotas (Expense) `#F97316` icon: `paw-print`
For pet owners — currently completely absent from the seeder.

| Name | Icon | Color |
|------|------|-------|
| Alimento Mascota | `utensils` | `#F97316` |
| Veterinario | `stethoscope` | `#10B981` |
| Accesorios Mascota | `package` | `#F59E0B` |
| Peluquería Mascota | `scissors` | `#EC4899` |

### 2. Viajes (Expense) `#0EA5E9` icon: `map-pin`
Travel deserves its own parent separate from day-to-day Transport.

| Name | Icon | Color |
|------|------|-------|
| Hotel | `bed` | `#0EA5E9` |
| Tour / Actividades | `map` | `#10B981` |
| Seguro de Viaje | `shield` | `#6366F1` |
| Equipaje | `luggage` | `#F59E0B` |

### 3. Estilo de Vida (Expense) `#EC4899` icon: `sparkles`
Covers lifestyle/social spending not cleanly fitting elsewhere.

| Name | Icon | Color |
|------|------|-------|
| Donaciones | `heart-handshake` | `#EC4899` |
| Cuidado Infantil | `baby` | `#FB923C` |
| Regalos | `gift` | `#EC4899` |
| Trabajo / Oficina | `briefcase` | `#6366F1` |
| Comunidad | `users` | `#84CC16` |

### 4. Finanzas (Expense) `#6B7280` icon: `landmark`
Bank fees, student loans, and general financial costs.

| Name | Icon | Color |
|------|------|-------|
| Comisiones Bancarias | `landmark` | `#6B7280` |
| Préstamo Estudiantil | `graduation-cap` | `#6366F1` |
| Intereses | `percent` | `#EF4444` |
| Multas | `alert-triangle` | `#F97316` |

---

## New Income Subcategories

Add to existing parent `Inversiones`: no changes needed (already has Dividendos, Intereses, Ganancias).

Add new standalone income categories:

| Name | Icon | Color | Notes |
|------|------|-------|-------|
| Pensión | `piggy-bank` | `#10B981` | Retirement pension |
| Beneficio Familiar | `users` | `#06B6D4` | Child/family government benefits |
| Bono / Aguinaldo | `gift` | `#EC4899` | Holiday bonus, one-time bonuses |
| Venta de Activos | `trending-up` | `#22C55E` | Selling car, property, goods |

---

## Savings Note

The reference app shows a **Savings** group (Emergency savings, Savings, Vacation savings) as a
separate category type. Our app currently has `income` and `expense` types (plus `system`).
Savings transactions can be modeled as income in Spendo (money set aside). No new category type is
needed — add these as income categories:

| Name | Icon | Color |
|------|------|-------|
| Ahorro de Emergencia | `star` | `#06B6D4` |
| Ahorro General | `piggy-bank` | `#06B6D4` |
| Ahorro para Vacaciones | `palm-tree` | `#06B6D4` |

---

## Implementation Steps

### Step 1 — Refactor `CategorySeeder` to use `updateOrCreate`

Change `Category::create([...])` → `Category::updateOrCreate(['name' => ..., 'user_id' => null, 'parent_id' => ...], [...])`.
This makes the seeder idempotent and safe to run on production via `php artisan db:seed --class=CategorySeeder`.

### Step 2 — Add new subcategories to existing arrays

Insert the new children into the `$expenseCategories` and `$incomeCategories` arrays in
`CategorySeeder`.

### Step 3 — Add new parent arrays

Append Mascotas, Viajes, Estilo de Vida, and Finanzas to `$expenseCategories`.
Append new income entries (Pensión, Beneficio Familiar, Bono, Venta de Activos) to
`$incomeCategories`.
Append Ahorro de Emergencia, Ahorro General, Ahorro para Vacaciones to `$incomeCategories`.

### Step 4 — Run and verify

```bash
php artisan db:seed --class=CategorySeeder
```

Or on a fresh database:
```bash
php artisan migrate:fresh --seed
```

### Step 5 — Update tests

`CategorySeeder` is used in `DummyDataSeeder`. Any tests that assert exact category counts will
need updating.

---

## Summary of Changes

| What | Count |
|------|-------|
| New subcategories added to existing expense parents | +20 |
| New expense parent categories | +4 (Mascotas, Viajes, Estilo de Vida, Finanzas) with 15 children |
| New income standalone categories | +4 |
| New income "savings" categories | +3 |
| **Total new categories** | **~42** |
