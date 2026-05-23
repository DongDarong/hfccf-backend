<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'template_id'        => $this->template_id,
            'section_id'         => $this->section_id,
            'question_type_id'   => $this->question_type_id,
            'question_type_key'  => $this->questionType?->key,
            'label'              => $this->label,
            'question_text'      => $this->question_text,
            'help_text'          => $this->help_text,
            'placeholder'        => $this->placeholder,
            'is_required'        => $this->is_required,
            'sort_order'         => $this->sort_order,
            'order'              => $this->order,
            'settings'           => $this->settings,
            'config'             => $this->config,
            'conditional_logic'  => $this->conditional_logic,
            'max_score'          => $this->max_score,
            'scoring_weight'     => $this->scoring_weight,
            'print_visible'      => $this->print_visible,
            'options'            => $this->whenLoaded('options', fn () =>
                $this->options->map(fn ($o) => [
                    'id'           => $o->id,
                    'label'        => $o->label,
                    'option_text'  => $o->option_text,
                    'value'        => $o->value,
                    'sort_order'   => $o->sort_order,
                    'order'        => $o->order,
                    'score_value'  => $o->score_value,
                    'color_code'   => $o->color_code,
                    'is_other'     => $o->is_other,
                ])
            ),
            'matrix_rows'        => $this->whenLoaded('matrixRows', fn () =>
                $this->matrixRows->map(fn ($row) => [
                    'id'         => $row->id,
                    'label'      => $row->label,
                    'label_kh'   => $row->label_kh,
                    'sort_order' => $row->sort_order,
                ])
            ),
        ];
    }
}
