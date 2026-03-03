# Delete Entities via MCP

Add delete tools to the MCP server for Accounts, Instruments, and Transactions.

## Cascade Behavior (by design)

| Tool | Effect |
|------|--------|
| `DeleteAccountTool` | Deletes the account **and all its transactions** |
| `DeleteInstrumentTool` | Deletes the instrument **and all transactions where it was the primary instrument** |
| `DeleteTransactionTool` | Deletes only that transaction; if it's a transfer, the linked leg is also deleted |

---

## Changes Required

### 1. Migration — change FK to `cascadeOnDelete`

Modify `2026_02_01_000005_create_transactions_table.php`:

```php
// account_id: nullOnDelete → cascadeOnDelete
$table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();

// instrument_id: nullOnDelete → cascadeOnDelete
$table->foreign('instrument_id')->references('id')->on('instruments')->cascadeOnDelete();

// from_instrument_id stays nullOnDelete — settlement source deletion should NOT wipe the transaction
```

---

### 2. New MCP Tools

#### `DeleteAccountTool`

- Input: `account_id` (required)
- Finds account owned by user → hard deletes it (DB cascade removes transactions)
- Response includes account name and count of deleted transactions (query before delete)
- Warning message in description: deleting an account removes all its transactions permanently

#### `DeleteInstrumentTool`

- Input: `instrument_id` (required)
- Finds instrument owned by user → hard deletes it (DB cascade removes transactions)
- Response includes instrument name and count of deleted transactions
- Warning message in description: deleting an instrument removes all transactions where it was used

#### `DeleteTransactionTool`

- Input: `transaction_id` (required)
- Finds transaction owned by user → deletes it
- If it's a `transfer_out` or `transfer_in`, also deletes the linked leg
- Response confirms what was deleted (and whether a linked transfer leg was also removed)

---

### 3. Register in `SpendoServer`

Add all three tools to the `$tools` array under a `// Delete tools` section.

---

## Files to Create/Modify

| File | Change |
|------|--------|
| `database/migrations/2026_02_01_000005_create_transactions_table.php` | `account_id` + `instrument_id` → `cascadeOnDelete` |
| `app/Mcp/Tools/DeleteAccountTool.php` | New tool |
| `app/Mcp/Tools/DeleteInstrumentTool.php` | New tool |
| `app/Mcp/Tools/DeleteTransactionTool.php` | New tool |
| `app/Mcp/Servers/SpendoServer.php` | Register the 3 new tools |

## Tests to Add

- `DeleteAccountTool`: unauthorized → error, owned → deletes account + transactions
- `DeleteInstrumentTool`: unauthorized → error, owned → deletes instrument + transactions
- `DeleteTransactionTool`: deletes single transaction; transfer → also deletes linked leg
