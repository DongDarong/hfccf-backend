<?php

namespace App\Http\Requests\Preschool;

use App\Support\PreschoolScheduleDay;
use App\Support\PreschoolScheduleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreschoolScheduleRequest extends FormRequest
{
    /**
     * Preschool schedules are admin-managed because the overlap rules affect
     * every timetable view, while teachers only receive read access.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && in_array($this->user()->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:preschool_classes,id'],
            'teacher_user_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:16',
                Rule::exists('users', 'id')->where(static function ($query) {
                    $query->where('role_code', 'teacher-preschool')->whereNull('deleted_at');
                }),
            ],
            'day_of_week' => ['required', 'integer', Rule::in(PreschoolScheduleDay::values())],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'room' => ['sometimes', 'nullable', 'string', 'max:100'],
            'activity_label' => ['required', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(PreschoolScheduleStatus::values())],
            'effective_from' => ['sometimes', 'nullable', 'date'],
            'effective_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:effective_from'],
            'override_locked_context' => ['sometimes', 'boolean'],
            'override_reason' => ['required_if:override_locked_context,1', 'nullable', 'string', 'max:500'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $startTime = (string) $this->input('start_time', '');
            $endTime = (string) $this->input('end_time', '');

            if ($startTime !== '' && $endTime !== '' && strcmp($startTime, $endTime) >= 0) {
                $validator->errors()->add('end_time', 'The end time must be after the start time.');
            }
        });
    }
}
