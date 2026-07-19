<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preschool_attendance_sessions', function (Blueprint $table): void {
            // Some development databases received part of this schema before the
            // migration was recorded. Add only the absent fields so those
            // databases can be brought under Laravel migration tracking.
            if (! Schema::hasColumn('preschool_attendance_sessions', 'session_code')) $table->string('session_code', 64)->nullable()->index();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'preschool_schedule_entry_id')) $table->foreignId('preschool_schedule_entry_id')->nullable()->constrained('preschool_schedule_entries')->nullOnDelete();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'teacher_user_id')) $table->string('teacher_user_id', 50)->nullable()->index();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'opens_at')) $table->dateTime('opens_at')->nullable()->index();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'closes_at')) $table->dateTime('closes_at')->nullable()->index();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'title')) $table->string('title')->nullable();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'source_occurrence_key')) $table->string('source_occurrence_key', 191)->nullable()->index();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'created_by_user_id')) $table->string('created_by_user_id', 50)->nullable()->index();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'updated_by_user_id')) $table->string('updated_by_user_id', 50)->nullable()->index();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'opened_by_user_id')) $table->string('opened_by_user_id', 50)->nullable()->index();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'closed_by_user_id')) $table->string('closed_by_user_id', 50)->nullable()->index();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'locked_by_user_id')) $table->string('locked_by_user_id', 50)->nullable()->index();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'cancelled_by_user_id')) $table->string('cancelled_by_user_id', 50)->nullable()->index();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'last_reopened_by_user_id')) $table->string('last_reopened_by_user_id', 50)->nullable()->index();
            if (! Schema::hasColumn('preschool_attendance_sessions', 'last_reopened_at')) $table->timestamp('last_reopened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('preschool_attendance_sessions', function (Blueprint $table): void {
            $table->dropForeign(['preschool_schedule_entry_id']);
            $table->dropColumn([
                'session_code', 'preschool_schedule_entry_id', 'teacher_user_id', 'opens_at', 'closes_at',
                'title', 'source_occurrence_key', 'created_by_user_id', 'updated_by_user_id',
                'opened_by_user_id', 'closed_by_user_id', 'locked_by_user_id', 'cancelled_by_user_id',
                'last_reopened_by_user_id', 'last_reopened_at',
            ]);
        });
    }
};
