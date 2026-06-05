<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsam_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('form_template_id')
                ->constrained('dsam_form_templates')
                ->restrictOnDelete();
            $table->foreignId('student_id')
                ->constrained('preschool_students')
                ->restrictOnDelete();
            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->restrictOnDelete();

            // users.id is string(16)
            $table->string('submitted_by', 16)->nullable();
            $table->string('evaluated_by', 16)->nullable();
            $table->string('approved_by', 16)->nullable();

            $table->enum('status', [
                'draft', 'in_progress', 'submitted', 'under_review', 'approved', 'rejected', 'archived'
            ])->default('draft');

            $table->unsignedTinyInteger('current_step')->default(0);

            // Calculated after submission
            $table->decimal('total_score', 10, 4)->nullable();
            $table->decimal('max_possible_score', 10, 4)->nullable();
            $table->decimal('score_percentage', 7, 4)->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->nullable();

            // Auto-save blob — the wizard stores partial progress here
            $table->json('draft_data')->nullable();

            $table->text('submission_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('evaluated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            $table->index('student_id');
            $table->index(['status', 'risk_level']);
            $table->index('academic_year_id');
            $table->index('form_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsam_form_submissions');
    }
};
