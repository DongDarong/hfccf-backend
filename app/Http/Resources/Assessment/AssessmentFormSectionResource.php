<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentFormSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'form_template_id' => $this->form_template_id,
            'title'            => $this->title,
            'description'      => $this->description,
            'order'            => $this->order,
            'parent_id'        => $this->parent_id,
        ];
    }
}
