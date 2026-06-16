<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('preschool_student_health_audit_logs')) {
            return;
        }

        Schema::create('preschool_student_health_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('actor_user_id', 16)->nullable();
            $table->string('action', 64);
            $table->string('entity_type', 191);
            $table->string('entity_id', 64)->nullable();
            $table->string('severity', 32)->nullable();
            $table->string('visibility', 16)->default('admin');
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('student_id', 'preschool_student_health_audit_logs_student_id_index');
            $table->index('actor_user_id', 'preschool_student_health_audit_logs_actor_user_id_index');
            $table->index('action', 'preschool_student_health_audit_logs_action_index');
            $table->index('entity_type', 'preschool_student_health_audit_logs_entity_type_index');
            $table->index('entity_id', 'preschool_student_health_audit_logs_entity_id_index');
            $table->index('severity', 'preschool_student_health_audit_logs_severity_index');
            $table->index('visibility', 'preschool_student_health_audit_logs_visibility_index');
            $table->index('created_at', 'preschool_student_health_audit_logs_created_at_index');

            $table->foreign('student_id')
                ->references('id')
                ->on('preschool_students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_student_health_audit_logs');
    }
};
