<?php

namespace App\Http\Resources\Preschool;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreschoolProgressSummaryResource extends JsonResource
{
    /**
     * Keep the response shape explicit so the frontend can render summary cards
     * and trend rows without guessing at nested report structures.
     */
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this['summary'] ?? [],
            'categories' => $this['categories'] ?? [],
            'recentAssessments' => $this['recentAssessments'] ?? [],
        ];
    }
}
