<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('preschool_guardian_remediation_logs')) {
            return;
        }

        Schema::create('preschool_guardian_remediation_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('issue_type', 100);
            $table->string('issue_key', 191)->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('guardian_id')->nullable();
            $table->unsignedBigInteger('related_guardian_id')->nullable();
            $table->unsignedBigInteger('relationship_id')->nullable();
            $table->string('action', 100);
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index('issue_type');
            $table->index('student_id');
            $table->index('guardian_id');
            $table->index('related_guardian_id');
            $table->index('performed_by_user_id');
            $table->index('performed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_guardian_remediation_logs');
    }
};
