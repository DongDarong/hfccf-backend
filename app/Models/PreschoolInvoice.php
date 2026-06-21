<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'class_id',
        'academic_year_id',
        'term_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'subtotal',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    public function preschoolClass(): BelongsTo
    {
        return $this->belongsTo(PreschoolClass::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicYear::class, 'academic_year_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicTerm::class, 'term_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PreschoolInvoiceItem::class, 'invoice_id')->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PreschoolPayment::class, 'invoice_id')->orderByDesc('created_at');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PreschoolReceipt::class, 'invoice_id')->orderByDesc('issued_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
