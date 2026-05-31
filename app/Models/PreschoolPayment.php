<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PreschoolPayment
 *
 * Records a single payment transaction (or pending payment obligation) for a
 * preschool student. Payments may be created manually by admins or
 * auto-generated on enrollment (via PreschoolEnrollmentService). The
 * payment_status column tracks the lifecycle: pending → paid / cancelled.
 * Soft-deleted rows are preserved for financial audit trails.
 *
 * @property int              $id
 * @property int              $student_id
 * @property int|null         $class_id
 * @property int|null         $academic_year_id
 * @property int|null         $term_id
 * @property string|null      $payment_reference
 * @property string           $amount              Decimal stored as string by cast
 * @property string|null      $currency
 * @property string|null      $payment_method
 * @property string           $payment_status      pending|paid|cancelled
 * @property \Carbon\Carbon|null $paid_at
 * @property \Carbon\Carbon|null $due_date
 * @property string|null      $note
 * @property string|null      $description         Display-facing label for receipts/tables
 * @property string|null      $created_by          Admin user ID (varchar 16)
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class PreschoolPayment extends Model
{
    use SoftDeletes;

    /** @var list<string> Columns safe for mass-assignment */
    protected $fillable = [
        'student_id',
        'class_id',
        'academic_year_id',
        'term_id',
        'payment_reference',
        'amount',
        'currency',
        'payment_method',
        'payment_status',
        'paid_at',
        'due_date',
        'note',
        'description',
        'created_by',
    ];

    /**
     * @return array<string, string> Column cast definitions
     */
    protected function casts(): array
    {
        return [
            'amount'   => 'decimal:2',
            'paid_at'  => 'datetime',
            'due_date' => 'date',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * The student this payment is charged to.
     *
     * @return BelongsTo<PreschoolStudent, self>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    /**
     * The class the payment is associated with (optional).
     *
     * @return BelongsTo<PreschoolClass, self>
     */
    public function preschoolClass(): BelongsTo
    {
        return $this->belongsTo(PreschoolClass::class, 'class_id');
    }

    /**
     * The academic year this payment covers.
     *
     * @return BelongsTo<PreschoolAcademicYear, self>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicYear::class, 'academic_year_id');
    }

    /**
     * The term this payment covers.
     *
     * @return BelongsTo<PreschoolAcademicTerm, self>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicTerm::class, 'term_id');
    }

    /**
     * The admin who created this payment record.
     * FK is not enforced at the DB level because users.id is varchar(16),
     * not a bigint, and cannot use foreignId()->constrained().
     *
     * @return BelongsTo<User, self>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
