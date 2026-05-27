<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreSportPlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user && in_array($user->role_code, ['superadmin', 'adminsport'], true);
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name', ''));
        [$firstName, $lastName] = $this->splitName($name);

        $this->merge([
            'first_name' => $this->input('first_name', $firstName),
            'last_name' => $this->input('last_name', $lastName),
            'jersey_number' => $this->input('jersey_number', $this->input('jerseyNumber')),
            'position' => $this->input('position', $this->input('primaryPosition')),
            'team' => $this->input('team', $this->input('team_name')),
            'date_of_birth' => $this->input('date_of_birth', $this->input('dateOfBirth')),
            'height_cm' => $this->input('height_cm', $this->input('heightCm')),
            'weight_kg' => $this->input('weight_kg', $this->input('weightKg')),
            'preferred_foot' => $this->input('preferred_foot', $this->input('preferredFoot')),
            'blood_type' => $this->input('blood_type', $this->input('bloodType')),
            'current_school' => $this->input('current_school', $this->input('currentSchool')),
            'grade_year' => $this->input('grade_year', $this->input('gradeYear')),
            'primary_position' => $this->input('primary_position', $this->input('primaryPosition')),
            'registration_status' => $this->input('registration_status', $this->input('registrationStatus')),
            'matches_played' => $this->input('matches_played', $this->input('matchesPlayed')),
            'goals_scored' => $this->input('goals_scored', $this->input('goalsScored')),
            'remove_photo' => $this->boolean('remove_photo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'player_code' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:sport_players,player_code'],
            'jersey_number' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'position' => ['sometimes', 'nullable', 'string', 'max:100'],
            'team' => ['required', 'string', 'max:191'],
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
            'status' => ['required', 'in:active,pending,inactive,suspended'],
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
