<?php

namespace App\Http\Resources\Sport;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SportTournamentKnockoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
