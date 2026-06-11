<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionApprovalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'submission_id' => $this->submission_id,
            'action'        => $this->action,
            'action_label'  => $this->actionLabel(),
            'notes'         => $this->notes,
            'actor'         => $this->whenLoaded('actor', fn () => [
                'id'   => $this->actor->id,
                'name' => trim($this->actor->first_name.' '.$this->actor->last_name),
            ]),
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
