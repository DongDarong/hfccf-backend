<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolStudentIdentityFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_student_store_and_update_persists_structured_identity_and_residence(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'id' => 'usr-stu-found-001',
            'email' => 'stu-found-001@hfccf.org',
        ]);
        Sanctum::actingAs($admin);

        $location = $this->createLocationTree('STU');

        $create = $this->postJson('/api/preschool/students', [
            'student_code' => 'PS-STU-FOUND-001',
            'first_name' => 'Dara',
            'last_name' => 'Sok',
            'latin_name' => 'Dara Sok',
            'gender' => 'male',
            'date_of_birth' => '2020-01-15',
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
            'guardian_name' => 'Sok Vanna',
            'guardian_phone' => '+855 12 900 900',
            'status' => 'active',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.student.latinName', 'Dara Sok')
            ->assertJsonPath('data.student.placeOfBirth', 'Legacy Birthplace')
            ->assertJsonPath('data.student.birthProvinceId', $location['provinceId'])
            ->assertJsonPath('data.student.address', 'ភូមិ STU, ឃុំ STU, ស្រុក STU, ខេត្ត STU');

        $studentId = $create->json('data.student.id');
        $this->assertDatabaseHas('preschool_students', [
            'id' => $studentId,
            'latin_name' => 'Dara Sok',
            'place_of_birth' => 'Legacy Birthplace',
            'birth_province_id' => $location['provinceId'],
            'residence_village_id' => $location['villageId'],
        ]);

        $show = $this->getJson('/api/preschool/students/'.$studentId);

        $show->assertOk()
            ->assertJsonPath('data.student.latinName', 'Dara Sok')
            ->assertJsonPath('data.student.nationality', 'Cambodian')
            ->assertJsonPath('data.student.ethnicity', 'Khmer')
            ->assertJsonPath('data.student.guardianType', null)
            ->assertJsonPath('data.student.birthProvinceId', $location['provinceId'])
            ->assertJsonPath('data.student.birthDistrictId', $location['districtId'])
            ->assertJsonPath('data.student.birthCommuneId', $location['communeId'])
            ->assertJsonPath('data.student.birthVillageId', $location['villageId'])
            ->assertJsonPath('data.student.residenceProvinceId', $location['provinceId'])
            ->assertJsonPath('data.student.residenceDistrictId', $location['districtId'])
            ->assertJsonPath('data.student.residenceCommuneId', $location['communeId'])
            ->assertJsonPath('data.student.residenceVillageId', $location['villageId'])
            ->assertJsonPath('data.student.birthLocationDisplay', 'ភូមិ STU, ឃុំ STU, ស្រុក STU, ខេត្ត STU')
            ->assertJsonPath('data.student.currentResidenceDisplay', 'ភូមិ STU, ឃុំ STU, ស្រុក STU, ខេត្ត STU');

        $update = $this->putJson('/api/preschool/students/'.$studentId, [
            'latin_name' => 'Dara Sok Updated',
        ]);

        $update->assertOk()
            ->assertJsonPath('data.student.latinName', 'Dara Sok Updated');

        $this->assertDatabaseHas('preschool_students', [
            'id' => $studentId,
            'latin_name' => 'Dara Sok Updated',
        ]);
    }

    public function test_student_validation_rejects_invalid_location_hierarchy(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'id' => 'usr-stu-found-002',
            'email' => 'stu-found-002@hfccf.org',
        ]);
        Sanctum::actingAs($admin);

        $provinceA = $this->createProvince('SPA', 'Province A', 'Province A');
        $provinceB = $this->createProvince('SPB', 'Province B', 'Province B');
        $districtA = $this->createDistrict($provinceA['id'], 'SDA', 'District A', 'District A');
        $districtB = $this->createDistrict($provinceB['id'], 'SDB', 'District B', 'District B');
        $communeA = $this->createCommune($provinceA['id'], $districtA['id'], 'SCA', 'Commune A', 'Commune A');
        $communeB = $this->createCommune($provinceB['id'], $districtB['id'], 'SCB', 'Commune B', 'Commune B');
        $villageA = $this->createVillage($provinceA['id'], $districtA['id'], $communeA['id'], 'SVA', 'Village A', 'Village A');
        $villageB = $this->createVillage($provinceB['id'], $districtB['id'], $communeB['id'], 'SVB', 'Village B', 'Village B');

        $districtMismatch = $this->postJson('/api/preschool/students', [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'status' => 'active',
            'birth_province_id' => $provinceA['id'],
            'birth_district_id' => $districtB['id'],
        ]);

        $districtMismatch->assertStatus(422)
            ->assertJsonPath('data.errors.birth_district_id.0', 'The selected district does not belong to the selected province.');

        $communeMismatch = $this->postJson('/api/preschool/students', [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'status' => 'active',
            'residence_province_id' => $provinceA['id'],
            'residence_district_id' => $districtA['id'],
            'residence_commune_id' => $communeB['id'],
        ]);

        $communeMismatch->assertStatus(422)
            ->assertJsonPath('data.errors.residence_commune_id.0', 'The selected commune does not belong to the selected district.');

        $villageMismatch = $this->postJson('/api/preschool/students', [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'status' => 'active',
            'birth_province_id' => $provinceA['id'],
            'birth_district_id' => $districtA['id'],
            'birth_commune_id' => $communeA['id'],
            'birth_village_id' => $villageB['id'],
        ]);

        $villageMismatch->assertStatus(422)
            ->assertJsonPath('data.errors.birth_village_id.0', 'The selected village does not belong to the selected commune.');
    }

    public function test_enrollment_conversion_maps_identity_birth_and_residence_fields_and_preserves_legacy_text(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'id' => 'usr-stu-found-003',
            'email' => 'stu-found-003@hfccf.org',
        ]);
        Sanctum::actingAs($admin);

        $location = $this->createLocationTree('CON');

        $applicationId = DB::table('preschool_enrollment_applications')->insertGetId([
            'application_code' => 'ENR-CONTRACT-001',
            'first_name' => 'Mina',
            'last_name' => 'Khan',
            'khmer_name' => 'Mina Khan',
            'gender' => 'female',
            'date_of_birth' => '2021-06-10',
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
            'guardian_name' => 'Khan Vanna',
            'guardian_phone' => '+855 12 333 444',
            'guardian_address' => 'Legacy guardian address',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/preschool/enrollments/{$applicationId}/enroll", []);

        $response->assertOk()
            ->assertJsonPath('data.application.status', 'enrolled');

        $studentId = $response->json('data.application.enrolledStudentId');
        $this->assertNotEmpty($studentId);

        $this->assertDatabaseHas('preschool_students', [
            'id' => $studentId,
            'first_name' => 'Mina',
            'last_name' => 'Khan',
            'latin_name' => 'Mina Khan',
            'place_of_birth' => 'Legacy Birthplace',
            'birth_province_id' => $location['provinceId'],
            'residence_village_id' => $location['villageId'],
        ]);

        $this->assertDatabaseHas('preschool_students', [
            'id' => $studentId,
            'address' => 'ភូមិ CON, ឃុំ CON, ស្រុក CON, ខេត្ត CON',
        ]);

        $this->assertDatabaseHas('preschool_enrollment_applications', [
            'id' => $applicationId,
            'status' => 'enrolled',
            'enrolled_student_id' => $studentId,
        ]);
    }

    public function test_student_update_with_empty_class_ids_does_not_throw_and_deactivates_assignments(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'id' => 'usr-stu-found-004',
            'email' => 'stu-found-004@hfccf.org',
        ]);
        Sanctum::actingAs($admin);

        $class = $this->createPreschoolClass('PS-STU-FOUND-CLS-004', 'Regression Class');

        $create = $this->postJson('/api/preschool/students', [
            'student_code' => 'PS-STU-FOUND-004',
            'first_name' => 'Sok',
            'last_name' => 'Vanna',
            'status' => 'active',
            'class_ids' => [$class['id']],
        ]);

        $create->assertCreated();

        $studentId = $create->json('data.student.id');

        $this->assertDatabaseHas('preschool_class_students', [
            'class_id' => $class['id'],
            'student_id' => $studentId,
            'status' => 'active',
        ]);

        $update = $this->putJson('/api/preschool/students/'.$studentId, [
            'class_ids' => [$class['id']],
        ]);

        $update->assertOk()
            ->assertJsonPath('data.student.id', $studentId);

        $this->assertDatabaseHas('preschool_class_students', [
            'class_id' => $class['id'],
            'student_id' => $studentId,
            'status' => 'active',
            'enrollment_status' => 'active',
        ]);

        $clear = $this->putJson('/api/preschool/students/'.$studentId, [
            'class_ids' => [],
        ]);

        $clear->assertOk()
            ->assertJsonPath('data.student.id', $studentId);

        $this->assertDatabaseHas('preschool_class_students', [
            'class_id' => $class['id'],
            'student_id' => $studentId,
            'status' => 'inactive',
            'enrollment_status' => 'inactive',
        ]);
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

    private function createPreschoolClass(string $code, string $name): array
    {
        $classId = DB::table('preschool_classes')->insertGetId([
            'code' => $code,
            'name' => $name,
            'level' => 'Nursery',
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
            'room' => 'Room A1',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $classId];
    }
}
