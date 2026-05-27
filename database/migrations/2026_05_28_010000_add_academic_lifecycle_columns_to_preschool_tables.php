<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preschool_class_students', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_class_students', 'academic_year_id')) {
                $table->foreignId('academic_year_id')->nullable()->after('term_label')->constrained('preschool_academic_years')->nullOnDelete()->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('preschool_class_students', 'term_id')) {
                $table->foreignId('term_id')->nullable()->after('academic_year_id')->constrained('preschool_terms')->nullOnDelete()->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('preschool_class_students', 'enrollment_status')) {
                $table->enum('enrollment_status', ['active', 'inactive', 'transferred', 'completed', 'graduated', 'promoted', 'archived'])->default('active')->after('term_id');
            }

            if (! Schema::hasColumn('preschool_class_students', 'enrollment_started_at')) {
                $table->timestamp('enrollment_started_at')->nullable()->after('enrollment_status');
            }

            if (! Schema::hasColumn('preschool_class_students', 'enrollment_ended_at')) {
                $table->timestamp('enrollment_ended_at')->nullable()->after('enrollment_started_at');
            }
        });

        Schema::table('preschool_class_teacher_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_class_teacher_assignments', 'academic_year_id')) {
                $table->foreignId('academic_year_id')->nullable()->after('academic_year')->constrained('preschool_academic_years')->nullOnDelete()->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('preschool_class_teacher_assignments', 'term_id')) {
                $table->foreignId('term_id')->nullable()->after('academic_year_id')->constrained('preschool_terms')->nullOnDelete()->cascadeOnUpdate();
            }
        });

        Schema::table('preschool_attendance_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_attendance_records', 'academic_year_id')) {
                $table->foreignId('academic_year_id')->nullable()->after('note')->constrained('preschool_academic_years')->nullOnDelete()->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('preschool_attendance_records', 'term_id')) {
                $table->foreignId('term_id')->nullable()->after('academic_year_id')->constrained('preschool_terms')->nullOnDelete()->cascadeOnUpdate();
            }
        });

        Schema::table('preschool_schedule_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_schedule_entries', 'academic_year_id')) {
                $table->foreignId('academic_year_id')->nullable()->after('effective_until')->constrained('preschool_academic_years')->nullOnDelete()->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('preschool_schedule_entries', 'term_id')) {
                $table->foreignId('term_id')->nullable()->after('academic_year_id')->constrained('preschool_terms')->nullOnDelete()->cascadeOnUpdate();
            }
        });

        Schema::table('preschool_student_assessments', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_student_assessments', 'academic_year_id')) {
                $table->foreignId('academic_year_id')->nullable()->after('period_label')->constrained('preschool_academic_years')->nullOnDelete()->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('preschool_student_assessments', 'term_id')) {
                $table->foreignId('term_id')->nullable()->after('academic_year_id')->constrained('preschool_terms')->nullOnDelete()->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        Schema::table('preschool_student_assessments', function (Blueprint $table): void {
            if (Schema::hasColumn('preschool_student_assessments', 'term_id')) {
                $table->dropConstrainedForeignId('term_id');
            }

            if (Schema::hasColumn('preschool_student_assessments', 'academic_year_id')) {
                $table->dropConstrainedForeignId('academic_year_id');
            }
        });

        Schema::table('preschool_schedule_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('preschool_schedule_entries', 'term_id')) {
                $table->dropConstrainedForeignId('term_id');
            }

            if (Schema::hasColumn('preschool_schedule_entries', 'academic_year_id')) {
                $table->dropConstrainedForeignId('academic_year_id');
            }
        });

        Schema::table('preschool_attendance_records', function (Blueprint $table): void {
            if (Schema::hasColumn('preschool_attendance_records', 'term_id')) {
                $table->dropConstrainedForeignId('term_id');
            }

            if (Schema::hasColumn('preschool_attendance_records', 'academic_year_id')) {
                $table->dropConstrainedForeignId('academic_year_id');
            }
        });

        Schema::table('preschool_class_teacher_assignments', function (Blueprint $table): void {
            if (Schema::hasColumn('preschool_class_teacher_assignments', 'term_id')) {
                $table->dropConstrainedForeignId('term_id');
            }

            if (Schema::hasColumn('preschool_class_teacher_assignments', 'academic_year_id')) {
                $table->dropConstrainedForeignId('academic_year_id');
            }
        });

        Schema::table('preschool_class_students', function (Blueprint $table): void {
            if (Schema::hasColumn('preschool_class_students', 'enrollment_ended_at')) {
                $table->dropColumn('enrollment_ended_at');
            }

            if (Schema::hasColumn('preschool_class_students', 'enrollment_started_at')) {
                $table->dropColumn('enrollment_started_at');
            }

            if (Schema::hasColumn('preschool_class_students', 'enrollment_status')) {
                $table->dropColumn('enrollment_status');
            }

            if (Schema::hasColumn('preschool_class_students', 'term_id')) {
                $table->dropConstrainedForeignId('term_id');
            }

            if (Schema::hasColumn('preschool_class_students', 'academic_year_id')) {
                $table->dropConstrainedForeignId('academic_year_id');
            }
        });

        Schema::dropIfExists('preschool_terms');
        Schema::dropIfExists('preschool_academic_years');
    }
};
