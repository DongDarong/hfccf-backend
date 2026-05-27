<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sport_match_events', function (Blueprint $table): void {
            $table->foreignId('squad_id')
                ->nullable()
                ->after('team_id')
                ->constrained('sport_match_squads')
                ->nullOnDelete();

            $table->foreignId('squad_player_id')
                ->nullable()
                ->after('squad_id')
                ->constrained('sport_match_squad_players')
                ->nullOnDelete();

            $table->foreignId('related_squad_player_id')
                ->nullable()
                ->after('squad_player_id')
                ->constrained('sport_match_squad_players')
                ->nullOnDelete();

            $table->string('player_name_snapshot')->nullable()->after('related_squad_player_id');
            $table->unsignedSmallInteger('jersey_number_snapshot')->nullable()->after('player_name_snapshot');
            $table->string('position_snapshot')->nullable()->after('jersey_number_snapshot');
            $table->string('period', 32)->nullable()->after('extra_time_minute');
            $table->index(['match_id', 'minute', 'extra_time_minute', 'created_at'], 'sport_match_events_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::table('sport_match_events', function (Blueprint $table): void {
            $table->dropIndex('sport_match_events_timeline_index');
            $table->dropConstrainedForeignId('related_squad_player_id');
            $table->dropConstrainedForeignId('squad_player_id');
            $table->dropConstrainedForeignId('squad_id');
            $table->dropColumn([
                'player_name_snapshot',
                'jersey_number_snapshot',
                'position_snapshot',
                'period',
            ]);
        });
    }
};
