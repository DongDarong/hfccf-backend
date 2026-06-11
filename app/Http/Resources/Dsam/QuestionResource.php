<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'uuid'                => $this->uuid,
            'form_section_id'     => $this->form_section_id,
            'question_type_id'    => $this->question_type_id,
            'parent_question_id'  => $this->parent_question_id,
            'trigger_option_id'   => $this->trigger_option_id,
            'label'               => $this->label,
            'label_kh'            => $this->label_kh,
            'placeholder'         => $this->placeholder,
            'placeholder_kh'      => $this->placeholder_kh,
            'help_text'           => $this->help_text,
            'help_text_kh'        => $this->help_text_kh,
            'order_index'         => $this->order_index,
            'is_required'         => $this->is_required,
            'is_scored'           => $this->is_scored,
            'is_conditional'      => $this->isConditional(),
            'max_score'           => $this->max_score,
            'validation_rules'    => $this->validation_rules,
            'config'              => $this->config,
            'scoring_config'      => $this->scoring_config,
            'question_type'       => new QuestionTypeResource($this->whenLoaded('questionType')),
            'options'             => QuestionOptionResource::collection($this->whenLoaded('options')),
            'conditional_children'=> QuestionResource::collection($this->whenLoaded('conditionalChildren')),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
