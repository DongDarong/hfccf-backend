<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preschool_students', function (Blueprint $table): void {
            $table->string('student_type', 32)->default('paying')->after('status');
        });

        DB::table('preschool_students')
            ->whereNull('student_type')
            ->update(['student_type' => 'paying']);
    }

    public function down(): void
    {
        Schema::table('preschool_students', function (Blueprint $table): void {
            $table->dropColumn('student_type');
        });
    }
};