<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preschool_attendance_sessions', function (Blueprint $table): void {
            $table->string('session_code', 64)->nullable()->after('id')->index();
            $table->foreignId('preschool_schedule_entry_id')->nullable()->after('preschool_class_id')->constrained('preschool_schedule_entries')->nullOnDelete();
            $table->string('teacher_user_id', 50)->nullable()->after('preschool_schedule_entry_id')->index();
            $table->dateTime('opens_at')->nullable()->after('end_time')->index();
            $table->dateTime('closes_at')->nullable()->after('opens_at')->index();
            $table->string('title')->nullable()->after('status');
            $table->string('source_occurrence_key', 191)->nullable()->after('session_key')->index();
            $table->string('created_by_user_id', 50)->nullable()->after('created_by')->index();
            $table->string('updated_by_user_id', 50)->nullable()->after('created_by_user_id')->index();
            $table->string('opened_by_user_id', 50)->nullable()->after('opened_by')->index();
            $table->string('closed_by_user_id', 50)->nullable()->after('closed_by')->index();
            $table->string('locked_by_user_id', 50)->nullable()->after('locked_by')->index();
            $table->string('cancelled_by_user_id', 50)->nullable()->after('cancelled_by')->index();
            $table->string('last_reopened_by_user_id', 50)->nullable()->after('reopened_by')->index();
            $table->timestamp('last_reopened_at')->nullable()->after('reopened_at');
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
