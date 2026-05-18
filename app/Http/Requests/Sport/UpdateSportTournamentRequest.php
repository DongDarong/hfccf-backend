<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSportTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user && in_array($user->role_code, ['superadmin', 'adminsport'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tournament_code' => $this->input('tournament_code', $this->input('tournamentCode')),
            'tournament_type' => $this->input('tournament_type', $this->input('tournamentType', $this->input('sport_type'))),
            'slug' => $this->input('slug', $this->input('code')),
            'visibility' => $this->input('visibility'),
            'registration_open_at' => $this->input('registration_open_at', $this->input('registrationOpenAt')),
            'registration_close_at' => $this->input('registration_close_at', $this->input('registrationCloseAt')),
            'starts_at' => $this->input('starts_at', $this->input('startsAt')),
            'ends_at' => $this->input('ends_at', $this->input('endsAt')),
            'logo_path' => $this->input('logo_path', $this->input('logoPath')),
            'banner_path' => $this->input('banner_path', $this->input('bannerPath')),
            'location' => $this->input('location'),
            'organizer' => $this->input('organizer'),
            'rules' => $this->input('rules'),
            'settings' => $this->input('settings'),
        ]);
    }

    public function rules(): array
    {
        return [
            'tournament_code' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:sport_tournaments,tournament_code,'.$this->route('id').',id'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:64', 'unique:sport_tournaments,slug,'.$this->route('id').',id'],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'season' => ['sometimes', 'nullable', 'string', 'max:64'],
            'tournament_type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'visibility' => ['sometimes', 'nullable', 'in:private,public,unlisted'],
            'status' => ['sometimes', 'required', 'in:draft,registration_open,registration_closed,group_draw_completed,fixtures_generated,active,knockout_stage,completed,archived'],
            'registration_open_at' => ['sometimes', 'nullable', 'date'],
            'registration_close_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:registration_open_at'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'description' => ['sometimes', 'nullable', 'string'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banner_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:191'],
            'organizer' => ['sometimes', 'nullable', 'string', 'max:191'],
            'rules' => ['sometimes', 'nullable', 'array'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
