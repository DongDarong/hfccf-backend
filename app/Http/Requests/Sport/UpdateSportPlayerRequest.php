<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSportPlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user && in_array($user->role_code, ['superadmin', 'adminsport'], true);
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->exists('name') || $this->exists('first_name') || $this->exists('last_name')) {
            $name = trim((string) $this->input('name', ''));
            [$firstName, $lastName] = $this->splitName($name);

            if ($this->exists('first_name') || $this->exists('name')) {
                $payload['first_name'] = $this->input('first_name', $firstName);
            }

            if ($this->exists('last_name') || $this->exists('name')) {
                $payload['last_name'] = $this->input('last_name', $lastName);
            }
        }

        $mappableFields = [
            'jersey_number' => 'jerseyNumber',
            'position' => 'primaryPosition',
            'date_of_birth' => 'dateOfBirth',
            'height_cm' => 'heightCm',
            'weight_kg' => 'weightKg',
            'preferred_foot' => 'preferredFoot',
            'blood_type' => 'bloodType',
            'current_school' => 'currentSchool',
            'grade_year' => 'gradeYear',
            'primary_position' => 'primaryPosition',
            'registration_status' => 'registrationStatus',
            'matches_played' => 'matchesPlayed',
            'goals_scored' => 'goalsScored',
        ];

        foreach ($mappableFields as $field => $camelField) {
            if ($this->exists($field) || $this->exists($camelField)) {
                $payload[$field] = $this->input($field, $this->input($camelField));
            }
        }

        if ($this->exists('team') || $this->exists('team_name')) {
            $payload['team'] = $this->input('team', $this->input('team_name'));
        }

        if ($this->exists('remove_photo')) {
            $payload['remove_photo'] = $this->boolean('remove_photo');
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        $playerId = (string) $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'player_code' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:sport_players,player_code,'.$playerId.',id'],
            'jersey_number' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'position' => ['sometimes', 'nullable', 'string', 'max:100'],
            'team' => ['sometimes', 'required', 'string', 'max:191'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:32'],
            'age' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
            'height_cm' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'preferred_foot' => ['sometimes', 'nullable', 'string', 'max:32'],
            'blood_type' => ['sometimes', 'nullable', 'string', 'max:8'],
            'village' => ['sometimes', 'nullable', 'string', 'max:100'],
            'commune' => ['sometimes', 'nullable', 'string', 'max:100'],
            'district' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'current_school' => ['sometimes', 'nullable', 'string', 'max:191'],
            'grade_year' => ['sometimes', 'nullable', 'string', 'max:32'],
            'primary_position' => ['sometimes', 'nullable', 'string', 'max:100'],
            'registration_status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'matches_played' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'goals_scored' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'required', 'in:active,pending,inactive,suspended'],
            'approval_status' => ['sometimes', 'nullable', 'in:pending,approved,rejected'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'division' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [$name, ''];

        return [
            trim((string) ($parts[0] ?? '')),
            trim((string) ($parts[1] ?? '')),
        ];
    }
}
