<?php

namespace App\Services;

use App\Models\EnglishClass;
use App\Models\EnglishStudent;
use App\Models\EnglishTask;
use App\Models\EnglishTaskSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnglishService
{
    public function dashboardSummary(User $user): array
    {
        $isTeacher = $user->role_code === 'teacher-english';

        $teacherQuery = User::query()->where('role_code', 'teacher-english');
        $classQuery = EnglishClass::query();
        $taskQuery = EnglishTask::query();
        $submissionQuery = EnglishTaskSubmission::query();
        $recentAssignments = EnglishTask::query()->with(['class', 'assignedBy'])->latest('id');
        $recentReviews = EnglishTaskSubmission::query()->with(['task.class', 'student', 'reviewedBy'])->whereNotNull('reviewed_at')->latest('id');

        if ($isTeacher) {
            $teacherClassIds = EnglishClass::query()
                ->where('teacher_user_id', $user->id)
                ->pluck('id')
                ->all();

            $teacherTaskIds = EnglishTask::query()
                ->where('assigned_by_user_id', $user->id)
                ->orWhereIn('class_id', $teacherClassIds)
                ->pluck('id')
                ->all();

            $classQuery->whereIn('id', $teacherClassIds);
            $taskQuery->whereIn('id', $teacherTaskIds);
            $submissionQuery->whereIn('task_id', $teacherTaskIds);
            $recentAssignments->whereIn('id', $teacherTaskIds);
            $recentReviews->whereIn('task_id', $teacherTaskIds);

            return [
                'summaryCards' => [
                    ['title' => 'Assigned classes', 'value' => (clone $classQuery)->count(), 'label' => 'Your classes', 'status' => 'success'],
                    ['title' => 'Active tasks', 'value' => (clone $taskQuery)->where('task_status', 'assigned')->count(), 'label' => 'Open tasks', 'status' => 'info'],
                    ['title' => 'Pending submissions', 'value' => (clone $submissionQuery)->whereIn('submission_status', ['pending', 'submitted', 'late'])->count(), 'label' => 'Awaiting review', 'status' => 'warning'],
                    ['title' => 'Reviewed submissions', 'value' => (clone $submissionQuery)->where('submission_status', 'reviewed')->count(), 'label' => 'Completed reviews', 'status' => 'success'],
                ],
                'recentAssignments' => $recentAssignments->limit(5)->get()->map(fn (EnglishTask $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'className' => $task->class?->name,
                    'taskStatus' => $task->task_status,
                    'dueDate' => $task->due_date?->toDateString(),
                ])->values(),
                'recentReviews' => $recentReviews->limit(5)->get()->map(fn (EnglishTaskSubmission $submission): array => [
                    'id' => $submission->id,
                    'taskTitle' => $submission->task?->title,
                    'studentName' => trim(($submission->student?->first_name ?? '').' '.($submission->student?->last_name ?? '')),
                    'submissionStatus' => $submission->submission_status,
                    'reviewedAt' => $submission->reviewed_at?->toISOString(),
                ])->values(),
                'teacherWorkload' => [
                    'classes' => (clone $classQuery)->count(),
                    'tasks' => (clone $taskQuery)->count(),
                    'submissions' => (clone $submissionQuery)->count(),
                ],
            ];
        }

        return [
            'summaryCards' => [
                ['title' => 'Total students', 'value' => EnglishStudent::query()->count(), 'label' => 'Registered learners', 'status' => 'success'],
                ['title' => 'Total teachers', 'value' => (clone $teacherQuery)->count(), 'label' => 'English instructors', 'status' => 'info'],
                ['title' => 'Active classes', 'value' => (clone $classQuery)->where('status', 'active')->count(), 'label' => 'Running classes', 'status' => 'warning'],
                ['title' => 'Active tasks', 'value' => (clone $taskQuery)->where('task_status', 'assigned')->count(), 'label' => 'Task pipeline', 'status' => 'success'],
                ['title' => 'Pending submissions', 'value' => (clone $submissionQuery)->whereIn('submission_status', ['pending', 'submitted', 'late'])->count(), 'label' => 'Awaiting review', 'status' => 'warning'],
                ['title' => 'Reviewed submissions', 'value' => (clone $submissionQuery)->where('submission_status', 'reviewed')->count(), 'label' => 'Scored items', 'status' => 'info'],
            ],
            'recentAssignments' => $recentAssignments->limit(5)->get()->map(fn (EnglishTask $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'className' => $task->class?->name,
                'taskStatus' => $task->task_status,
                'dueDate' => $task->due_date?->toDateString(),
            ])->values(),
            'recentReviews' => $recentReviews->limit(5)->get()->map(fn (EnglishTaskSubmission $submission): array => [
                'id' => $submission->id,
                'taskTitle' => $submission->task?->title,
                'studentName' => trim(($submission->student?->first_name ?? '').' '.($submission->student?->last_name ?? '')),
                'submissionStatus' => $submission->submission_status,
                'reviewedAt' => $submission->reviewed_at?->toISOString(),
            ])->values(),
            'teacherWorkload' => $teacherQuery->get()->map(fn (User $teacher): array => [
                'id' => $teacher->id,
                'name' => trim($teacher->first_name.' '.$teacher->last_name),
                'classes' => EnglishClass::query()->where('teacher_user_id', $teacher->id)->count(),
                'tasks' => EnglishTask::query()->where('assigned_by_user_id', $teacher->id)->count(),
            ])->values(),
        ];
    }

    public function nextClassCode(): string
    {
        return $this->nextSequentialCode('english_classes', 'class_code', 'ENG-CLS-');
    }

    public function nextStudentCode(): string
    {
        return $this->nextSequentialCode('english_students', 'student_code', 'ENG-STU-');
    }

    private function nextSequentialCode(string $table, string $column, string $prefix): string
    {
        $existing = DB::table($table)->select($column)->get()->map(function ($row) use ($column): int {
            $value = (string) ($row->{$column} ?? '');
            if (preg_match('/(\d+)$/', $value, $matches)) {
                return (int) $matches[1];
            }

            return 0;
        })->max() ?? 0;

        return $prefix.str_pad((string) ($existing + 1), 3, '0', STR_PAD_LEFT);
    }
}
