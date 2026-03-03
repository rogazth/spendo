<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'account_id',
        'instrument_id',
        'from_instrument_id',
        'category_id',
        'linked_transaction_id',
        'amount',
        'instrument_amount',
        'exchange_rate',
        'currency',
        'description',
        'notes',
        'exclude_from_budget',
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'integer',
            'instrument_amount' => 'integer',
            'exchange_rate' => 'decimal:6',
            'exclude_from_budget' => 'boolean',
            'transaction_date' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function fromInstrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class, 'from_instrument_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function linkedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'linked_transaction_id');
    }

    public function counterpartTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'linked_transaction_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Get and set the amount (stored as cents in DB).
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    /**
     * Get and set the instrument_amount (stored as cents in DB).
     */
    protected function instrumentAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? $value / 100 : null,
            set: fn ($value) => $value !== null ? (int) round($value * 100) : null,
        );
    }

    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->type->isDebit() ? '-' : '+';

        return $prefix.'$'.number_format($this->amount, 0, ',', '.');
    }

    public function isTransfer(): bool
    {
        return in_array($this->type, [TransactionType::TransferOut, TransactionType::TransferIn]);
    }
}
