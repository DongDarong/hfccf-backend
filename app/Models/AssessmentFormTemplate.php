<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentFormTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'name_kh',
        'description',
        'description_kh',
        'category',
        'module',
        'status',
        'is_locked',
        'settings',
        'version_notes',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'duplicated_from_template_id',
        'duplicated_from_version',
        'restored_from_template_id',
        'restored_from_version',
        'created_by',
        'updated_by',
        'published_at',
        'published_by',
        'archived_at',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'settings' => 'array',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AssessmentFormVersion::class, 'template_id');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(AssessmentFormVersion::class, 'template_id')
            ->where('is_current', true)
            ->latestOfMany();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(AssessmentFormSection::class, 'template_id')->orderBy('sort_order');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class, 'template_id');
    }

    public function scoringRules(): HasMany
    {
        return $this->hasMany(AssessmentScoringRule::class, 'template_id');
    }

    public function riskLevels(): HasMany
    {
        return $this->hasMany(AssessmentRiskLevel::class, 'template_id')->orderBy('sort_order');
    }

    public function printTemplates(): HasMany
    {
        return $this->hasMany(AssessmentPrintTemplate::class, 'form_template_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssessmentSubmission::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
    }

    public function duplicatedFromTemplate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicated_from_template_id', 'id');
    }

    public function restoredFromTemplate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'restored_from_template_id', 'id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by', 'id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by', 'id');
    }

    public function getCurrentVersionAttribute(): ?int
    {
        $currentVersion = $this->relationLoaded('currentVersion')
            ? $this->getRelation('currentVersion')
            : $this->currentVersion()->first();

        if ($currentVersion) {
            return (int) $currentVersion->version_number;
        }

        return $this->versions()->max('version_number') ? (int) $this->versions()->max('version_number') : null;
    }
}
