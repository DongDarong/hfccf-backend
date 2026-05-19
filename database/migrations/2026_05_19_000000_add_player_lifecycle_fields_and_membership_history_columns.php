<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sport_players', function (Blueprint $table): void {
            $table->string('roster_status', 16)->default('inactive')->after('approval_status')->index();
            $table->string('disciplinary_status', 32)->nullable()->after('roster_status')->index();
            $table->string('injury_status', 32)->nullable()->after('disciplinary_status')->index();
            $table->timestamp('archived_at')->nullable()->after('injury_status');
            $table->text('status_notes')->nullable()->after('archived_at');
        });

        DB::table('sport_players')
            ->orderBy('id')
            ->chunkById(200, function ($players): void {
                foreach ($players as $player) {
                    $rosterStatus = $player->approval_status === 'pending'
                        ? 'inactive'
                        : ($player->status ?: 'active');

                    DB::table('sport_players')
                        ->where('id', $player->id)
                        ->update([
                            'roster_status' => $rosterStatus,
                        ]);
                }
            });

        Schema::table('sport_player_team_memberships', function (Blueprint $table): void {
            $table->string('updated_by_user_id', 32)->nullable()->after('created_by_user_id')->index();
            $table->timestamp('suspension_until')->nullable()->after('left_at');
            $table->text('injury_notes')->nullable()->after('suspension_until');
            $table->text('notes')->nullable()->after('injury_notes');

            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sport_player_team_memberships', function (Blueprint $table): void {
            $table->dropForeign(['updated_by_user_id']);
            $table->dropColumn([
                'updated_by_user_id',
                'suspension_until',
                'injury_notes',
                'notes',
            ]);
        });

        Schema::table('sport_players', function (Blueprint $table): void {
            $table->dropColumn([
                'roster_status',
                'disciplinary_status',
                'injury_status',
                'archived_at',
                'status_notes',
            ]);
        });
    }
};
