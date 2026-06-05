<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'form_template_id' => $this->form_template_id,
            'title'            => $this->title,
            'title_kh'         => $this->title_kh,
            'description'      => $this->description,
            'description_kh'   => $this->description_kh,
            'order_index'      => $this->order_index,
            'scoring_weight'   => (float) $this->scoring_weight,
            'is_required'      => $this->is_required,
            'settings'         => $this->settings,
            'question_count'   => $this->when(
                $this->relationLoaded('allQuestions'),
                fn () => $this->allQuestions->count(),
            ),
            'questions'        => QuestionResource::collection($this->whenLoaded('allQuestions')),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
