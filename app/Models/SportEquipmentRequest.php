<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportEquipmentRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'request_code',
        'equipment_item_id',
        'coach_user_id',
        'team_id',
        'requested_quantity',
        'approved_quantity',
        'issued_quantity',
        'returned_quantity',
        'damaged_quantity',
        'missing_quantity',
        'purpose',
        'required_date',
        'expected_return_date',
        'status',
        'admin_note',
        'rejected_reason',
        'reviewed_by_user_id',
        'reviewed_at',
        'issued_by_user_id',
        'issued_at',
        'returned_by_user_id',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'integer',
            'approved_quantity' => 'integer',
            'issued_quantity' => 'integer',
            'returned_quantity' => 'integer',
            'damaged_quantity' => 'integer',
            'missing_quantity' => 'integer',
            'required_date' => 'date',
            'expected_return_date' => 'date',
            'reviewed_at' => 'datetime',
            'issued_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SportEquipmentItem::class, 'equipment_item_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id', 'id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SportTeam::class, 'team_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id', 'id');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_user_id', 'id');
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(SportEquipmentAssignment::class, 'equipment_request_id');
    }
}
