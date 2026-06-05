<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'uuid'               => $this->uuid,
            'organization_id'    => $this->organization_id,
            'academic_year_id'   => $this->academic_year_id,
            'parent_template_id' => $this->parent_template_id,
            'name'               => $this->name,
            'name_kh'            => $this->name_kh,
            'description'        => $this->description,
            'description_kh'     => $this->description_kh,
            'category'           => $this->category,
            'status'             => $this->status,
            'version_number'     => $this->version_number,
            'version_notes'      => $this->version_notes,
            'scoring_config'     => $this->scoring_config,
            'risk_config'        => $this->resolvedRiskConfig(),
            'settings'           => $this->settings,
            'is_published'       => $this->isPublished(),
            'is_draft'           => $this->isDraft(),
            'section_count'      => $this->when(
                $this->relationLoaded('sections'),
                fn () => $this->sections->count(),
            ),
            'submissions_count'  => $this->when(
                isset($this->submissions_count),
                $this->submissions_count,
            ),
            'academic_year'      => new AcademicYearResource($this->whenLoaded('academicYear')),
            'sections'           => FormSectionResource::collection($this->whenLoaded('sections')),
            'created_by'         => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy->id,
                'name' => trim($this->createdBy->first_name.' '.$this->createdBy->last_name),
            ]),
            'published_by'       => $this->whenLoaded('publishedBy', fn () => [
                'id'   => $this->publishedBy->id,
                'name' => trim($this->publishedBy->first_name.' '.$this->publishedBy->last_name),
            ]),
            'published_at'       => $this->published_at?->toIso8601String(),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
