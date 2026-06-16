<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('preschool_health_alerts')) {
            return;
        }

        Schema::create('preschool_health_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('preschool_students')->cascadeOnDelete();
            $table->string('alert_type', 80);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low')->index();
            $table->enum('status', ['new', 'acknowledged', 'in_progress', 'resolved', 'closed'])->default('new')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('source_type', 100);
            $table->string('source_id', 100);
            $table->string('assigned_to_user_id', 16)->nullable();
            $table->string('acknowledged_by_user_id', 16)->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('resolved_by_user_id', 16)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('closed_by_user_id', 16)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'source_type', 'source_id'], 'preschool_health_alerts_source_unique');
            $table->index(['student_id', 'status'], 'preschool_health_alerts_student_status_index');
            $table->index(['student_id', 'severity'], 'preschool_health_alerts_student_severity_index');
            $table->index(['assigned_to_user_id', 'status'], 'preschool_health_alerts_assignee_status_index');

            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('acknowledged_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('closed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_health_alerts');
    }
};
