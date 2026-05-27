<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('preschool_guardian_governance_issues')) {
            return;
        }

        Schema::create('preschool_guardian_governance_issues', function (Blueprint $table): void {
            $table->id();
            $table->string('issue_type', 100);
            $table->string('issue_key', 191)->nullable();
            $table->string('severity', 20)->default('info');
            $table->string('priority', 20)->default('low');
            $table->string('status', 30)->default('detected');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('guardian_id')->nullable();
            $table->unsignedBigInteger('relationship_id')->nullable();
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->unsignedInteger('recurrence_count')->default(0);
            $table->json('latest_snapshot')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('issue_type');
            $table->index('issue_key');
            $table->index('severity');
            $table->index('priority');
            $table->index('status');
            $table->index('student_id');
            $table->index('guardian_id');
            $table->index('assigned_to_user_id');
            $table->index('detected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_guardian_governance_issues');
    }
};
