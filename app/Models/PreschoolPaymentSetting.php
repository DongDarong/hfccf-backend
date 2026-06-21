<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolPaymentSetting extends Model
{
    protected $table = 'preschool_payment_settings';

    public const LATE_FEE_FIXED = 'fixed';
    public const LATE_FEE_PERCENTAGE = 'percentage';

    protected $fillable = [
        'invoice_prefix',
        'receipt_prefix',
        'next_invoice_number',
        'next_receipt_number',
        'late_fee_enabled',
        'late_fee_type',
        'late_fee_amount',
        'grace_period_days',
        'proration_enabled',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'next_invoice_number' => 'integer',
            'next_receipt_number' => 'integer',
            'late_fee_enabled' => 'boolean',
            'late_fee_amount' => 'decimal:2',
            'grace_period_days' => 'integer',
            'proration_enabled' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
