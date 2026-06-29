<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Export governance keeps immutable metadata only so historical downloads
     * can be reviewed without storing mutable report blobs.
     */
    public function up(): void
    {
        if (! Schema::hasTable('preschool_report_export_records')) {
            Schema::create('preschool_report_export_records', function (Blueprint $table): void {
                $table->id();
                $table->string('actor_user_id', 16)->nullable();
                $table->string('actor_role', 64)->nullable();
                $table->string('export_type', 64);
                $table->string('export_format', 32);
                $table->string('export_source', 32);
                $table->foreignId('academic_year_id')->nullable()->constrained('preschool_academic_years')->nullOnDelete();
                $table->foreignId('term_id')->nullable()->constrained('preschool_terms')->nullOnDelete();
                $table->foreignId('report_period_id')->nullable()->constrained('preschool_report_periods')->nullOnDelete();
                $table->json('filters')->nullable();
                $table->json('snapshot_ids')->nullable();
                $table->unsignedInteger('record_count')->nullable();
                $table->string('file_name')->nullable();
                $table->string('checksum', 128)->nullable();
                $table->string('export_reason')->nullable();
                $table->json('request_context')->nullable();
                $table->timestamp('exported_at')->nullable();
                $table->timestamps();

                $table->index('actor_user_id', 'preschool_report_export_records_actor_user_id_index');
                $table->index(['export_type', 'export_format']);
                $table->index(['export_source', 'exported_at']);
                $table->index(['academic_year_id', 'term_id', 'report_period_id'], 'preschool_report_export_context_index');

                $table->foreign('actor_user_id', 'preschool_report_export_records_actor_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });

            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (Schema::hasColumn('preschool_report_export_records', 'actor_user_id')) {
            DB::statement('ALTER TABLE preschool_report_export_records MODIFY actor_user_id VARCHAR(16) NULL');
        }

        $hasActorIndex = collect(DB::select(
            "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'preschool_report_export_records' AND index_name = 'preschool_report_export_records_actor_user_id_index' LIMIT 1"
        ))->isNotEmpty();

        if (! $hasActorIndex) {
            DB::statement('CREATE INDEX preschool_report_export_records_actor_user_id_index ON preschool_report_export_records (actor_user_id)');
        }

        $hasActorForeignKey = collect(DB::select(
            "SELECT 1 FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'preschool_report_export_records' AND constraint_name = 'preschool_report_export_records_actor_user_id_foreign' LIMIT 1"
        ))->isNotEmpty();

        if (! $hasActorForeignKey) {
            DB::statement('ALTER TABLE preschool_report_export_records ADD CONSTRAINT preschool_report_export_records_actor_user_id_foreign FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_report_export_records');
    }
};
