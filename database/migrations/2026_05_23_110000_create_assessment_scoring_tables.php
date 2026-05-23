<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_scoring_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')
                ->constrained('assessment_form_templates')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->enum('scope', ['template', 'section', 'question'])->default('template');
            $table->unsignedBigInteger('scope_id');
            $table->enum('rule_type', ['sum', 'weighted', 'percentage', 'formula', 'manual'])->default('sum');
            $table->text('formula')->nullable();
            $table->decimal('max_score', 10, 2)->nullable();
            $table->decimal('pass_score', 10, 2)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['scope', 'scope_id'], 'assessment_scoring_rules_scope_scope_id_index');
            $table->index('template_id', 'assessment_scoring_rules_template_id_index');
        });

        Schema::create('assessment_risk_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')
                ->constrained('assessment_form_templates')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('label', 64);
            $table->string('label_kh', 64)->nullable();
            $table->string('key', 32);
            $table->decimal('min_score', 10, 2);
            $table->decimal('max_score', 10, 2);
            $table->string('color_code', 7)->default('#94a3b8');
            $table->tinyInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'sort_order'], 'assessment_risk_levels_template_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_risk_levels');
        Schema::dropIfExists('assessment_scoring_rules');
    }
};
