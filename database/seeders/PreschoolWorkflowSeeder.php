<?php

namespace Database\Seeders;

use App\Services\PreschoolWorkflowDefinitionService;
use Illuminate\Database\Seeder;

class PreschoolWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        app(PreschoolWorkflowDefinitionService::class)->seedDefaults();
    }
}
