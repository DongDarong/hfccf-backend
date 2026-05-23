<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'section_id'           => $this->section_id,
            'question_type_id'     => $this->question_type_id,
            'question_type_key'    => $this->questionType?->key,
            'question_text'        => $this->question_text,
            'help_text'            => $this->help_text,
            'placeholder'          => $this->placeholder,
            'is_required'          => $this->is_required,
            'order'                => $this->order,
            'config'               => $this->config,
            'conditional_logic'    => $this->conditional_logic,
            'options'              => $this->whenLoaded('options', fn () =>
                $this->options->map(fn ($o) => [
                    'id'          => $o->id,
                    'option_text' => $o->option_text,
                    'order'       => $o->order,
                    'score_value' => $o->score_value,
                ])
            ),
        ];
    }
}
