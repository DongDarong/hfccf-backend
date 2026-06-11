<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'submission_id'   => $this->submission_id,
            'form_section_id' => $this->form_section_id,
            'raw_score'       => (float) $this->raw_score,
            'weighted_score'  => (float) $this->weighted_score,
            'max_score'       => (float) $this->max_score,
            'percentage'      => round((float) $this->percentage, 2),
            'section'         => $this->whenLoaded('section', fn () => [
                'id'             => $this->section->id,
                'title'          => $this->section->title,
                'title_kh'       => $this->section->title_kh,
                'scoring_weight' => (float) $this->section->scoring_weight,
            ]),
        ];
    }
}
