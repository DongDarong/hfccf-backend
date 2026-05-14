<?php

namespace App\Http\Requests\English;

use App\Models\EnglishTask;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnglishSubmissionRequest extends FormRequest
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

        $taskId = (int) ($this->input('task_id') ?: 0);

        if ($taskId <= 0) {
            return false;
        }

        return EnglishTask::query()
            ->whereKey($taskId)
            ->whereHas('class', function ($query) use ($user): void {
                $query->where('teacher_user_id', $user->id);
            })
            ->exists();
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', 'integer', 'exists:english_tasks,id'],
            'student_id' => ['required', 'integer', 'exists:english_students,id'],
            'submission_text' => ['nullable', 'string'],
            'submitted_at' => ['nullable', 'date'],
            'submission_status' => ['nullable', Rule::in(['pending', 'submitted', 'late', 'reviewed'])],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string'],
        ];
    }
}
