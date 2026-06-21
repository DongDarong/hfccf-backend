<?php

namespace App\Http\Requests\Preschool;

use App\Models\PreschoolSchoolCalendarEvent;
use Illuminate\Validation\Rule;

class UpdatePreschoolSchoolCalendarEventRequest extends StorePreschoolSchoolCalendarEventRequest
{
    public function rules(): array
    {
        return [
            'academic_year_id' => ['sometimes', 'required', 'integer', 'exists:preschool_academic_years,id'],
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'required', Rule::in(PreschoolSchoolCalendarEvent::TYPES)],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'nullable', Rule::in(PreschoolSchoolCalendarEvent::STATUSES)],
        ];
    }
}
