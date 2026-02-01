<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettings extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'user_id',
        'default_currency',
        'budget_cycle_start_day',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'budget_cycle_start_day' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
