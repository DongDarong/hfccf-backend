<?php

namespace App\Http\Requests\Preschool;

use App\Models\PreschoolSchoolCalendarEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreschoolSchoolCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:preschool_academic_years,id'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(PreschoolSchoolCalendarEvent::TYPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(PreschoolSchoolCalendarEvent::STATUSES)],
        ];
    }
}
