<?php

namespace App\Console\Commands;

use App\Services\PreschoolNotificationRuleService;
use Illuminate\Console\Command;

class PreschoolRunDailyAutomationChecks extends Command
{
    protected $signature = 'preschool:automation-daily-checks';

    protected $description = 'Run conservative Preschool notification and automation checks.';

    public function handle(PreschoolNotificationRuleService $service): int
    {
        $result = $service->runDailyChecks();

        $this->info('Preschool daily automation checks completed.');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
