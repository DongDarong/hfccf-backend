<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsam_submission_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained('dsam_form_submissions')
                ->cascadeOnDelete();
            $table->foreignId('form_section_id')
                ->constrained('dsam_form_sections')
                ->cascadeOnDelete();

            // Pre-computed per-section results (rebuilt by CalculateScoresAction on each submit/save)
            $table->decimal('raw_score', 10, 4)->default(0);
            $table->decimal('weighted_score', 10, 4)->default(0);
            $table->decimal('max_score', 10, 4)->default(0);
            $table->decimal('percentage', 7, 4)->default(0);

            $table->timestamps();

            $table->unique(['submission_id', 'form_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsam_submission_scores');
    }
};
