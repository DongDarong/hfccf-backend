<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sport_teams', function (Blueprint $table): void {
            $table->foreignId('division_id')
                ->nullable()
                ->after('id')
                ->constrained('sport_divisions')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('sport_teams', function (Blueprint $table): void {
            $table->dropForeignKeyIfExists(['division_id']);
            $table->dropColumn('division_id');
        });
    }
};
