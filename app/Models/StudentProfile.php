<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    public $timestamps = false;

    // updated_at only — no created_at on this table
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'student_id',
        // Father
        'father_name', 'father_dob', 'father_occupation', 'father_income', 'father_phone', 'father_status',
        // Mother
        'mother_name', 'mother_dob', 'mother_occupation', 'mother_income', 'mother_phone', 'mother_status',
        // Guardian
        'guardian_name', 'guardian_relation', 'guardian_phone',
        // Household
        'num_siblings', 'birth_order', 'household_size', 'monthly_income', 'income_sources',
        // Housing
        'housing_type', 'has_electricity', 'has_clean_water', 'has_toilet',
        // Education
        'distance_to_school', 'transport_mode',
        // Health
        'health_status', 'disabilities', 'has_health_insurance', 'vaccination_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'father_dob'          => 'date',
            'mother_dob'          => 'date',
            'father_income'       => 'decimal:2',
            'mother_income'       => 'decimal:2',
            'monthly_income'      => 'decimal:2',
            'income_sources'      => 'array',
            'disabilities'        => 'array',
            'has_electricity'     => 'boolean',
            'has_clean_water'     => 'boolean',
            'has_toilet'          => 'boolean',
            'has_health_insurance' => 'boolean',
            'distance_to_school'  => 'decimal:2',
            'num_siblings'        => 'integer',
            'birth_order'         => 'integer',
            'household_size'      => 'integer',
            'updated_at'          => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }
}
