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
        'payment_method_id',
        'category_id',
        'linked_transaction_id',
        'amount',
        'currency',
        'description',
        'notes',
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'integer',
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

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
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
