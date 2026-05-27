<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preschool_class_students', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_class_students', 'academic_year')) {
                $table->string('academic_year', 32)->nullable()->after('enrolled_at');
            }

            if (! Schema::hasColumn('preschool_class_students', 'term_label')) {
                $table->string('term_label', 191)->nullable()->after('academic_year');
            }
        });

        Schema::table('preschool_class_teacher_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_class_teacher_assignments', 'academic_year')) {
                $table->string('academic_year', 32)->nullable()->after('assigned_at');
            }

            if (! Schema::hasColumn('preschool_class_teacher_assignments', 'term_label')) {
                $table->string('term_label', 191)->nullable()->after('academic_year');
            }
        });
    }

    public function down(): void
    {
        Schema::table('preschool_class_teacher_assignments', function (Blueprint $table): void {
            if (Schema::hasColumn('preschool_class_teacher_assignments', 'term_label')) {
                $table->dropColumn('term_label');
            }

            if (Schema::hasColumn('preschool_class_teacher_assignments', 'academic_year')) {
                $table->dropColumn('academic_year');
            }
        });

        Schema::table('preschool_class_students', function (Blueprint $table): void {
            if (Schema::hasColumn('preschool_class_students', 'term_label')) {
                $table->dropColumn('term_label');
            }

            if (Schema::hasColumn('preschool_class_students', 'academic_year')) {
                $table->dropColumn('academic_year');
            }
        });
    }
};
