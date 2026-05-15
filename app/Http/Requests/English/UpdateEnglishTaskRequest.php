<?php

namespace App\Http\Requests\English;

use App\Models\EnglishTask;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnglishTaskRequest extends FormRequest
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

        $taskId = (string) $this->route('id');
        if ($taskId === '') {
            return false;
        }

        $task = EnglishTask::query()->with('class')->find($taskId);

        return $task ? (string) $task->class?->teacher_user_id === (string) $user->id : false;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['sometimes', 'required', 'integer', 'exists:english_classes,id'],
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'task_status' => ['sometimes', 'nullable', Rule::in(['draft', 'assigned', 'submitted', 'reviewed', 'completed'])],
        ];
    }
}
