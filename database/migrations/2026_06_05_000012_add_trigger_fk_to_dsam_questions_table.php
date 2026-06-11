<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // trigger_option_id FK must be added after dsam_question_options exists (migration 11)
    public function up(): void
    {
        Schema::table('dsam_questions', function (Blueprint $table): void {
            $table->foreign('trigger_option_id')
                ->references('id')->on('dsam_question_options')
                ->nullOnDelete();

            $table->index('trigger_option_id');
        });
    }

    public function down(): void
    {
        Schema::table('dsam_questions', function (Blueprint $table): void {
            $table->dropForeign(['trigger_option_id']);
            $table->dropIndex(['trigger_option_id']);
        });
    }
};
