<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsam_question_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();        // 'short_text', 'radio', etc.
            $table->string('display_name', 255);
            $table->string('display_name_kh', 255)->nullable();
            $table->string('icon', 100)->nullable();
            $table->boolean('has_options')->default(false);   // radio/checkbox/dropdown/rubric
            $table->boolean('has_scoring')->default(false);
            $table->json('config_schema')->nullable();         // describes valid config keys for this type
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
        });

        // Seed the 13 supported question types
        DB::table('dsam_question_types')->insert([
            ['name' => 'short_text',   'display_name' => 'Short Text',    'display_name_kh' => 'អត្ថបទខ្លី',         'icon' => 'text',        'has_options' => false, 'has_scoring' => false, 'sort_order' => 1,  'config_schema' => null, 'is_active' => true],
            ['name' => 'long_text',    'display_name' => 'Long Text',     'display_name_kh' => 'អត្ថបទវែង',         'icon' => 'textarea',    'has_options' => false, 'has_scoring' => false, 'sort_order' => 2,  'config_schema' => null, 'is_active' => true],
            ['name' => 'number',       'display_name' => 'Number',        'display_name_kh' => 'ចំនួន',              'icon' => 'hash',        'has_options' => false, 'has_scoring' => true,  'sort_order' => 3,  'config_schema' => '{"min":null,"max":null,"integer_only":false}', 'is_active' => true],
            ['name' => 'date',         'display_name' => 'Date',          'display_name_kh' => 'កាលបរិច្ឆេទ',       'icon' => 'calendar',    'has_options' => false, 'has_scoring' => false, 'sort_order' => 4,  'config_schema' => null, 'is_active' => true],
            ['name' => 'radio',        'display_name' => 'Radio Button',  'display_name_kh' => 'ជម្រើសតែមួយ',       'icon' => 'radio',       'has_options' => true,  'has_scoring' => true,  'sort_order' => 5,  'config_schema' => null, 'is_active' => true],
            ['name' => 'checkbox',     'display_name' => 'Checkbox',      'display_name_kh' => 'ប្រអប់ធីក',          'icon' => 'checkbox',    'has_options' => true,  'has_scoring' => true,  'sort_order' => 6,  'config_schema' => '{"min_select":null,"max_select":null}', 'is_active' => true],
            ['name' => 'dropdown',     'display_name' => 'Dropdown',      'display_name_kh' => 'បញ្ជីទម្លាក់ចុះ',   'icon' => 'chevron',     'has_options' => true,  'has_scoring' => true,  'sort_order' => 7,  'config_schema' => null, 'is_active' => true],
            ['name' => 'rating_scale', 'display_name' => 'Rating Scale',  'display_name_kh' => 'មាត្រដ្ឋានវាយតម្លៃ', 'icon' => 'star',        'has_options' => false, 'has_scoring' => true,  'sort_order' => 8,  'config_schema' => '{"min":1,"max":5,"min_label":null,"max_label":null}', 'is_active' => true],
            ['name' => 'table_grid',   'display_name' => 'Table / Grid',  'display_name_kh' => 'តារាង',              'icon' => 'table',       'has_options' => false, 'has_scoring' => false, 'sort_order' => 9,  'config_schema' => '{"columns":[],"rows":[],"allow_add_rows":false}', 'is_active' => true],
            ['name' => 'file_upload',  'display_name' => 'File Upload',   'display_name_kh' => 'ផ្ទុកឯកសារ',        'icon' => 'upload',      'has_options' => false, 'has_scoring' => false, 'sort_order' => 10, 'config_schema' => '{"accept":["image/*","application/pdf"],"max_mb":10}', 'is_active' => true],
            ['name' => 'signature',    'display_name' => 'Signature',     'display_name_kh' => 'ហត្ថលេខា',          'icon' => 'pen',         'has_options' => false, 'has_scoring' => false, 'sort_order' => 11, 'config_schema' => null, 'is_active' => true],
            ['name' => 'score_rubric', 'display_name' => 'Score Rubric',  'display_name_kh' => 'លក្ខណៈវិនិច្ឆ័យ',  'icon' => 'rubric',      'has_options' => true,  'has_scoring' => true,  'sort_order' => 12, 'config_schema' => null, 'is_active' => true],
            ['name' => 'conditional',  'display_name' => 'Conditional',   'display_name_kh' => 'មានលក្ខខណ្ឌ',       'icon' => 'branch',      'has_options' => false, 'has_scoring' => false, 'sort_order' => 13, 'config_schema' => null, 'is_active' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('dsam_question_types');
    }
};
