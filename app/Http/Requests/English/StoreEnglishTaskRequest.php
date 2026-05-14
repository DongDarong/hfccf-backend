<?php

namespace App\Http\Requests\English;

use App\Models\EnglishClass;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnglishTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        if (in_array($user->role_code, ['superadmin', 'adminenglish'], true)) {
            return true;
        }

        if ($user->role_code !== 'teacher-english') {
            return false;
        }

        $classId = (int) ($this->input('class_id') ?: 0);
        if ($classId <= 0) {
            return false;
        }

        return EnglishClass::query()
            ->whereKey($classId)
            ->where('teacher_user_id', $user->id)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:english_classes,id'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'task_status' => ['nullable', Rule::in(['draft', 'assigned', 'submitted', 'reviewed', 'completed'])],
        ];
    }
}
