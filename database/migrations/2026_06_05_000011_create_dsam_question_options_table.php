<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsam_question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')
                ->constrained('dsam_questions')
                ->cascadeOnDelete();
            $table->string('label', 500);
            $table->string('label_kh', 500)->nullable();
            $table->string('value', 255);
            $table->decimal('score_value', 8, 2)->nullable();   // score awarded when this option is chosen
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->boolean('is_default')->default(false);
            $table->json('config')->nullable();                  // rubric criteria text, colour, etc.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['question_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsam_question_options');
    }
};
