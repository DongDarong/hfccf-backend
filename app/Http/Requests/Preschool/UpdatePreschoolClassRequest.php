<?php

namespace App\Http\Requests\Preschool;

use App\Models\PreschoolClassLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreschoolClassRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $classLevel = $this->resolveClassLevel();
        $payload = [
            'teacher_display_name' => $this->input('teacher_display_name', $this->input('teacher')),
            'students_count' => $this->input('students_count', $this->input('students')),
        ];

        $classLevelId = trim((string) $this->input('class_level_id', $this->input('classLevelId', '')));
        if ($classLevelId !== '') {
            $payload['class_level_id'] = $classLevelId;
        } elseif ($classLevel?->id) {
            $payload['class_level_id'] = $classLevel->id;
        }

        $legacyLevel = trim((string) $this->input('level', ''));
        if ($legacyLevel !== '') {
            $payload['level'] = $legacyLevel;
        } elseif ($classLevel?->name_en) {
            $payload['level'] = $classLevel->name_en;
        }

        $this->merge($payload);
    }

    public function authorize(): bool
    {
        return $this->hasPreschoolAdminAccess();
    }

    private function hasPreschoolAdminAccess(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    private function resolveClassLevel(): ?PreschoolClassLevel
    {
        $classLevelId = trim((string) $this->input('class_level_id', $this->input('classLevelId', '')));
        if ($classLevelId !== '') {
            return PreschoolClassLevel::query()->find($classLevelId);
        }

        $legacyLevel = strtolower(trim((string) $this->input('level', '')));
        if ($legacyLevel === '') {
            return null;
        }

        $legacyMap = [
            'nursery' => 'NUR',
            'kindergarten a' => 'KGA',
            'kindergarten 1' => 'KGA',
            'kindergarten b' => 'KGB',
            'kindergarten 2' => 'KGB',
            'prep' => 'PRE',
        ];

        $code = $legacyMap[$legacyLevel] ?? null;
        if ($code !== null) {
            $classLevel = PreschoolClassLevel::query()->where('code', $code)->first();
            if ($classLevel) {
                return $classLevel;
            }
        }

        return PreschoolClassLevel::query()
            ->whereRaw('LOWER(name_en) = ?', [$legacyLevel])
            ->orWhereRaw('LOWER(code) = ?', [str_replace(' ', '', $legacyLevel)])
            ->first();
    }

    public function rules(): array
    {
        $classId = (string) $this->route('id');

        return [
            'code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('preschool_classes', 'code')->ignore($classId)],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'teacher_user_id' => ['sometimes', 'nullable', 'string', 'exists:users,id'],
            'teacher_display_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'class_level_id' => [
                'sometimes',
                'required',
                'exists:preschool_class_levels,id',
                Rule::exists('preschool_class_levels', 'id')->where(static function ($query) {
                    $query->where('is_active', true);
                }),
            ],
            'level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'schedule' => ['sometimes', 'nullable', 'string', 'max:191'],
            'students_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'pending', 'closed', 'archived'])],
            'room' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'student_ids' => ['sometimes', 'array'],
            'student_ids.*' => ['integer', 'exists:preschool_students,id'],
            'override_locked_context' => ['sometimes', 'boolean'],
            'override_reason' => ['required_if:override_locked_context,1', 'nullable', 'string', 'max:500'],
        ];
    }
}
