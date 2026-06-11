<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsam_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained('dsam_form_submissions')
                ->cascadeOnDelete();
            $table->foreignId('question_id')
                ->constrained('dsam_questions')
                ->restrictOnDelete();

            // Typed value columns — only one is populated per answer row, chosen by question_type
            $table->text('text_value')->nullable();              // short_text, long_text, signature
            $table->decimal('number_value', 15, 4)->nullable();  // number, rating_scale
            $table->date('date_value')->nullable();              // date
            $table->json('json_value')->nullable();              // checkbox[], table_grid rows, rating detail

            // Relative path inside the configured disk
            $table->string('file_path', 500)->nullable();        // file_upload

            // Denormalised score for fast aggregation — recomputed on each save
            $table->decimal('score_value', 8, 4)->nullable();

            $table->timestamps();

            $table->unique(['submission_id', 'question_id']);
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsam_answers');
    }
};
