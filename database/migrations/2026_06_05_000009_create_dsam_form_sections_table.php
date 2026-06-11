<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsam_form_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_template_id')
                ->constrained('dsam_form_templates')
                ->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('title_kh', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('description_kh')->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            // Weight of this section toward the total score (0.0–1.0, all sections should sum to 1.0)
            $table->decimal('scoring_weight', 5, 4)->default(1.0000);
            $table->boolean('is_required')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['form_template_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsam_form_sections');
    }
};
