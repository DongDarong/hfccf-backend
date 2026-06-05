<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Dsam\FormSection;
use App\Models\Dsam\FormTemplate;
use App\Models\Dsam\QuestionType;
use App\Models\Organization;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\StudentHistory;
use App\Models\PreschoolStudent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DsamDevSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Organization ────────────────────────────────────────────────────
        $org = Organization::firstOrCreate(
            ['email' => 'admin@hfccf.org'],
            [
                'name'      => 'HFCCF',
                'name_kh'   => 'សង្គមការពារកុមារ HFCCF',
                'type'      => 'ngo',
                'province'  => 'Phnom Penh',
                'phone'     => '+855-23-000-000',
                'is_active' => true,
            ],
        );
        $this->command->info("Organization: {$org->name} (id={$org->id})");

        // ── 2. Wire existing users to org ──────────────────────────────────────
        DB::table('users')
            ->whereNull('organization_id')
            ->update(['organization_id' => $org->id]);
        $this->command->info('Users wired to organization.');

        // ── 3. Schools ─────────────────────────────────────────────────────────
        $school = School::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'HFCCF Preschool - Main Campus'],
            [
                'name_kh'        => 'សាលា HFCCF - ទីស្នាក់ការកណ្ដាល',
                'province'       => 'Phnom Penh',
                'district'       => 'Chamkar Mon',
                'principal_name' => 'Demo Principal',
                'phone'          => '+855-23-000-001',
                'is_active'      => true,
            ],
        );
        $this->command->info("School: {$school->name} (id={$school->id})");

        // ── 4. Academic years ──────────────────────────────────────────────────
        $prevYear = AcademicYear::firstOrCreate(
            ['organization_id' => $org->id, 'name' => '2024-2025'],
            ['start_date' => '2024-09-01', 'end_date' => '2025-06-30', 'is_current' => false],
        );

        $currentYear = AcademicYear::firstOrCreate(
            ['organization_id' => $org->id, 'name' => '2025-2026'],
            ['start_date' => '2025-09-01', 'end_date' => '2026-06-30', 'is_current' => false],
        );
        $currentYear->setCurrent();
        $this->command->info("Academic years seeded. Current: {$currentYear->name}");

        // ── 5. Student profiles & histories for existing preschool students ────
        $students = PreschoolStudent::limit(10)->get();
        foreach ($students as $student) {
            StudentProfile::firstOrCreate(
                ['student_id' => $student->id],
                [
                    'father_name'       => 'Demo Father',
                    'father_occupation' => 'Farmer',
                    'father_income'     => 250000,
                    'mother_name'       => 'Demo Mother',
                    'mother_occupation' => 'Market Vendor',
                    'mother_income'     => 200000,
                    'household_size'    => 5,
                    'monthly_income'    => 450000,
                    'income_sources'    => ['farming', 'market'],
                    'housing_type'      => 'owned',
                    'has_electricity'   => true,
                    'has_clean_water'   => true,
                    'has_toilet'        => false,
                    'distance_to_school'=> 1.5,
                    'transport_mode'    => 'walk',
                    'health_status'     => 'fair',
                ],
            );

            StudentHistory::firstOrCreate(
                ['student_id' => $student->id, 'academic_year_id' => $currentYear->id],
                [
                    'school_id'  => $school->id,
                    'grade'      => $student->classes()->latest()->first()?->level ?? 'K1',
                    'class_name' => $student->classes()->latest()->first()?->name ?? 'Class A',
                    'status'     => 'active',
                ],
            );
        }
        $this->command->info("Student profiles & histories seeded for {$students->count()} students.");

        // ── 6. Sample annual assessment form ──────────────────────────────────
        $existing = FormTemplate::where('organization_id', $org->id)
            ->where('name', 'Annual Student Assessment 2025-2026')
            ->first();

        if ($existing) {
            $this->command->info('Sample form template already exists — skipping.');
            return;
        }

        $form = FormTemplate::create([
            'organization_id'  => $org->id,
            'academic_year_id' => $currentYear->id,
            'name'             => 'Annual Student Assessment 2025-2026',
            'name_kh'          => 'ការវាយតម្លៃប្រចាំឆ្នាំ ២០២៥-២០២៦',
            'description'      => 'Comprehensive annual assessment covering family, economic, health, and education indicators.',
            'category'         => 'annual_assessment',
            'status'           => 'draft',
            'version_number'   => 1,
            'risk_config'      => [
                'invert_score' => true,
                'thresholds'   => [
                    ['level' => 'low',      'min' => 76, 'max' => 100, 'color' => '#16a34a', 'label' => 'Low Risk',      'label_kh' => 'ហានិភ័យទាប'],
                    ['level' => 'medium',   'min' => 51, 'max' => 75,  'color' => '#d97706', 'label' => 'Medium Risk',   'label_kh' => 'ហានិភ័យមធ្យម'],
                    ['level' => 'high',     'min' => 26, 'max' => 50,  'color' => '#ea580c', 'label' => 'High Risk',     'label_kh' => 'ហានិភ័យខ្ពស់'],
                    ['level' => 'critical', 'min' => 0,  'max' => 25,  'color' => '#dc2626', 'label' => 'Critical Risk', 'label_kh' => 'ហានិភ័យធ្ងន់ធ្ងរ'],
                ],
            ],
        ]);

        // Section 1 — Family Information
        $s1 = FormSection::create([
            'form_template_id' => $form->id,
            'title'            => 'Family Information',
            'title_kh'         => 'ព័ត៌មានគ្រួសារ',
            'order_index'      => 0,
            'scoring_weight'   => 0.30,
        ]);

        $radioType = QuestionType::where('name', 'radio')->first();
        $textType  = QuestionType::where('name', 'short_text')->first();
        $numType   = QuestionType::where('name', 'number')->first();

        // Q1 — guardian status
        $q1 = $s1->allQuestions()->create([
            'question_type_id' => $radioType->id,
            'label'            => 'What is the primary guardian situation?',
            'label_kh'         => 'តើស្ថានភាពអ្នកអាណាព្យាបាលចម្បងជាអ្វី?',
            'order_index'      => 0,
            'is_required'      => true,
            'is_scored'        => true,
            'max_score'        => 3,
        ]);
        $q1->options()->createMany([
            ['label' => 'Both parents present',    'label_kh' => 'ឪពុកម្តាយទាំងពីររស់នៅ', 'value' => 'both',     'score_value' => 3, 'order_index' => 0],
            ['label' => 'Single parent',           'label_kh' => 'មានឪពុក ឬ ម្តាយម្នាក់',   'value' => 'single',   'score_value' => 2, 'order_index' => 1],
            ['label' => 'Guardian (non-parent)',   'label_kh' => 'អ្នកអាណាព្យាបាល',          'value' => 'guardian', 'score_value' => 1, 'order_index' => 2],
            ['label' => 'No guardian / orphan',   'label_kh' => 'គ្មានអ្នកអាណាព្យាបាល',     'value' => 'orphan',   'score_value' => 0, 'order_index' => 3],
        ]);

        // Q2 — monthly income
        $s1->allQuestions()->create([
            'question_type_id' => $numType->id,
            'label'            => 'Estimated monthly household income (USD)',
            'label_kh'         => 'ប្រាក់ចំណូលគ្រួសារប្រចាំខែ (ដុល្លារ)',
            'order_index'      => 1,
            'is_required'      => true,
            'is_scored'        => false,
            'validation_rules' => ['min' => 0, 'max' => 10000],
        ]);

        // Section 2 — Housing & Living Conditions
        $s2 = FormSection::create([
            'form_template_id' => $form->id,
            'title'            => 'Housing & Living Conditions',
            'title_kh'         => 'លក្ខខណ្ឌលំនៅដ្ឋាន',
            'order_index'      => 1,
            'scoring_weight'   => 0.25,
        ]);

        $checkboxType = QuestionType::where('name', 'checkbox')->first();
        $q3 = $s2->allQuestions()->create([
            'question_type_id' => $checkboxType->id,
            'label'            => 'Which utilities does the household have?',
            'label_kh'         => 'គ្រួសារមានសេវាកម្មអ្វីខ្លះ?',
            'order_index'      => 0,
            'is_required'      => false,
            'is_scored'        => true,
            'max_score'        => 3,
            'scoring_config'   => ['type' => 'direct', 'formula' => 'sum'],
        ]);
        $q3->options()->createMany([
            ['label' => 'Electricity',  'label_kh' => 'អគ្គិសនី',    'value' => 'electricity', 'score_value' => 1, 'order_index' => 0],
            ['label' => 'Clean water',  'label_kh' => 'ទឹកស្អាត',    'value' => 'water',       'score_value' => 1, 'order_index' => 1],
            ['label' => 'Toilet',       'label_kh' => 'បន្ទប់ទឹក',   'value' => 'toilet',      'score_value' => 1, 'order_index' => 2],
        ]);

        // Section 3 — Education Status
        $s3 = FormSection::create([
            'form_template_id' => $form->id,
            'title'            => 'Education Status',
            'title_kh'         => 'ស្ថានភាពការសិក្សា',
            'order_index'      => 2,
            'scoring_weight'   => 0.25,
        ]);

        $ratingType = QuestionType::where('name', 'rating_scale')->first();
        $s3->allQuestions()->create([
            'question_type_id' => $ratingType->id,
            'label'            => 'How would you rate the child\'s school attendance regularity?',
            'label_kh'         => 'តើអ្នកវាយតម្លៃការចូលរៀនប្រចាំថ្ងៃរបស់សិស្សយ៉ាងដូចម្ដេច?',
            'order_index'      => 0,
            'is_required'      => true,
            'is_scored'        => true,
            'max_score'        => 5,
            'config'           => ['min' => 1, 'max' => 5, 'min_label' => 'Rarely', 'max_label' => 'Always'],
        ]);

        // Section 4 — Health & Nutrition
        $s4 = FormSection::create([
            'form_template_id' => $form->id,
            'title'            => 'Health & Nutrition',
            'title_kh'         => 'សុខភាព និង អាហារូបត្ថម្ភ',
            'order_index'      => 3,
            'scoring_weight'   => 0.20,
        ]);

        $s4->allQuestions()->create([
            'question_type_id' => $radioType->id,
            'label'            => 'What is the child\'s general health status?',
            'label_kh'         => 'តើស្ថានភាពសុខភាពទូទៅរបស់កុមារជាយ៉ាងណា?',
            'order_index'      => 0,
            'is_required'      => true,
            'is_scored'        => true,
            'max_score'        => 3,
        ])->options()->createMany([
            ['label' => 'Good',    'label_kh' => 'ល្អ',     'value' => 'good',    'score_value' => 3, 'order_index' => 0],
            ['label' => 'Fair',    'label_kh' => 'មធ្យម',  'value' => 'fair',    'score_value' => 2, 'order_index' => 1],
            ['label' => 'Poor',    'label_kh' => 'ខ្សោយ',  'value' => 'poor',    'score_value' => 1, 'order_index' => 2],
            ['label' => 'Critical','label_kh' => 'ធ្ងន់ធ្ងរ', 'value' => 'critical','score_value' => 0, 'order_index' => 3],
        ]);

        $this->command->info("Sample form template created (id={$form->id}, status=draft).");
        $this->command->info('Run `php artisan dsam:seed` or POST /api/dsam/forms/{id}/publish to activate it.');
    }
}
