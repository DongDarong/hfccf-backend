<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_form_templates', function (Blueprint $table): void {
            $table->timestamp('published_at')->nullable()->after('settings');
            $table->string('published_by', 16)->nullable()->after('published_at');
            $table->timestamp('archived_at')->nullable()->after('published_by');
            $table->string('archived_by', 16)->nullable()->after('archived_at');

            $table->foreign('published_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('archived_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_form_templates', function (Blueprint $table): void {
            $table->dropForeign(['published_by']);
            $table->dropForeign(['archived_by']);
            $table->dropColumn(['published_at', 'published_by', 'archived_at', 'archived_by']);
        });
    }
};
