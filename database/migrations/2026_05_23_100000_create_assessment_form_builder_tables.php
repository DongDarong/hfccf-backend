<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_question_types', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label', 128);
            $table->string('label_kh', 128)->nullable();
            $table->string('renderer', 128)->nullable();
            $table->boolean('has_options')->default(false);
            $table->boolean('has_scoring')->default(false);
            $table->boolean('has_matrix')->default(false);
            $table->boolean('is_file')->default(false);
            $table->json('settings_schema')->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('assessment_question_types')->insert([
            ['key' => 'short_text',      'label' => 'Short Text',       'label_kh' => 'អត្ថបទខ្លី',         'renderer' => 'short-text',       'has_options' => false, 'has_scoring' => false, 'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 10,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'long_text',       'label' => 'Long Text',        'label_kh' => 'អត្ថបទវែង',          'renderer' => 'long-text',        'has_options' => false, 'has_scoring' => false, 'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 20,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'number',          'label' => 'Number',           'label_kh' => 'លេខ',                'renderer' => 'number',           'has_options' => false, 'has_scoring' => true,  'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 30,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'date',            'label' => 'Date',             'label_kh' => 'កាលបរិច្ឆេទ',       'renderer' => 'date',             'has_options' => false, 'has_scoring' => false, 'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 40,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'radio',           'label' => 'Radio',            'label_kh' => 'រ៉ាដ្យូ',            'renderer' => 'radio',            'has_options' => true,  'has_scoring' => true,  'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 50,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'checkbox',        'label' => 'Checkbox',         'label_kh' => 'ប្រអប់ធីក',          'renderer' => 'checkbox',         'has_options' => true,  'has_scoring' => true,  'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 60,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'dropdown',        'label' => 'Dropdown',         'label_kh' => 'បញ្ជីជ្រើសរើស',     'renderer' => 'dropdown',         'has_options' => true,  'has_scoring' => true,  'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 70,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'multi_select',    'label' => 'Multi Select',     'label_kh' => 'ជ្រើសរើសច្រើន',     'renderer' => 'multi-select',     'has_options' => true,  'has_scoring' => true,  'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 80,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'rating_scale',    'label' => 'Rating Scale',     'label_kh' => 'មាត្រដ្ឋានវាយតម្លៃ', 'renderer' => 'rating-scale',     'has_options' => true,  'has_scoring' => true,  'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 90,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'score_rubric',    'label' => 'Score Rubric',     'label_kh' => 'រូបិក​ពិន្ទុ',       'renderer' => 'score-rubric',     'has_options' => true,  'has_scoring' => true,  'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'matrix',          'label' => 'Matrix',           'label_kh' => 'ម៉ាទ្រីស',           'renderer' => 'matrix',           'has_options' => true,  'has_scoring' => true,  'has_matrix' => true,  'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 110, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'dynamic_table',   'label' => 'Dynamic Table',    'label_kh' => 'តារាងថាមវន្ត',       'renderer' => 'dynamic-table',    'has_options' => false, 'has_scoring' => false, 'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 120, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'file_upload',     'label' => 'File Upload',      'label_kh' => 'បញ្ចូលឯកសារ',       'renderer' => 'file-upload',      'has_options' => false, 'has_scoring' => false, 'has_matrix' => false, 'is_file' => true,  'settings_schema' => null, 'is_active' => true, 'sort_order' => 130, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'image_upload',    'label' => 'Image Upload',     'label_kh' => 'បញ្ចូលរូបភាព',      'renderer' => 'image-upload',     'has_options' => false, 'has_scoring' => false, 'has_matrix' => false, 'is_file' => true,  'settings_schema' => null, 'is_active' => true, 'sort_order' => 140, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'signature',       'label' => 'Signature',        'label_kh' => 'ហត្ថលេខា',          'renderer' => 'signature',        'has_options' => false, 'has_scoring' => false, 'has_matrix' => false, 'is_file' => true,  'settings_schema' => null, 'is_active' => true, 'sort_order' => 150, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'gps_location',    'label' => 'GPS Location',     'label_kh' => 'ទីតាំង GPS',         'renderer' => 'gps-location',     'has_options' => false, 'has_scoring' => false, 'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 160, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'calculated',      'label' => 'Calculated',       'label_kh' => 'គណនា',               'renderer' => 'calculated',       'has_options' => false, 'has_scoring' => true,  'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 170, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'conditional',     'label' => 'Conditional',      'label_kh' => 'លក្ខខណ្ឌ',           'renderer' => 'conditional',      'has_options' => false, 'has_scoring' => false, 'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 180, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'repeating_group', 'label' => 'Repeating Group',  'label_kh' => 'ក្រុមដដែលៗ',         'renderer' => 'repeating-group',  'has_options' => false, 'has_scoring' => false, 'has_matrix' => false, 'is_file' => false, 'settings_schema' => null, 'is_active' => true, 'sort_order' => 190, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('assessment_form_templates', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('code', 64)->unique();
            $table->string('name', 255);
            $table->string('name_kh', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('description_kh')->nullable();
            $table->string('category', 64)->nullable();
            $table->string('module', 64)->default('preschool');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_locked')->default(false);
            $table->json('settings')->nullable();
            $table->string('created_by', 16);
            $table->string('updated_by', 16)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['module', 'status'], 'assessment_form_templates_module_status_index');
            $table->index('category', 'assessment_form_templates_category_index');
            $table->index('status', 'assessment_form_templates_status_index');

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

        Schema::create('assessment_form_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')
                ->constrained('assessment_form_templates')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->smallInteger('version_number')->default(1);
            $table->string('label', 64)->nullable();
            $table->longText('snapshot');
            $table->text('change_summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('published_by', 16)->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->unique(['template_id', 'version_number'], 'assessment_form_versions_template_version_unique');
            $table->index(['template_id', 'is_current'], 'assessment_form_versions_template_current_index');

            $table->foreign('published_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('assessment_form_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')
                ->constrained('assessment_form_templates')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 64)->nullable();
            $table->string('title', 255);
            $table->string('title_kh', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('description_kh')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_repeatable')->default(false);
            $table->tinyInteger('max_repeats')->nullable();
            $table->boolean('print_visible')->default(true);
            $table->decimal('scoring_weight', 5, 2)->default(1.00);
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('template_id', 'assessment_form_sections_template_id_index');
            $table->index('parent_id', 'assessment_form_sections_parent_id_index');

            $table->foreign('parent_id')
                ->references('id')
                ->on('assessment_form_sections')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('assessment_questions', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('section_id')
                ->constrained('assessment_form_sections')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('template_id')
                ->constrained('assessment_form_templates')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('question_type_id')
                ->constrained('assessment_question_types')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedBigInteger('parent_question_id')->nullable();
            $table->string('code', 64)->nullable();
            $table->text('label');
            $table->text('label_kh')->nullable();
            $table->text('help_text')->nullable();
            $table->text('help_text_kh')->nullable();
            $table->string('placeholder', 255)->nullable();
            $table->string('placeholder_kh', 255)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_scored')->default(false);
            $table->decimal('max_score', 8, 2)->nullable();
            $table->decimal('scoring_weight', 5, 2)->default(1.00);
            $table->boolean('print_visible')->default(true);
            $table->json('validation_rules')->nullable();
            $table->json('conditional_logic')->nullable();
            $table->string('calculation_formula', 512)->nullable();
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['section_id', 'sort_order'], 'assessment_questions_section_sort_index');
            $table->index('template_id', 'assessment_questions_template_id_index');
            $table->index('code', 'assessment_questions_code_index');
            $table->index('parent_question_id', 'assessment_questions_parent_question_id_index');

            $table->foreign('parent_question_id')
                ->references('id')
                ->on('assessment_questions')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('assessment_question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')
                ->constrained('assessment_questions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('label', 512);
            $table->string('label_kh', 512)->nullable();
            $table->string('value', 255);
            $table->decimal('score_value', 8, 2)->default(0);
            $table->string('risk_tag', 32)->nullable();
            $table->string('color_code', 7)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_other')->default(false);
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('question_id', 'assessment_question_options_question_id_index');
        });

        Schema::create('assessment_matrix_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')
                ->constrained('assessment_questions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('label', 255);
            $table->string('label_kh', 255)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('question_id', 'assessment_matrix_rows_question_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_matrix_rows');
        Schema::dropIfExists('assessment_question_options');
        Schema::dropIfExists('assessment_questions');
        Schema::dropIfExists('assessment_form_sections');
        Schema::dropIfExists('assessment_form_versions');
        Schema::dropIfExists('assessment_form_templates');
        Schema::dropIfExists('assessment_question_types');
    }
};
