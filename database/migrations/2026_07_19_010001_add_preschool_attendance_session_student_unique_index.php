<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preschool_attendance_records', function (Blueprint $table): void {
            $table->unique(['attendance_session_id', 'student_id'], 'preschool_attendance_session_student_unique');
        });
    }

    public function down(): void
    {
        Schema::table('preschool_attendance_records', function (Blueprint $table): void {
            $table->dropUnique('preschool_attendance_session_student_unique');
        });
    }
};
