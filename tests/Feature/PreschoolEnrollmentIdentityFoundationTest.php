<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolEnrollmentIdentityFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_enrollment_store_and_update_preserves_legacy_name_and_structured_locations(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'id' => 'usr-enr-found-001',
            'email' => 'enr-found-001@hfccf.org',
        ]);
        Sanctum::actingAs($admin);

        $location = $this->createLocationTree('ENR');

        $create = $this->postJson('/api/preschool/enrollments', [
            'first_name' => 'Sophea',
            'last_name' => 'Chan',
            'khmer_name' => 'Sophea Chan',
            'gender' => 'female',
            'date_of_birth' => '2021-03-15',
            'place_of_birth' => 'Legacy Birthplace',
            'nationality' => 'Cambodian',
            'ethnicity' => 'Khmer',
            'birth_province_id' => $location['provinceId'],
            'birth_district_id' => $location['districtId'],
            'birth_commune_id' => $location['communeId'],
            'birth_village_id' => $location['villageId'],
            'residence_province_id' => $location['provinceId'],
            'residence_district_id' => $location['districtId'],
            'residence_commune_id' => $location['communeId'],
            'residence_village_id' => $location['villageId'],
            'guardian_name' => 'Chan Makara',
            'guardian_phone' => '+855 12 111 222',
            'guardian_address' => 'Legacy guardian address',
            'status' => 'draft',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.application.khmerName', 'Sophea Chan')
            ->assertJsonPath('data.application.latinName', 'Sophea Chan')
            ->assertJsonPath('data.application.birthProvinceId', $location['provinceId'])
            ->assertJsonPath('data.application.currentResidenceDisplay', 'ភូមិ ENR, ឃុំ ENR, ស្រុក ENR, ខេត្ត ENR');

        $applicationId = $create->json('data.application.id');
        $this->assertDatabaseHas('preschool_enrollment_applications', [
            'id' => $applicationId,
            'khmer_name' => 'Sophea Chan',
            'latin_name' => 'Sophea Chan',
            'birth_province_id' => $location['provinceId'],
            'residence_village_id' => $location['villageId'],
        ]);

        $update = $this->patchJson('/api/preschool/enrollments/'.$applicationId, [
            'ethnicity' => 'Cham',
            'birth_province_id' => $location['provinceId'],
            'birth_district_id' => $location['districtId'],
            'birth_commune_id' => $location['communeId'],
            'birth_village_id' => $location['villageId'],
            'residence_province_id' => $location['provinceId'],
            'residence_district_id' => $location['districtId'],
            'residence_commune_id' => $location['communeId'],
            'residence_village_id' => $location['villageId'],
        ]);

        $update->assertOk()
            ->assertJsonPath('data.application.latinName', 'Sophea Chan')
            ->assertJsonPath('data.application.khmerName', 'Sophea Chan')
            ->assertJsonPath('data.application.ethnicity', 'Cham');

        $partialUpdate = $this->patchJson('/api/preschool/enrollments/'.$applicationId, [
            'latin_name' => 'Sophea Chan Updated',
        ]);

        $partialUpdate->assertOk()
            ->assertJsonPath('data.application.latinName', 'Sophea Chan Updated')
            ->assertJsonPath('data.application.khmerName', 'Sophea Chan Updated');

        $this->assertDatabaseHas('preschool_enrollment_applications', [
            'id' => $applicationId,
            'latin_name' => 'Sophea Chan Updated',
            'khmer_name' => 'Sophea Chan Updated',
        ]);
    }

    public function test_enrollment_validation_rejects_invalid_location_hierarchy(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'id' => 'usr-enr-found-002',
            'email' => 'enr-found-002@hfccf.org',
        ]);
        Sanctum::actingAs($admin);

        $provinceA = $this->createProvince('PA', 'Province A', 'Province A');
        $provinceB = $this->createProvince('PB', 'Province B', 'Province B');
        $district = $this->createDistrict($provinceA['id'], 'DA', 'District A', 'District A');
        $commune = $this->createCommune($provinceA['id'], $district['id'], 'CA', 'Commune A', 'Commune A');
        $village = $this->createVillage($provinceA['id'], $district['id'], $commune['id'], 'VA', 'Village A', 'Village A');
        $otherTree = $this->createLocationTree('OTH');

        $districtMismatch = $this->postJson('/api/preschool/enrollments', [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'birth_province_id' => $provinceB['id'],
            'birth_district_id' => $district['id'],
        ]);

        $districtMismatch->assertStatus(422)
            ->assertJsonPath('data.errors.birth_district_id.0', 'The selected district does not belong to the selected province.');

        $communeMismatch = $this->postJson('/api/preschool/enrollments', [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'birth_province_id' => $provinceA['id'],
            'birth_district_id' => $district['id'],
            'birth_commune_id' => $commune['id'],
            'residence_province_id' => $provinceA['id'],
            'residence_district_id' => $district['id'],
            'residence_commune_id' => $otherTree['communeId'],
        ]);

        $communeMismatch->assertStatus(422)
            ->assertJsonPath('data.errors.residence_commune_id.0', 'The selected commune does not belong to the selected district.');

        $villageMismatch = $this->postJson('/api/preschool/enrollments', [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'birth_province_id' => $provinceA['id'],
            'birth_district_id' => $district['id'],
            'birth_commune_id' => $commune['id'],
            'birth_village_id' => $otherTree['villageId'],
            'residence_province_id' => $provinceA['id'],
            'residence_district_id' => $district['id'],
            'residence_commune_id' => $commune['id'],
            'residence_village_id' => $village['id'],
        ]);

        $villageMismatch->assertStatus(422)
            ->assertJsonPath('data.errors.birth_village_id.0', 'The selected village does not belong to the selected commune.');
    }

    private function createProvince(string $code, string $nameKh, string $nameEn): array
    {
        $id = DB::table('cambodia_provinces')->insertGetId([
            'code' => $code,
            'name_kh' => $nameKh,
            'name_en' => $nameEn,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id];
    }

    private function createDistrict(int $provinceId, string $code, string $nameKh, string $nameEn): array
    {
        $id = DB::table('cambodia_districts')->insertGetId([
            'province_id' => $provinceId,
            'code' => $code,
            'name_kh' => $nameKh,
            'name_en' => $nameEn,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id];
    }

    private function createCommune(int $provinceId, int $districtId, string $code, string $nameKh, string $nameEn): array
    {
        $id = DB::table('cambodia_communes')->insertGetId([
            'province_id' => $provinceId,
            'district_id' => $districtId,
            'code' => $code,
            'name_kh' => $nameKh,
            'name_en' => $nameEn,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id];
    }

    private function createVillage(int $provinceId, int $districtId, int $communeId, string $code, string $nameKh, string $nameEn): array
    {
        $id = DB::table('cambodia_villages')->insertGetId([
            'province_id' => $provinceId,
            'district_id' => $districtId,
            'commune_id' => $communeId,
            'code' => $code,
            'name_kh' => $nameKh,
            'name_en' => $nameEn,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id];
    }

    private function createLocationTree(string $suffix): array
    {
        $province = $this->createProvince("P{$suffix}", "ខេត្ត {$suffix}", "Province {$suffix}");
        $district = $this->createDistrict($province['id'], "D{$suffix}", "ស្រុក {$suffix}", "District {$suffix}");
        $commune = $this->createCommune($province['id'], $district['id'], "C{$suffix}", "ឃុំ {$suffix}", "Commune {$suffix}");
        $village = $this->createVillage($province['id'], $district['id'], $commune['id'], "V{$suffix}", "ភូមិ {$suffix}", "Village {$suffix}");

        return [
            'provinceId' => $province['id'],
            'districtId' => $district['id'],
            'communeId' => $commune['id'],
            'villageId' => $village['id'],
        ];
    }
}
