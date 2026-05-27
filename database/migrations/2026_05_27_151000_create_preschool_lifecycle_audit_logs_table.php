<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_lifecycle_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_user_id', 16)->nullable();
            $table->string('actor_role', 64)->nullable();
            $table->string('action_type', 64);
            $table->string('entity_type', 191);
            $table->string('entity_id', 64)->nullable();
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('term_id')->nullable();
            $table->unsignedBigInteger('report_period_id')->nullable();
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->text('override_reason')->nullable();
            $table->string('lock_code', 64)->nullable();
            $table->string('lock_reason', 255)->nullable();
            $table->json('request_context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('actor_user_id', 'preschool_lifecycle_audit_logs_actor_user_id_index');
            $table->index('actor_role', 'preschool_lifecycle_audit_logs_actor_role_index');
            $table->index('action_type', 'preschool_lifecycle_audit_logs_action_type_index');
            $table->index('entity_type', 'preschool_lifecycle_audit_logs_entity_type_index');
            $table->index('entity_id', 'preschool_lifecycle_audit_logs_entity_id_index');
            $table->index('academic_year_id', 'preschool_lifecycle_audit_logs_academic_year_id_index');
            $table->index('term_id', 'preschool_lifecycle_audit_logs_term_id_index');
            $table->index('report_period_id', 'preschool_lifecycle_audit_logs_report_period_id_index');
            $table->index('created_at', 'preschool_lifecycle_audit_logs_created_at_index');

            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('academic_year_id')
                ->references('id')
                ->on('preschool_academic_years')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('term_id')
                ->references('id')
                ->on('preschool_terms')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('report_period_id')
                ->references('id')
                ->on('preschool_report_periods')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_lifecycle_audit_logs');
    }
};
