<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sport_teams', function (Blueprint $table): void {
            $table->foreignId('playing_style_id')
                ->nullable()
                ->after('division_id')
                ->constrained('sport_playing_styles')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('sport_teams', function (Blueprint $table): void {
            $table->dropForeignKeyIfExists(['playing_style_id']);
            $table->dropColumn('playing_style_id');
        });
    }
};
