<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('notification_type', 100);
            $table->string('title', 255);
            $table->text('body');
            $table->string('severity', 32)->default('medium');
            $table->enum('status', ['unread', 'read', 'archived'])->default('unread');
            $table->string('target_user_id', 16)->nullable();
            $table->string('target_role', 64)->nullable();
            $table->string('source_type', 100)->nullable();
            $table->string('source_id', 191)->nullable();
            $table->foreignId('preschool_student_id')->nullable()->constrained('preschool_students')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('preschool_class_id')->nullable()->constrained('preschool_classes')->nullOnDelete()->cascadeOnUpdate();
            $table->string('action_route', 255)->nullable();
            $table->json('action_params')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('created_by', 16)->nullable();
            $table->timestamps();

            $table->index('notification_type', 'preschool_notifications_type_index');
            $table->index('severity', 'preschool_notifications_severity_index');
            $table->index('status', 'preschool_notifications_status_index');
            $table->index('target_user_id', 'preschool_notifications_target_user_index');
            $table->index('target_role', 'preschool_notifications_target_role_index');
            $table->index(['source_type', 'source_id'], 'preschool_notifications_source_index');
            $table->index('preschool_student_id', 'preschool_notifications_student_index');
            $table->index('preschool_class_id', 'preschool_notifications_class_index');
            $table->index('created_at', 'preschool_notifications_created_at_index');

            $table->foreign('target_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('preschool_automation_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('task_type', 100);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('priority', 32)->default('normal');
            $table->enum('status', ['open', 'in_progress', 'completed', 'cancelled', 'overdue'])->default('open');
            $table->string('assigned_to_user_id', 16)->nullable();
            $table->string('assigned_role', 64)->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->string('source_id', 191)->nullable();
            $table->foreignId('preschool_student_id')->nullable()->constrained('preschool_students')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('preschool_class_id')->nullable()->constrained('preschool_classes')->nullOnDelete()->cascadeOnUpdate();
            $table->string('action_route', 255)->nullable();
            $table->json('action_params')->nullable();
            $table->string('created_by', 16)->nullable();
            $table->string('completed_by', 16)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('cancelled_by', 16)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('task_type', 'preschool_automation_tasks_type_index');
            $table->index('priority', 'preschool_automation_tasks_priority_index');
            $table->index('status', 'preschool_automation_tasks_status_index');
            $table->index('assigned_to_user_id', 'preschool_automation_tasks_assigned_user_index');
            $table->index('assigned_role', 'preschool_automation_tasks_assigned_role_index');
            $table->index('due_at', 'preschool_automation_tasks_due_at_index');
            $table->index(['source_type', 'source_id'], 'preschool_automation_tasks_source_index');
            $table->index('preschool_student_id', 'preschool_automation_tasks_student_index');
            $table->index('preschool_class_id', 'preschool_automation_tasks_class_index');
            $table->index('created_at', 'preschool_automation_tasks_created_at_index');

            $table->foreign('assigned_to_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('completed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('cancelled_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_automation_tasks');
        Schema::dropIfExists('preschool_notifications');
    }
};
