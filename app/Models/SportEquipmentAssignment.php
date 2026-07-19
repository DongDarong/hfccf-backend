<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportEquipmentAssignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'assignment_code', 'equipment_request_id', 'equipment_item_id', 'team_id', 'coach_user_id',
        'assigned_quantity', 'returned_quantity', 'damaged_quantity', 'missing_quantity', 'status',
        'assigned_at', 'expected_return_at', 'returned_at', 'assigned_by_user_id', 'returned_by_user_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_quantity' => 'integer',
            'returned_quantity' => 'integer',
            'damaged_quantity' => 'integer',
            'missing_quantity' => 'integer',
            'assigned_at' => 'datetime',
            'expected_return_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SportEquipmentRequest::class, 'equipment_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SportEquipmentItem::class, 'equipment_item_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SportTeam::class, 'team_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id', 'id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id', 'id');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_user_id', 'id');
    }
}
