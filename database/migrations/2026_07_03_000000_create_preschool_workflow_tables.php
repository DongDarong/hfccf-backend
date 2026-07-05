<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_workflow_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('domain', 100)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('preschool_workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_id')
                ->constrained('preschool_workflow_definitions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('key', 100);
            $table->string('name', 191);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('step_type', 32)->default('action');
            $table->string('assigned_role', 64)->nullable();
            $table->unsignedInteger('sla_hours')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['workflow_definition_id', 'key'], 'preschool_workflow_steps_definition_key_unique');
            $table->index(['workflow_definition_id', 'sort_order'], 'preschool_workflow_steps_definition_sort_index');
            $table->index('step_type', 'preschool_workflow_steps_type_index');
        });

        Schema::create('preschool_workflow_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_id')
                ->constrained('preschool_workflow_definitions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('source_type', 100);
            $table->string('source_id', 191);
            $table->string('source_label', 255)->nullable();
            $table->foreignId('current_step_id')
                ->nullable()
                ->constrained('preschool_workflow_steps')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->enum('status', [
                'open',
                'in_progress',
                'pending_approval',
                'approved',
                'rejected',
                'returned',
                'completed',
                'cancelled',
                'escalated',
                'overdue',
            ])->default('open')->index();
            $table->string('priority', 32)->default('normal')->index();
            $table->string('assigned_to_user_id', 16)->nullable()->index();
            $table->string('assigned_role', 64)->nullable()->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['workflow_definition_id', 'source_type', 'source_id'],
                'preschool_workflow_instances_source_unique'
            );
            $table->index(['workflow_definition_id', 'status'], 'preschool_workflow_instances_definition_status_index');
            $table->index(['assigned_to_user_id', 'status'], 'preschool_workflow_instances_assigned_status_index');
            $table->index(['assigned_role', 'status'], 'preschool_workflow_instances_role_status_index');

            $table->foreign('assigned_to_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('preschool_workflow_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_instance_id')
                ->constrained('preschool_workflow_instances')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('workflow_step_id')
                ->nullable()
                ->constrained('preschool_workflow_steps')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('requested_by_user_id', 16)->nullable()->index();
            $table->string('requested_to_user_id', 16)->nullable()->index();
            $table->string('requested_to_role', 64)->nullable()->index();
            $table->enum('status', ['pending', 'approved', 'rejected', 'returned', 'cancelled', 'escalated'])->default('pending')->index();
            $table->text('decision_notes')->nullable();
            $table->string('decided_by_user_id', 16)->nullable()->index();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workflow_instance_id', 'status'], 'preschool_workflow_approvals_instance_status_index');
            $table->index(['workflow_step_id', 'status'], 'preschool_workflow_approvals_step_status_index');

            $table->foreign('requested_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('requested_to_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('decided_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('preschool_workflow_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_instance_id')
                ->constrained('preschool_workflow_instances')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('event_type', 100)->index();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('actor_user_id', 16)->nullable()->index();
            $table->string('from_status', 32)->nullable()->index();
            $table->string('to_status', 32)->nullable()->index();
            $table->foreignId('from_step_id')
                ->nullable()
                ->constrained('preschool_workflow_steps')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('to_step_id')
                ->nullable()
                ->constrained('preschool_workflow_steps')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workflow_instance_id', 'created_at'], 'preschool_workflow_events_instance_created_index');

            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_workflow_events');
        Schema::dropIfExists('preschool_workflow_approvals');
        Schema::dropIfExists('preschool_workflow_instances');
        Schema::dropIfExists('preschool_workflow_steps');
        Schema::dropIfExists('preschool_workflow_definitions');
    }
};
