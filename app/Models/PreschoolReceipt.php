<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolReceipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'reissued_from_receipt_id',
        'receipt_number',
        'issued_at',
        'issued_by',
        'amount',
        'payment_method',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PreschoolPayment::class, 'payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PreschoolInvoice::class, 'invoice_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function reissuedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reissued_from_receipt_id');
    }
}
