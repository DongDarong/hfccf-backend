<?php

namespace App\Services;

use App\Models\PreschoolAutomationTask;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolHealthAlert;
use App\Models\PreschoolInvoice;
use App\Models\PreschoolNotification;
use App\Models\PreschoolPayment;
use App\Models\PreschoolStudentAssessment;
use App\Models\User;
use App\Support\PreschoolAssessmentStatus;
use App\Support\PreschoolAttendanceConfigurationService;
use App\Support\PreschoolAttendanceSessionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PreschoolNotificationRuleService
{
    public function __construct(
        private readonly PreschoolNotificationService $notificationService,
        private readonly PreschoolAutomationTaskService $taskService,
        private readonly PreschoolAttendanceSessionService $attendanceSessionService,
        private readonly PreschoolAttendanceConfigurationService $attendanceConfigurationService,
    ) {
    }

    public function runDailyChecks(?User $actor = null): array
    {
        $viewer = $this->resolveViewer($actor);
        $stats = [
            'checkedAt' => now()->toISOString(),
            'notificationsCreated' => 0,
            'notificationsUpdated' => 0,
            'tasksCreated' => 0,
            'tasksUpdated' => 0,
            'buckets' => [],
        ];

        if (! $viewer) {
            $stats['buckets']['skipped'] = true;

            return $stats;
        }

        DB::transaction(function () use ($viewer, &$stats): void {
            $this->syncMissingSessions($viewer, $stats);
            $this->syncAttendanceFollowUps($viewer, $stats);
            $this->syncHealthAlerts($viewer, $stats);
            $this->syncInvoices($viewer, $stats);
            $this->syncPayments($viewer, $stats);
            $this->syncAssessments($viewer, $stats);
        });

        return $stats;
    }

    private function syncMissingSessions(User $viewer, array &$stats): void
    {
        $missingSessions = $this->attendanceSessionService->missingSessions($viewer);

        foreach ($missingSessions as $session) {
            $class = $session->preschoolClass;
            $teacherId = $class?->teacher_user_id;

            $notification = $this->notificationService->upsertNotification([
                'notification_type' => 'attendance.missing_session',
                'title' => 'Missing attendance session',
                'body' => trim(($class?->name ?? 'A preschool class').' has an overdue attendance session that still needs attention.'),
                'severity' => 'medium',
                'target_user_id' => $teacherId,
                'target_role' => 'adminpreschool',
                'source_type' => 'attendance_session',
                'source_id' => (string) $session->id,
                'preschool_class_id' => $session->preschool_class_id,
                'action_route' => 'dashboard-preschool-admin-attendance-session-details',
                'action_params' => ['id' => $session->id],
                'created_by' => $viewer->id,
            ]);
            $this->updateStats($stats, 'notifications', $notification['created'], $notification['updated']);

            $task = $this->taskService->upsertTask([
                'task_type' => 'attendance.follow_up',
                'title' => 'Follow up missing attendance session',
                'description' => 'Review the missed attendance session and update the record or escalate if needed.',
                'priority' => 'high',
                'status' => PreschoolAutomationTask::STATUS_OVERDUE,
                'assigned_to_user_id' => $teacherId,
                'assigned_role' => 'adminpreschool',
                'due_at' => Carbon::parse($session->attendance_date)->endOfDay(),
                'source_type' => 'attendance_session',
                'source_id' => (string) $session->id,
                'preschool_class_id' => $session->preschool_class_id,
                'action_route' => 'dashboard-preschool-admin-attendance-session-details',
                'action_params' => ['id' => $session->id],
                'created_by' => $viewer->id,
            ]);
            $this->updateStats($stats, 'tasks', $task['created'], $task['updated']);
        }
    }

    private function syncAttendanceFollowUps(User $viewer, array &$stats): void
    {
        $threshold = $this->attendanceConfigurationService->getAbsenceAlertDays();
        $communications = PreschoolGuardianCommunication::query()
            ->with(['student.classes', 'guardian'])
            ->where('source_type', 'attendance')
            ->whereIn('communication_type', ['repeated_absence', 'late_pattern', 'attendance_exception'])
            ->whereIn('status', ['queued', 'sent'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        foreach ($communications as $communication) {
            $student = $communication->student()->withTrashed()->first();
            $class = $student?->classes()->first();
            $teacherId = $class?->teacher_user_id;
            $dueAt = $communication->created_at?->copy()->addDay() ?? now()->addDay();

            $notification = $this->notificationService->upsertNotification([
                'notification_type' => 'attendance.follow_up',
                'title' => 'Attendance follow-up required',
                'body' => $communication->message ?: 'A student attendance follow-up needs staff attention.',
                'severity' => $communication->severity ?: 'medium',
                'target_user_id' => $teacherId,
                'target_role' => 'adminpreschool',
                'source_type' => 'attendance_communication',
                'source_id' => (string) $communication->id,
                'preschool_student_id' => $communication->student_id,
                'preschool_class_id' => $class?->id,
                'action_route' => 'dashboard-preschool-admin-attendance-alerts',
                'action_params' => [
                    'communicationType' => $communication->communication_type,
                    'threshold' => $threshold,
                ],
                'created_by' => $viewer->id,
            ]);
            $this->updateStats($stats, 'notifications', $notification['created'], $notification['updated']);

            $task = $this->taskService->upsertTask([
                'task_type' => 'attendance.follow_up',
                'title' => 'Follow up with guardian',
                'description' => $communication->subject ?: 'Attendance follow-up is pending.',
                'priority' => $communication->severity === 'high' ? 'high' : 'normal',
                'status' => $dueAt->lt(now()) ? PreschoolAutomationTask::STATUS_OVERDUE : PreschoolAutomationTask::STATUS_OPEN,
                'assigned_to_user_id' => $teacherId,
                'assigned_role' => 'adminpreschool',
                'due_at' => $dueAt,
                'source_type' => 'attendance_communication',
                'source_id' => (string) $communication->id,
                'preschool_student_id' => $communication->student_id,
                'preschool_class_id' => $class?->id,
                'action_route' => 'dashboard-preschool-admin-guardian-communications',
                'action_params' => [
                    'studentId' => $communication->student_id,
                    'guardianId' => $communication->guardian_id,
                ],
                'created_by' => $viewer->id,
            ]);
            $this->updateStats($stats, 'tasks', $task['created'], $task['updated']);
        }
    }

    private function syncHealthAlerts(User $viewer, array &$stats): void
    {
        $alerts = PreschoolHealthAlert::query()
            ->with(['student', 'assignedTo'])
            ->whereIn('severity', ['high', 'critical'])
            ->whereNotIn('status', ['resolved', 'closed'])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        foreach ($alerts as $alert) {
            $class = $alert->student?->classes()->first();
            $teacherId = $class?->teacher_user_id;
            $priority = $alert->severity === 'critical' ? 'urgent' : 'high';

            $notification = $this->notificationService->upsertNotification([
                'notification_type' => 'health.alert',
                'title' => 'Critical health alert',
                'body' => trim(($alert->title ?: 'Health alert').' needs staff attention.'),
                'severity' => $alert->severity,
                'target_user_id' => $teacherId,
                'target_role' => 'adminpreschool',
                'source_type' => 'health_alert',
                'source_id' => (string) $alert->id,
                'preschool_student_id' => $alert->student_id,
                'preschool_class_id' => $class?->id,
                'action_route' => 'dashboard-preschool-admin-health-student',
                'action_params' => ['id' => $alert->student_id],
                'created_by' => $viewer->id,
            ]);
            $this->updateStats($stats, 'notifications', $notification['created'], $notification['updated']);

            $task = $this->taskService->upsertTask([
                'task_type' => 'health.follow_up',
                'title' => 'Review health alert',
                'description' => $alert->description ?: 'Review the health alert record and follow up with the class team.',
                'priority' => $priority,
                'status' => PreschoolAutomationTask::STATUS_OPEN,
                'assigned_to_user_id' => $teacherId,
                'assigned_role' => 'adminpreschool',
                'due_at' => now()->addDay(),
                'source_type' => 'health_alert',
                'source_id' => (string) $alert->id,
                'preschool_student_id' => $alert->student_id,
                'preschool_class_id' => $class?->id,
                'action_route' => 'dashboard-preschool-admin-health-student',
                'action_params' => ['id' => $alert->student_id],
                'created_by' => $viewer->id,
            ]);
            $this->updateStats($stats, 'tasks', $task['created'], $task['updated']);
        }
    }

    private function syncInvoices(User $viewer, array &$stats): void
    {
        $invoices = PreschoolInvoice::query()
            ->with(['student', 'preschoolClass'])
            ->where('balance_due', '>', 0)
            ->whereNotIn('status', ['cancelled', 'paid'])
            ->where(function ($query): void {
                $query->where('status', 'overdue')
                    ->orWhere(function ($dueQuery): void {
                        $dueQuery->whereNotNull('due_date')
                            ->whereDate('due_date', '<', now()->toDateString());
                    });
            })
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        foreach ($invoices as $invoice) {
            $teacherId = $invoice->preschoolClass?->teacher_user_id;

            $notification = $this->notificationService->upsertNotification([
                'notification_type' => 'payments.overdue_invoice',
                'title' => 'Overdue invoice',
                'body' => 'An overdue invoice needs review and follow-up.',
                'severity' => 'high',
                'target_user_id' => $teacherId,
                'target_role' => 'adminpreschool',
                'source_type' => 'invoice',
                'source_id' => (string) $invoice->id,
                'preschool_student_id' => $invoice->student_id,
                'preschool_class_id' => $invoice->class_id,
                'action_route' => 'dashboard-preschool-admin-invoice-detail',
                'action_params' => ['id' => $invoice->id],
                'created_by' => $viewer->id,
            ]);
            $this->updateStats($stats, 'notifications', $notification['created'], $notification['updated']);

            $task = $this->taskService->upsertTask([
                'task_type' => 'payments.follow_up',
                'title' => 'Follow up overdue invoice',
                'description' => 'Review the overdue invoice and contact the relevant staff member.',
                'priority' => 'high',
                'status' => PreschoolAutomationTask::STATUS_OVERDUE,
                'assigned_to_user_id' => $teacherId,
                'assigned_role' => 'adminpreschool',
                'due_at' => $invoice->due_date?->endOfDay() ?? now(),
                'source_type' => 'invoice',
                'source_id' => (string) $invoice->id,
                'preschool_student_id' => $invoice->student_id,
                'preschool_class_id' => $invoice->class_id,
                'action_route' => 'dashboard-preschool-admin-invoice-detail',
                'action_params' => ['id' => $invoice->id],
                'created_by' => $viewer->id,
            ]);
            $this->updateStats($stats, 'tasks', $task['created'], $task['updated']);
        }
    }

    private function syncPayments(User $viewer, array &$stats): void
    {
        $payments = PreschoolPayment::query()
            ->with(['student', 'preschoolClass', 'invoice', 'receipts'])
            ->where('payment_status', 'paid')
            ->whereDoesntHave('receipts')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        foreach ($payments as $payment) {
            $teacherId = $payment->preschoolClass?->teacher_user_id;

            $notification = $this->notificationService->upsertNotification([
                'notification_type' => 'payments.pending_receipt',
                'title' => 'Pending receipt',
                'body' => 'A paid payment does not have a receipt yet.',
                'severity' => 'medium',
                'target_user_id' => $teacherId,
                'target_role' => 'adminpreschool',
                'source_type' => 'payment',
                'source_id' => (string) $payment->id,
                'preschool_student_id' => $payment->student_id,
                'preschool_class_id' => $payment->class_id,
                'action_route' => 'dashboard-preschool-admin-invoice-detail',
                'action_params' => ['id' => $payment->invoice_id],
                'created_by' => $viewer->id,
            ]);
            $this->updateStats($stats, 'notifications', $notification['created'], $notification['updated']);
        }
    }

    private function syncAssessments(User $viewer, array &$stats): void
    {
        $draftThreshold = now()->subDay();
        $overdueThreshold = now()->subDays(3);

        $assessments = PreschoolStudentAssessment::query()
            ->with(['student', 'preschoolClass', 'category', 'assessedBy'])
            ->where('status', PreschoolAssessmentStatus::DRAFT)
            ->whereDate('assessment_date', '<=', $draftThreshold->toDateString())
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        foreach ($assessments as $assessment) {
            $teacherId = $assessment->preschoolClass?->teacher_user_id ?? $assessment->assessed_by_user_id;
            $isOverdue = $assessment->assessment_date !== null && $assessment->assessment_date->lte($overdueThreshold);
            $priority = $isOverdue ? 'high' : 'normal';

            $notification = $this->notificationService->upsertNotification([
                'notification_type' => $isOverdue ? 'assessments.overdue_grading' : 'assessments.pending_review',
                'title' => $isOverdue ? 'Overdue assessment grading' : 'Pending assessment review',
                'body' => $isOverdue
                    ? 'An assessment is overdue for grading and review.'
                    : 'An assessment is waiting for review and finalization.',
                'severity' => $isOverdue ? 'high' : 'medium',
                'target_user_id' => $teacherId,
                'target_role' => 'adminpreschool',
                'source_type' => 'assessment',
                'source_id' => (string) $assessment->id,
                'preschool_student_id' => $assessment->student_id,
                'preschool_class_id' => $assessment->class_id,
                'action_route' => 'dashboard-preschool-admin-reports-assessments',
                'action_params' => ['studentId' => $assessment->student_id],
                'created_by' => $viewer->id,
            ]);
            $this->updateStats($stats, 'notifications', $notification['created'], $notification['updated']);

            $task = $this->taskService->upsertTask([
                'task_type' => $isOverdue ? 'assessments.overdue_grading' : 'assessments.pending_review',
                'title' => $isOverdue ? 'Complete overdue grading' : 'Review assessment',
                'description' => $assessment->teacher_comment ?: 'Review the draft assessment and finalize it when ready.',
                'priority' => $priority,
                'status' => $isOverdue ? PreschoolAutomationTask::STATUS_OVERDUE : PreschoolAutomationTask::STATUS_OPEN,
                'assigned_to_user_id' => $teacherId,
                'assigned_role' => 'adminpreschool',
                'due_at' => $isOverdue ? $assessment->assessment_date?->endOfDay() : $assessment->assessment_date?->copy()->addDay()->endOfDay(),
                'source_type' => 'assessment',
                'source_id' => (string) $assessment->id,
                'preschool_student_id' => $assessment->student_id,
                'preschool_class_id' => $assessment->class_id,
                'action_route' => 'preschool-assessment-dashboard',
                'action_params' => ['studentId' => $assessment->student_id],
                'created_by' => $viewer->id,
            ]);
            $this->updateStats($stats, 'tasks', $task['created'], $task['updated']);
        }
    }

    private function resolveViewer(?User $actor): ?User
    {
        if ($actor) {
            return $actor;
        }

        return User::query()
            ->whereIn('role_code', ['superadmin', 'adminpreschool'])
            ->where('status', 'active')
            ->orderByRaw("CASE role_code WHEN 'superadmin' THEN 1 ELSE 2 END")
            ->first();
    }

    private function updateStats(array &$stats, string $bucket, bool $created, bool $updated): void
    {
        $stats[$bucket.'Created'] += $created ? 1 : 0;
        $stats[$bucket.'Updated'] += $updated ? 1 : 0;
    }
}
