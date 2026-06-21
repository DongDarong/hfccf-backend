<?php

namespace Tests\Feature;

use App\Models\PreschoolHealthCheckCategory;
use App\Models\PreschoolHealthIncidentCategory;
use App\Models\PreschoolHealthSeverityLevel;
use App\Models\PreschoolHealthSetting;
use App\Models\PreschoolVaccinationCategory;
use App\Models\User;
use App\Support\PreschoolHealthConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolHealthConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_get_default_settings(): void
    {
        $user = $this->makeUser('superadmin');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/preschool/settings/health');

        $response->assertOk()
            ->assertJsonPath('data.settings.critical_alert_enabled', true)
            ->assertJsonPath('data.settings.overdue_vaccination_alert_days', 30)
            ->assertJsonPath('data.settings.medication_reminder_minutes_before', 30);
    }

    public function test_adminpreschool_can_update_settings(): void
    {
        $user = $this->makeUser('adminpreschool');
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/preschool/settings/health', [
            'critical_alert_enabled' => false,
            'guardian_notification_enabled' => true,
            'teacher_notification_enabled' => false,
            'admin_notification_enabled' => true,
            'medication_reminder_enabled' => true,
            'vaccination_reminder_enabled' => false,
            'overdue_vaccination_alert_days' => 21,
            'medication_reminder_minutes_before' => 45,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.settings.critical_alert_enabled', false)
            ->assertJsonPath('data.settings.overdue_vaccination_alert_days', 21);

        $this->assertDatabaseHas('preschool_health_settings', [
            'critical_alert_enabled' => 0,
            'overdue_vaccination_alert_days' => 21,
        ]);
    }

    public function test_teacher_preschool_is_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser('teacher-preschool'));

        $this->getJson('/api/preschool/settings/health')->assertForbidden();
        $this->putJson('/api/preschool/settings/health', $this->healthPayload())->assertForbidden();
    }

    public function test_unrelated_admin_is_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser('adminenglish'));

        $this->getJson('/api/preschool/settings/health')->assertForbidden();
    }

    public function test_severity_level_create_update_archive(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $created = $this->postJson('/api/preschool/settings/health/severity-levels', [
            'name' => 'Urgent',
            'code' => 'urgent',
            'priority' => 5,
            'color' => '#ff0000',
            'requires_acknowledgment' => true,
            'triggers_notification' => true,
            'is_active' => true,
            'sort_order' => 5,
        ])->assertCreated();

        $severityId = $created->json('data.severity.id');

        $this->putJson("/api/preschool/settings/health/severity-levels/{$severityId}", [
            'name' => 'Urgent Updated',
            'code' => 'urgent',
            'priority' => 6,
            'color' => '#cc0000',
            'requires_acknowledgment' => true,
            'triggers_notification' => true,
            'is_active' => true,
            'sort_order' => 6,
        ])->assertOk();

        $this->postJson("/api/preschool/settings/health/severity-levels/{$severityId}/archive")->assertOk();

        $this->assertSoftDeleted('preschool_health_severity_levels', [
            'id' => $severityId,
        ]);
    }

    public function test_incident_category_create_update_archive(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $created = $this->postJson('/api/preschool/settings/health/incident-categories', [
            'name' => 'Nosebleed',
            'code' => 'nosebleed',
            'description' => 'Minor incident',
            'default_severity_code' => 'medium',
            'is_active' => true,
            'sort_order' => 10,
        ])->assertCreated();

        $categoryId = $created->json('data.category.id');

        $this->putJson("/api/preschool/settings/health/incident-categories/{$categoryId}", [
            'name' => 'Nosebleed Updated',
            'code' => 'nosebleed',
            'description' => 'Updated incident',
            'default_severity_code' => 'high',
            'is_active' => true,
            'sort_order' => 11,
        ])->assertOk();

        $this->postJson("/api/preschool/settings/health/incident-categories/{$categoryId}/archive")->assertOk();

        $this->assertSoftDeleted('preschool_health_incident_categories', [
            'id' => $categoryId,
        ]);
    }

    public function test_vaccination_category_create_update_archive(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $created = $this->postJson('/api/preschool/settings/health/vaccination-categories', [
            'name' => 'COVID',
            'code' => 'covid',
            'description' => 'Optional',
            'recommended_age_months' => 24,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 20,
        ])->assertCreated();

        $categoryId = $created->json('data.category.id');

        $this->putJson("/api/preschool/settings/health/vaccination-categories/{$categoryId}", [
            'name' => 'COVID Updated',
            'code' => 'covid',
            'description' => 'Updated optional',
            'recommended_age_months' => 30,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 21,
        ])->assertOk();

        $this->postJson("/api/preschool/settings/health/vaccination-categories/{$categoryId}/archive")->assertOk();

        $this->assertSoftDeleted('preschool_vaccination_categories', [
            'id' => $categoryId,
        ]);
    }

    public function test_health_check_category_create_update_archive(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $created = $this->postJson('/api/preschool/settings/health/check-categories', [
            'name' => 'Pulse',
            'code' => 'pulse',
            'description' => 'Vitals',
            'is_active' => true,
            'sort_order' => 30,
        ])->assertCreated();

        $categoryId = $created->json('data.category.id');

        $this->putJson("/api/preschool/settings/health/check-categories/{$categoryId}", [
            'name' => 'Pulse Updated',
            'code' => 'pulse',
            'description' => 'Vitals updated',
            'is_active' => true,
            'sort_order' => 31,
        ])->assertOk();

        $this->postJson("/api/preschool/settings/health/check-categories/{$categoryId}/archive")->assertOk();

        $this->assertSoftDeleted('preschool_health_check_categories', [
            'id' => $categoryId,
        ]);
    }

    public function test_dashboard_health_summary_returns_live_values(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $response = $this->getJson('/api/preschool/settings/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.dashboard.health.critical_alert_enabled', true)
            ->assertJsonPath('data.dashboard.health.severity_levels_count', 4)
            ->assertJsonPath('data.dashboard.health.incident_categories_count', 6)
            ->assertJsonPath('data.dashboard.health.vaccination_categories_count', 5)
            ->assertJsonPath('data.dashboard.health.health_check_categories_count', 6)
            ->assertJsonPath('data.dashboard.health.medication_reminder_enabled', true)
            ->assertJsonPath('data.dashboard.health.vaccination_reminder_enabled', true);
    }

    public function test_helpers_should_trigger_notification_and_acknowledgment_use_config_values(): void
    {
        $service = app(PreschoolHealthConfigurationService::class);

        $service->createSeverityLevel([
            'name' => 'Test Severity',
            'code' => 'test_severity',
            'priority' => 9,
            'color' => '#123456',
            'requires_acknowledgment' => true,
            'triggers_notification' => false,
            'is_active' => true,
            'sort_order' => 9,
        ]);

        $this->assertFalse($service->shouldTriggerHealthNotification('test_severity'));
        $this->assertTrue($service->requiresAcknowledgment('test_severity'));
    }

    public function test_active_helpers_exclude_archived_and_inactive_categories(): void
    {
        $service = app(PreschoolHealthConfigurationService::class);
        $record = $service->createIncidentCategory([
            'name' => 'Temporary',
            'code' => 'temporary',
            'description' => null,
            'default_severity_code' => 'low',
            'is_active' => true,
            'sort_order' => 50,
        ]);

        $service->archiveIncidentCategory($record);

        $activeCategories = $service->getIncidentCategories();

        $this->assertFalse($activeCategories->contains(fn (PreschoolHealthIncidentCategory $category) => (string) $category->id === (string) $record->id));
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

    private function healthPayload(): array
    {
        return [
            'critical_alert_enabled' => true,
            'guardian_notification_enabled' => true,
            'teacher_notification_enabled' => true,
            'admin_notification_enabled' => true,
            'medication_reminder_enabled' => true,
            'vaccination_reminder_enabled' => true,
            'overdue_vaccination_alert_days' => 30,
            'medication_reminder_minutes_before' => 30,
        ];
    }
}
