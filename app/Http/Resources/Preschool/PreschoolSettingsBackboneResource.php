<?php

namespace App\Http\Resources\Preschool;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreschoolSettingsBackboneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = is_array($this->resource) ? $this->resource : [];

        return [
            'academicYear' => $payload['academicYear'] ?? [],
            'terms' => $payload['terms'] ?? [],
            'classConfigurations' => $payload['classConfigurations'] ?? [],
            'attendance' => $payload['attendance'] ?? [],
            'assessment' => $payload['assessment'] ?? [],
            'schedule' => $payload['schedule'] ?? [],
            'enrollment' => $payload['enrollment'] ?? [],
            'payment' => $payload['payment'] ?? [],
            'health' => $payload['health'] ?? [],
            'preferences' => $payload['preferences'] ?? [],
            'groups' => $payload['groups'] ?? [],
            'metadata' => $payload['metadata'] ?? [],
        ];
    }
}
