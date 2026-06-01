<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->string('attendance_type', 16);
            $table->foreignId('team_id')
                ->nullable()
                ->constrained('sport_teams')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('player_id')
                ->nullable()
                ->constrained('sport_players')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('coach_user_id', 16)->nullable();
            $table->string('recorded_by_user_id', 16)->nullable();
            $table->date('attendance_date');
            $table->enum('status', ['present', 'absent', 'late', 'excused']);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['attendance_type', 'attendance_date'], 'sport_attendance_type_date_index');
            $table->index(['team_id', 'attendance_date'], 'sport_attendance_team_date_index');
            $table->index(['player_id', 'attendance_date'], 'sport_attendance_player_date_index');
            $table->index(['coach_user_id', 'attendance_date'], 'sport_attendance_coach_date_index');
            $table->index(['attendance_date', 'status'], 'sport_attendance_date_status_index');
            $table->index('recorded_by_user_id', 'sport_attendance_recorded_by_index');

            $table->foreign('coach_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('recorded_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_attendance_records');
    }
};
