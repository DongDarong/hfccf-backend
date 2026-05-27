<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_print_templates', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('form_template_id')
                ->constrained('assessment_form_templates')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('name', 255);
            $table->string('name_kh', 255)->nullable();
            $table->enum('format', ['pdf', 'excel', 'html'])->default('pdf');
            $table->enum('page_size', ['A4', 'Letter', 'A3'])->default('A4');
            $table->enum('orientation', ['portrait', 'landscape'])->default('portrait');
            $table->smallInteger('margin_top')->default(20);
            $table->smallInteger('margin_right')->default(20);
            $table->smallInteger('margin_bottom')->default(20);
            $table->smallInteger('margin_left')->default(20);
            $table->string('font_family', 64)->default('Khmer');
            $table->tinyInteger('font_size')->default(11);
            $table->mediumText('header_html')->nullable();
            $table->mediumText('footer_html')->nullable();
            $table->string('watermark_text', 128)->nullable();
            $table->boolean('show_logo')->default(true);
            $table->string('logo_path', 512)->nullable();
            $table->boolean('show_qr_code')->default(false);
            $table->boolean('show_watermark')->default(false);
            $table->longText('blocks');
            $table->text('styles')->nullable();
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->string('created_by', 16);
            $table->string('updated_by', 16)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('form_template_id', 'assessment_print_templates_form_template_id_index');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('assessment_export_logs', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('initiated_by', 16);
            $table->enum('export_type', ['pdf', 'excel', 'zip', 'html']);
            $table->enum('scope', ['single', 'batch', 'report'])->default('single');
            $table->json('submission_ids')->nullable();
            $table->unsignedBigInteger('print_template_id')->nullable();
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->string('file_path', 512)->nullable();
            $table->integer('file_size')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status', 'assessment_export_logs_status_index');
            $table->index('initiated_by', 'assessment_export_logs_initiated_by_index');
            $table->index('expires_at', 'assessment_export_logs_expires_at_index');

            $table->foreign('initiated_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('print_template_id')
                ->references('id')
                ->on('assessment_print_templates')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('assessment_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id', 16);
            $table->string('action', 64);
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_label', 255)->nullable();
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['entity_type', 'entity_id'], 'assessment_audit_logs_entity_type_entity_id_index');
            $table->index('user_id', 'assessment_audit_logs_user_id_index');
            $table->index('action', 'assessment_audit_logs_action_index');
            $table->index('created_at', 'assessment_audit_logs_created_at_index');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_audit_logs');
        Schema::dropIfExists('assessment_export_logs');
        Schema::dropIfExists('assessment_print_templates');
    }
};
