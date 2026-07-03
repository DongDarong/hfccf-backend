<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasIndex(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return DB::selectOne(
                'SELECT name FROM sqlite_master WHERE type = ? AND tbl_name = ? AND name = ? LIMIT 1',
                ['index', $table, $index]
            ) !== null;
        }

        $database = DB::connection()->getDatabaseName();

        return DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$database, $table, $index]
        )->aggregate > 0;
    }

    public function up(): void
    {
        Schema::table('preschool_enrollment_applications', function (Blueprint $table): void {
            $table->string('reviewed_by_user_id', 16)->nullable()->change();
            $table->string('approved_by_user_id', 16)->nullable()->change();
            $table->string('enrolled_by_user_id', 16)->nullable()->change();
            $table->string('created_by_user_id', 16)->nullable()->change();
            $table->string('updated_by_user_id', 16)->nullable()->change();

            $table->foreign('requested_academic_year_id', 'pea_req_ay_fk')
                ->references('id')
                ->on('preschool_academic_years')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('requested_term_id', 'pea_req_term_fk')
                ->references('id')
                ->on('preschool_terms')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('preferred_class_id', 'pea_pref_class_fk')
                ->references('id')
                ->on('preschool_classes')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('enrolled_student_id', 'pea_enrolled_student_fk')
                ->references('id')
                ->on('preschool_students')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('reviewed_by_user_id', 'pea_reviewed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('approved_by_user_id', 'pea_approved_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('enrolled_by_user_id', 'pea_enrolled_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('created_by_user_id', 'pea_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('updated_by_user_id', 'pea_updated_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('preschool_enrollment_decision_logs', function (Blueprint $table): void {
            $table->string('actor_user_id', 16)->nullable()->change();
            $table->foreign('actor_user_id', 'pedl_actor_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('preschool_class_teacher_assignments', function (Blueprint $table): void {
            $table->foreign('teacher_user_id', 'pcta_teacher_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('preschool_guardian_governance_issues', function (Blueprint $table): void {
            $table->string('assigned_to_user_id', 16)->nullable()->change();

            $table->foreign('student_id', 'pghi_student_fk')
                ->references('id')
                ->on('preschool_students')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('guardian_id', 'pghi_guardian_fk')
                ->references('id')
                ->on('preschool_guardians')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('relationship_id', 'pghi_relationship_fk')
                ->references('id')
                ->on('preschool_student_guardians')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('assigned_to_user_id', 'pghi_assigned_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('preschool_guardian_remediation_logs', function (Blueprint $table): void {
            $table->string('performed_by_user_id', 16)->nullable()->change();

            $table->foreign('student_id', 'pgrl_student_fk')
                ->references('id')
                ->on('preschool_students')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('guardian_id', 'pgrl_guardian_fk')
                ->references('id')
                ->on('preschool_guardians')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('related_guardian_id', 'pgrl_related_guardian_fk')
                ->references('id')
                ->on('preschool_guardians')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('relationship_id', 'pgrl_relationship_fk')
                ->references('id')
                ->on('preschool_student_guardians')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('performed_by_user_id', 'pgrl_performed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('preschool_student_medical_profiles', function (Blueprint $table): void {
            $table->string('created_by_user_id', 16)->nullable()->change();
            $table->string('updated_by_user_id', 16)->nullable()->change();

            $table->foreign('student_id', 'psmp_student_fk')
                ->references('id')
                ->on('preschool_students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('created_by_user_id', 'psmp_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('updated_by_user_id', 'psmp_updated_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        if (! $this->hasIndex('preschool_guardian_communications', 'preschool_guardian_communications_source_index')) {
            Schema::table('preschool_guardian_communications', function (Blueprint $table): void {
                $table->index(['source_type', 'source_id'], 'preschool_guardian_communications_source_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('preschool_guardian_communications', 'preschool_guardian_communications_source_index')) {
            Schema::table('preschool_guardian_communications', function (Blueprint $table): void {
                $table->dropIndex('preschool_guardian_communications_source_index');
            });
        }

        Schema::table('preschool_student_medical_profiles', function (Blueprint $table): void {
            $table->dropForeign('psmp_updated_by_fk');
            $table->dropForeign('psmp_created_by_fk');
            $table->dropForeign('psmp_student_fk');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->change();
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->change();
        });

        Schema::table('preschool_guardian_remediation_logs', function (Blueprint $table): void {
            $table->dropForeign('pgrl_performed_by_fk');
            $table->dropForeign('pgrl_relationship_fk');
            $table->dropForeign('pgrl_related_guardian_fk');
            $table->dropForeign('pgrl_guardian_fk');
            $table->dropForeign('pgrl_student_fk');
            $table->unsignedBigInteger('performed_by_user_id')->nullable()->change();
        });

        Schema::table('preschool_guardian_governance_issues', function (Blueprint $table): void {
            $table->dropForeign('pghi_assigned_user_fk');
            $table->dropForeign('pghi_relationship_fk');
            $table->dropForeign('pghi_guardian_fk');
            $table->dropForeign('pghi_student_fk');
            $table->unsignedBigInteger('assigned_to_user_id')->nullable()->change();
        });

        Schema::table('preschool_class_teacher_assignments', function (Blueprint $table): void {
            $table->dropForeign('pcta_teacher_user_fk');
        });

        Schema::table('preschool_enrollment_decision_logs', function (Blueprint $table): void {
            $table->dropForeign('pedl_actor_user_fk');
            $table->unsignedBigInteger('actor_user_id')->nullable()->change();
        });

        Schema::table('preschool_enrollment_applications', function (Blueprint $table): void {
            $table->dropForeign('pea_updated_by_fk');
            $table->dropForeign('pea_created_by_fk');
            $table->dropForeign('pea_enrolled_by_fk');
            $table->dropForeign('pea_approved_by_fk');
            $table->dropForeign('pea_reviewed_by_fk');
            $table->dropForeign('pea_enrolled_student_fk');
            $table->dropForeign('pea_pref_class_fk');
            $table->dropForeign('pea_req_term_fk');
            $table->dropForeign('pea_req_ay_fk');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->change();
            $table->unsignedBigInteger('approved_by_user_id')->nullable()->change();
            $table->unsignedBigInteger('enrolled_by_user_id')->nullable()->change();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->change();
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->change();
        });
    }
};
