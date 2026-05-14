<?php

namespace App\Http\Requests\English;

use App\Models\EnglishTaskSubmission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnglishSubmissionRequest extends FormRequest
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

        $submissionId = (string) $this->route('id');
        if ($submissionId === '') {
            return false;
        }

        $submission = EnglishTaskSubmission::query()->with('task.class')->find($submissionId);

        return $submission ? (string) $submission->task?->class?->teacher_user_id === (string) $user->id : false;
    }

    public function rules(): array
    {
        return [
            'task_id' => ['sometimes', 'required', 'integer', 'exists:english_tasks,id'],
            'student_id' => ['sometimes', 'required', 'integer', 'exists:english_students,id'],
            'submission_text' => ['sometimes', 'nullable', 'string'],
            'submitted_at' => ['sometimes', 'nullable', 'date'],
            'submission_status' => ['sometimes', 'nullable', Rule::in(['pending', 'submitted', 'late', 'reviewed'])],
            'score' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'feedback' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
