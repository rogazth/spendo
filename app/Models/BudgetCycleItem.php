<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetCycleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_cycle_id',
        'category_id',
        'category_name',
        'category_color',
        'category_emoji',
        'category_ids',
        'cap_amount',
    ];

    protected function casts(): array
    {
        return [
            'category_ids' => 'array',
        ];
    }

    /**
     * Get and set the cap (stored as cents in DB).
     */
    protected function capAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(BudgetCycle::class, 'budget_cycle_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
