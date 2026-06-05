<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsam_questions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('form_section_id')
                ->constrained('dsam_form_sections')
                ->cascadeOnDelete();
            $table->foreignId('question_type_id')
                ->constrained('dsam_question_types')
                ->restrictOnDelete();

            // Conditional question support: this question is a child of parent_question_id
            // and is shown only when trigger_option_id is selected
            $table->unsignedBigInteger('parent_question_id')->nullable();
            // trigger_option_id FK added in migration 12 (after dsam_question_options exists)
            $table->unsignedBigInteger('trigger_option_id')->nullable();

            $table->text('label');
            $table->text('label_kh')->nullable();
            $table->string('placeholder', 500)->nullable();
            $table->string('placeholder_kh', 500)->nullable();
            $table->text('help_text')->nullable();
            $table->text('help_text_kh')->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_scored')->default(false);
            $table->decimal('max_score', 8, 2)->nullable();

            // {"required":true,"min":0,"max":100,"min_length":null,"max_length":null,"pattern":null}
            $table->json('validation_rules')->nullable();

            // Type-specific UI config (columns/rows for table_grid, min/max for rating_scale, etc.)
            $table->json('config')->nullable();

            // {"type":"direct|weighted|rubric","weight":1.0,"formula":"sum|max|avg"}
            $table->json('scoring_config')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_question_id')
                ->references('id')->on('dsam_questions')
                ->nullOnDelete();

            $table->index(['form_section_id', 'order_index']);
            $table->index('parent_question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsam_questions');
    }
};
