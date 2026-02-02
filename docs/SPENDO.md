# CLAUDE.md - Spendo

## Project Overview

**Spendo** es una app de finanzas personales que resuelve un problema específico: separar **cuentas** (donde está el dinero) de **métodos de pago** (cómo pagas).

- **Dominio**: spendo.cl
- **Target**: Usuarios LATAM que manejan múltiples TDC y liquidan mensualmente
- **Diferenciador**: Bot de Telegram para registro de gastos mediante OCR + configuración flexible de períodos de presupuesto

### El Problema

Las apps existentes asumen que método de pago = cuenta. En la realidad:
- El dinero está en cuentas (corriente, ahorro, efectivo, inversiones)
- Pagas con TDC y liquidas a fin de mes
- Necesitas saber: saldo por cuenta, deuda por TDC, gastos por categoría

### La Solución

1. Modelo de datos que separa Account vs PaymentMethod
2. Presupuestos con períodos configurables (no atado a mes calendario)
3. Categorías jerárquicas (2 niveles)
4. Bot de Telegram: foto de factura → AI extrae datos → registra transacción (v2)

## Tech Stack

```
Backend:    Laravel 11 + PHP 8.3
Frontend:   Inertia.js + React 19 + TypeScript
UI:         shadcn/ui + Tailwind CSS
Database:   PostgreSQL 16
Queue:      Redis + Laravel Horizon
AI/OCR:     Gemini 2.5 Flash (v2)
Hosting:    Hetzner VPS + Ploi
Domain:     spendo.cl
```

## Additional Context

Para más detalle, revisar:
- `docs/DATABASE.md` - Schema completo y migraciones
- `docs/FEATURES.md` - Funcionalidades detalladas
- `docs/UI.md` - Flujos de interfaz

## Project Structure

```
├── app/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Account.php
│   │   ├── PaymentMethod.php
│   │   ├── Transaction.php
│   │   ├── Category.php
│   │   ├── Budget.php
│   │   ├── BudgetItem.php
│   │   ├── RecurringTransaction.php
│   │   └── Attachment.php
│   ├── Services/
│   │   ├── TransactionService.php
│   │   ├── BudgetService.php
│   │   └── BalanceService.php
│   ├── Actions/
│   │   ├── CreateTransferAction.php
│   │   ├── SettleCreditCardAction.php
│   │   └── GenerateRecurringTransactionsAction.php
│   └── Http/
│       ├── Controllers/
│       └── Requests/
├── resources/js/
│   ├── Pages/
│   │   ├── Dashboard.tsx
│   │   ├── Accounts/
│   │   ├── PaymentMethods/
│   │   ├── Transactions/
│   │   ├── Categories/
│   │   ├── Budgets/
│   │   └── Settings/
│   ├── Components/
│   │   ├── ui/ (shadcn)
│   │   └── app/
│   └── Layouts/
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
└── routes/
    └── web.php
```

## Development Commands

```bash
# Setup inicial
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Development
npm run dev
php artisan serve

# Queue (para jobs)
php artisan queue:work

# Testing
php artisan test
npm run test

# Code quality
./vendor/bin/phpstan analyse
npm run lint
```

## Environment Variables

```env
APP_NAME="Spendo"
APP_ENV=local
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=spendo
DB_USERNAME=postgres
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Future: Telegram + AI
TELEGRAM_BOT_TOKEN=
GEMINI_API_KEY=
```

## Coding Standards

### PHP/Laravel
- `declare(strict_types=1)` en todos los archivos
- Form Requests para validación
- API Resources para responses
- Actions para lógica de negocio compleja
- Services para lógica reutilizable
- Factories y Seeders obligatorios para cada modelo
- PHPStan level 8
- Pint para formatting

### TypeScript/React
- Strict mode habilitado
- Interfaces para todos los props
- Custom hooks para lógica reutilizable
- shadcn/ui vanilla (sin customización excesiva)
- Componentes funcionales con hooks

### Database
- Migrations con `down()` funcional
- Índices en foreign keys y campos de búsqueda frecuente
- Soft deletes en modelos principales
- UUIDs como primary key
- Montos en centavos (integer) para evitar floating point

### General
- Todo en UTC, convertir en frontend según timezone del usuario
- Una moneda por budget (sin conversiones)
- Tests desde el inicio

## MVP Scope

### v0.1 - Core
- [ ] Auth (email/password)
- [ ] CRUD Accounts (con balance inicial)
- [ ] CRUD Payment Methods (con config TDC)
- [ ] CRUD Categories (jerárquicas, 2 niveles)
- [ ] Transacciones: expense, income, transfer, settlement
- [ ] Dashboard: vista global + selector de budget

### v0.2 - Budgets
- [ ] CRUD Budgets con períodos configurables
- [ ] Budget items por categoría
- [ ] Reportes de presupuesto vs real
- [ ] Transacciones recurrentes

### v0.3 - Telegram Bot
- [ ] Bot básico: registro manual
- [ ] OCR de facturas con Gemini
- [ ] Categorización automática

### Future
- [ ] App móvil
- [ ] Split de gastos compartidos
- [ ] Metas de ahorro
- [ ] Multi-usuario (finanzas compartidas)
