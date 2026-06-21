<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preschool_academic_years', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_academic_years', 'description')) {
                $table->text('description')->nullable()->after('label');
            }

            if (! Schema::hasColumn('preschool_academic_years', 'created_by')) {
                $table->string('created_by', 16)->nullable()->after('description');
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('preschool_academic_years', 'updated_by')) {
                $table->string('updated_by', 16)->nullable()->after('created_by');
                $table->foreign('updated_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('preschool_academic_years', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
                $table->index('deleted_at', 'preschool_academic_years_deleted_at_index');
            }
        });

        Schema::table('preschool_terms', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_terms', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (! Schema::hasColumn('preschool_terms', 'created_by')) {
                $table->string('created_by', 16)->nullable()->after('description');
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('preschool_terms', 'updated_by')) {
                $table->string('updated_by', 16)->nullable()->after('created_by');
                $table->foreign('updated_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('preschool_terms', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
                $table->index('deleted_at', 'preschool_terms_deleted_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('preschool_terms', function (Blueprint $table): void {
            if (Schema::hasColumn('preschool_terms', 'deleted_at')) {
                $table->dropIndex('preschool_terms_deleted_at_index');
                $table->dropColumn('deleted_at');
            }

            if (Schema::hasColumn('preschool_terms', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }

            if (Schema::hasColumn('preschool_terms', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('preschool_terms', 'description')) {
                $table->dropColumn('description');
            }
        });

        Schema::table('preschool_academic_years', function (Blueprint $table): void {
            if (Schema::hasColumn('preschool_academic_years', 'deleted_at')) {
                $table->dropIndex('preschool_academic_years_deleted_at_index');
                $table->dropColumn('deleted_at');
            }

            if (Schema::hasColumn('preschool_academic_years', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }

            if (Schema::hasColumn('preschool_academic_years', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('preschool_academic_years', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
