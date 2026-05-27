<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentPrintTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'form_template_id',
        'name',
        'name_kh',
        'format',
        'page_size',
        'orientation',
        'margin_top',
        'margin_right',
        'margin_bottom',
        'margin_left',
        'font_family',
        'font_size',
        'header_html',
        'footer_html',
        'watermark_text',
        'show_logo',
        'logo_path',
        'show_qr_code',
        'show_watermark',
        'blocks',
        'styles',
        'is_default',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'show_logo'      => 'boolean',
            'show_qr_code'   => 'boolean',
            'show_watermark' => 'boolean',
            'is_default'     => 'boolean',
            'blocks'         => 'array',
            'margin_top'     => 'integer',
            'margin_right'   => 'integer',
            'margin_bottom'  => 'integer',
            'margin_left'    => 'integer',
        ];
    }

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormTemplate::class, 'form_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
