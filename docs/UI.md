# UI Patterns & Components

## Icon Conventions
- Always use Icon suffix: `PlusIcon`, `PencilIcon`, `Trash2Icon`
- NO margins on icons inside Button or DropdownMenuItem (components handle gap)

## CRUD Pattern (Dialog-based)
All index pages use dialogs instead of separate create/edit pages:
```tsx
// State
const [formDialogOpen, setFormDialogOpen] = useState(false);
const [editingItem, setEditingItem] = useState<Item | undefined>();
const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
const [deletingItem, setDeletingItem] = useState<Item | null>(null);

// Clear deleting state only on dialog close (prevents undefined flash)
const handleDeleteDialogOpenChange = (open: boolean) => {
    setDeleteDialogOpen(open);
    if (!open) setDeletingItem(null);
};
```

## ConfirmDialog
- `description` accepts ReactNode for semibold item names
- Use `variant="destructive"` for delete confirmations
- Dropdown delete items: NO destructive color class
```tsx
<ConfirmDialog
    description={<>¿Eliminar <span className="font-semibold">{item.name}</span>?</>}
    variant="destructive"
/>
```

## Empty States
Use `ui/empty.tsx` components:
```tsx
<Empty>
    <EmptyHeader>
        <EmptyMedia variant="icon"><ReceiptIcon /></EmptyMedia>
        <EmptyTitle>No hay datos</EmptyTitle>
        <EmptyDescription>Descripción...</EmptyDescription>
    </EmptyHeader>
    <EmptyContent>
        <Button>Acción</Button>
    </EmptyContent>
</Empty>
```

## DataTable Sorting
Show sort direction in column headers:
```tsx
const sorted = column.getIsSorted();
// sorted === 'asc' → ArrowUpIcon
// sorted === 'desc' → ArrowDownIcon
// sorted === false → ArrowUpDownIcon (opacity-50)
```

## Form Components
- **Type toggles**: Use `Tabs` not `Select`
- **Dates**: Use `Popover` + `Calendar` (datepicker pattern)
- **Long text**: Use `Textarea` for description/notes
- **Conditional fields**: Hide irrelevant fields (e.g., payment method for income)

## Datepicker Pattern
```tsx
<Popover>
    <PopoverTrigger asChild>
        <Button variant="outline">
            <CalendarIcon className="h-4 w-4" />
            {format(date, 'PPP', { locale: es })}
        </Button>
    </PopoverTrigger>
    <PopoverContent className="w-auto p-0" align="start">
        <Calendar mode="single" selected={date} onSelect={setDate} />
    </PopoverContent>
</Popover>
```

## Backend Data for Forms
Return raw arrays (not ResourceCollection) for form dropdowns:
```php
'categories' => $categories->map(fn($c) => [
    'id' => $c->id, 'name' => $c->name, ...
])->toArray(),
```

## Conditional Validation
Use Laravel's built-in `required_if`:
```php
'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id', 'required_if:type,expense'],
```

## TypeScript Form Types
Use explicit generic types instead of `as Type` casting:
```tsx
// ✓ Correct: explicit generic
const { data, setData } = useForm<{
    name: string;
    type: AccountType;
}>({ name: '', type: 'checking' });

// ✗ Wrong: as casting
const { data, setData } = useForm({
    type: 'checking' as AccountType,
});
```

## Type Constants Pattern
Define constants with `as const` and derive types (see `types/models.ts`):
```tsx
export const ACCOUNT_TYPES = [
    { id: 'checking', label: 'Cuenta Corriente' },
    { id: 'savings', label: 'Cuenta de Ahorro' },
] as const;
export type AccountType = typeof ACCOUNT_TYPES[number]['id'];

// Usage in forms
{ACCOUNT_TYPES.map((type) => (
    <SelectItem key={type.id} value={type.id}>{type.label}</SelectItem>
))}
```
