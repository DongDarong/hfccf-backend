<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sport_attendance_records', function (Blueprint $table): void {
            $table->string('subject_key', 64)->after('attendance_type');
        });

        DB::table('sport_attendance_records')->orderBy('id')->chunkById(100, function ($records): void {
            foreach ($records as $record) {
                $subjectKey = 'player:'.trim((string) $record->player_id);

                DB::table('sport_attendance_records')
                    ->where('id', $record->id)
                    ->update(['subject_key' => $subjectKey]);
            }
        });

        Schema::table('sport_attendance_records', function (Blueprint $table): void {
            $table->unique(['subject_key', 'attendance_date'], 'sport_attendance_subject_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sport_attendance_records', function (Blueprint $table): void {
            $table->dropUnique('sport_attendance_subject_date_unique');
            $table->dropColumn('subject_key');
        });
    }
};
