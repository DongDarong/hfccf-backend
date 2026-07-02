<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preschool_attendance_records', function (Blueprint $table): void {
            $table->foreignId('attendance_session_id')
                ->nullable()
                ->constrained('preschool_attendance_sessions')
                ->nullOnDelete();

            $table->index(['attendance_session_id', 'student_id'], 'preschool_attendance_records_session_student_index');
        });
    }

    public function down(): void
    {
        Schema::table('preschool_attendance_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attendance_session_id');
        });
    }
};
