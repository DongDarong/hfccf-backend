<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\EnterpriseGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernanceRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_risk_dashboard_returns_expected_shape(): void
    {
        $service = $this->app->make(EnterpriseGovernanceService::class);

        $this->assertSame('Low', $service->calculateStudentRisk(['average_score' => 100])['level']);
        $this->assertArrayHasKey('components', $service->calculateOperationalRisk());
    }

    private function makeUser(string $roleCode): User
    {
        return User::query()->create([
            'name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'email' => uniqid($roleCode.'_', true).'@example.test',
            'password' => 'password',
            'role_code' => $roleCode,
        ]);
    }
}
