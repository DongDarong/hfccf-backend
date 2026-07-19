<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportEquipmentItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'equipment_code',
        'name',
        'category',
        'description',
        'unit',
        'total_quantity',
        'available_quantity',
        'minimum_stock_level',
        'storage_location',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'total_quantity' => 'integer',
            'available_quantity' => 'integer',
            'minimum_stock_level' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id', 'id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(SportEquipmentRequest::class, 'equipment_item_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SportEquipmentAssignment::class, 'equipment_item_id');
    }
}
