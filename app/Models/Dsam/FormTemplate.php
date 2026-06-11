<?php

namespace App\Models\Dsam;

use App\Models\AcademicYear;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FormTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'dsam_form_templates';

    protected $fillable = [
        'uuid',
        'organization_id',
        'academic_year_id',
        'parent_template_id',
        'name',
        'name_kh',
        'description',
        'description_kh',
        'category',
        'status',
        'version_number',
        'version_notes',
        'scoring_config',
        'risk_config',
        'settings',
        'published_by',
        'created_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'scoring_config' => 'array',
            'risk_config'    => 'array',
            'settings'       => 'array',
            'published_at'   => 'datetime',
            'version_number' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            if (blank($template->uuid)) {
                $template->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** The template this version was copied from */
    public function parentTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'parent_template_id');
    }

    /** All newer versions derived from this template */
    public function childVersions(): HasMany
    {
        return $this->hasMany(FormTemplate::class, 'parent_template_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(FormSection::class, 'form_template_id')->orderBy('order_index');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'form_template_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by', 'id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Resolved risk thresholds, with sensible defaults if not configured */
    public function resolvedRiskConfig(): array
    {
        return $this->risk_config ?? [
            'invert_score' => true,
            'thresholds'   => [
                ['level' => 'low',      'min' => 76, 'max' => 100, 'color' => '#16a34a'],
                ['level' => 'medium',   'min' => 51, 'max' => 75,  'color' => '#d97706'],
                ['level' => 'high',     'min' => 26, 'max' => 50,  'color' => '#ea580c'],
                ['level' => 'critical', 'min' => 0,  'max' => 25,  'color' => '#dc2626'],
            ],
        ];
    }
}
