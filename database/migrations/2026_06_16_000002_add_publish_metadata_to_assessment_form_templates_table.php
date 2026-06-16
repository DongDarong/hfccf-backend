<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_form_templates', function (Blueprint $table): void {
            $table->text('version_notes')->nullable()->after('settings');
            $table->text('review_notes')->nullable()->after('version_notes');
            $table->string('reviewed_by', 16)->nullable()->after('review_notes');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->foreignId('duplicated_from_template_id')->nullable()->after('reviewed_at')->constrained('assessment_form_templates')->nullOnDelete();
            $table->unsignedSmallInteger('duplicated_from_version')->nullable()->after('duplicated_from_template_id');
            $table->foreignId('restored_from_template_id')->nullable()->after('duplicated_from_version')->constrained('assessment_form_templates')->nullOnDelete();
            $table->unsignedSmallInteger('restored_from_version')->nullable()->after('restored_from_template_id');

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_form_templates', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['duplicated_from_template_id']);
            $table->dropForeign(['restored_from_template_id']);
            $table->dropColumn([
                'version_notes',
                'review_notes',
                'reviewed_by',
                'reviewed_at',
                'duplicated_from_template_id',
                'duplicated_from_version',
                'restored_from_template_id',
                'restored_from_version',
            ]);
        });
    }
};
