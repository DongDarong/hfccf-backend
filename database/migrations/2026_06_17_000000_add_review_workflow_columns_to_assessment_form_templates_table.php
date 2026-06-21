<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_form_templates', function (Blueprint $table): void {
            $table->string('review_status', 32)->nullable()->default('draft')->after('status');
            $table->string('submitted_by', 16)->nullable()->after('review_status');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->string('review_started_by', 16)->nullable()->after('submitted_at');
            $table->timestamp('review_started_at')->nullable()->after('review_started_by');

            $table->foreign('submitted_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('review_started_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['module', 'review_status'], 'assessment_form_templates_module_review_status_index');
            $table->index('review_status', 'assessment_form_templates_review_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_form_templates', function (Blueprint $table): void {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['review_started_by']);
            $table->dropIndex('assessment_form_templates_module_review_status_index');
            $table->dropIndex('assessment_form_templates_review_status_index');
            $table->dropColumn([
                'review_status',
                'submitted_by',
                'submitted_at',
                'review_started_by',
                'review_started_at',
            ]);
        });
    }
};
