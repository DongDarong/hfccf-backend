<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_workflow_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->enum('mode', ['preview', 'run'])->default('run');
            $table->enum('status', ['pending', 'running', 'completed', 'completed_with_errors', 'failed', 'cancelled'])->default('pending');
            $table->string('definition_key', 100)->nullable();
            $table->string('source_type', 100)->nullable();
            $table->json('filters')->nullable();
            $table->unsignedInteger('requested_limit')->nullable();
            $table->unsignedInteger('batch_size')->nullable();
            $table->unsignedInteger('eligible_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('existing_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('started_by_user_id', 16);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['mode', 'status'], 'preschool_workflow_sync_runs_mode_status_index');
            $table->index('definition_key', 'preschool_workflow_sync_runs_definition_key_index');
            $table->index('source_type', 'preschool_workflow_sync_runs_source_type_index');
            $table->index('started_by_user_id', 'preschool_workflow_sync_runs_started_by_user_id_index');
            $table->index('started_at', 'preschool_workflow_sync_runs_started_at_index');
            $table->index('created_at', 'preschool_workflow_sync_runs_created_at_index');

            $table->foreign('started_by_user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('preschool_workflow_sync_run_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_run_id')
                ->constrained('preschool_workflow_sync_runs')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('definition_key', 100);
            $table->string('source_type', 100);
            $table->string('source_id', 128);
            $table->string('source_label', 191)->nullable();
            $table->enum('result_status', ['created', 'existing', 'skipped', 'failed']);
            $table->text('reason')->nullable();
            $table->foreignId('workflow_instance_id')->nullable()->constrained('preschool_workflow_instances')->nullOnDelete()->cascadeOnUpdate();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('sync_run_id', 'preschool_workflow_sync_run_items_sync_run_id_index');
            $table->index(['definition_key', 'source_type'], 'preschool_workflow_sync_run_items_definition_source_index');
            $table->index(['source_type', 'source_id'], 'preschool_workflow_sync_run_items_source_index');
            $table->index('result_status', 'preschool_workflow_sync_run_items_result_status_index');
            $table->index('processed_at', 'preschool_workflow_sync_run_items_processed_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_workflow_sync_run_items');
        Schema::dropIfExists('preschool_workflow_sync_runs');
    }
};
