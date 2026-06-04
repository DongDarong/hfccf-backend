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
            $table->string('public_id', 32)->nullable()->unique()->after('id');
        });

        $students = DB::table('preschool_students')
            ->orderBy('id')
            ->get(['id']);

        $publicSequence = 1;
        $studentCodeSequence = 1;

        foreach ($students as $student) {
            DB::table('preschool_students')
                ->where('id', $student->id)
                ->update([
                    'public_id' => sprintf('STU-HFCCF-%04d', $publicSequence++),
                    'student_code' => sprintf('PS-%05d', $studentCodeSequence++),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('preschool_students', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
