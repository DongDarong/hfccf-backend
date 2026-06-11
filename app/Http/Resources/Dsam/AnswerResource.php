<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'submission_id' => $this->submission_id,
            'question_id'   => $this->question_id,
            // Only the populated typed column is included — others omitted when null
            'text_value'    => $this->text_value,
            'number_value'  => $this->number_value,
            'date_value'    => $this->date_value?->toDateString(),
            'json_value'    => $this->json_value,
            'file_path'     => $this->file_path,
            'score_value'   => $this->score_value,
            // Convenience: whatever value is set, resolved to a single field
            'display_value' => $this->displayValue(),
            'question'      => new QuestionResource($this->whenLoaded('question')),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
