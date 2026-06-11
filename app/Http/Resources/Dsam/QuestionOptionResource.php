<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'question_id' => $this->question_id,
            'label'       => $this->label,
            'label_kh'    => $this->label_kh,
            'value'       => $this->value,
            'score_value' => $this->score_value,
            'order_index' => $this->order_index,
            'is_default'  => $this->is_default,
            'config'      => $this->config,
        ];
    }
}
