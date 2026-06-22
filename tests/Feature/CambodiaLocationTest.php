<?php

namespace Tests\Feature;

use App\Models\CambodiaCommune;
use App\Models\CambodiaDistrict;
use App\Models\CambodiaProvince;
use App\Models\CambodiaVillage;
use App\Models\User;
use App\Support\CambodiaLocationImporter;
use Database\Seeders\HfccfAuthSeeder;
use Database\Seeders\CambodiaLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CambodiaLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_importer_resolves_frontend_csv_paths(): void
    {
        $importer = app(CambodiaLocationImporter::class);
        $path = $importer->resolveSourcePath('Cambodia Province List 2025.csv');

        $this->assertStringContainsString('hfccf-frontend', $path);
        $this->assertFileExists($path);
    }

    public function test_seeder_imports_all_location_levels_and_is_idempotent(): void
    {
        Artisan::call('db:seed', ['--class' => CambodiaLocationSeeder::class]);
        Artisan::call('db:seed', ['--class' => CambodiaLocationSeeder::class]);

        $this->assertDatabaseCount('cambodia_provinces', 25);
        $this->assertDatabaseCount('cambodia_districts', 210);
        $this->assertDatabaseCount('cambodia_communes', 1661);
        $this->assertDatabaseCount('cambodia_villages', 14576);
    }

    public function test_authenticated_users_can_read_locations_and_parent_filters_work(): void
    {
        Artisan::call('db:seed', ['--class' => HfccfAuthSeeder::class]);
        Artisan::call('db:seed', ['--class' => CambodiaLocationSeeder::class]);
        Sanctum::actingAs($this->makeUser());

        $provinceResponse = $this->getJson('/api/locations/provinces')->assertOk();
        $provinceResponse->assertJsonPath('data.0.code', '01');
        $provinceResponse->assertJsonPath('data.0.name_kh', CambodiaProvince::query()->where('code', '01')->value('name_kh'));

        $province = CambodiaProvince::query()->where('code', '01')->firstOrFail();
        $district = CambodiaDistrict::query()->where('province_id', $province->id)->where('code', '0102')->firstOrFail();
        $commune = CambodiaCommune::query()->where('district_id', $district->id)->where('code', '010201')->firstOrFail();
        $village = CambodiaVillage::query()->where('commune_id', $commune->id)->where('code', '01020101')->firstOrFail();

        $districtResponse = $this->getJson('/api/locations/districts?province_code=1')->assertOk();
        $districtResponse->assertJsonCount($province->districts()->count(), 'data');
        $districtResponse->assertJsonFragment(['code' => '0102', 'name_kh' => $district->name_kh]);

        $provinceTwo = CambodiaProvince::query()->where('code', '02')->firstOrFail();
        $districtResponseZeroPadded = $this->getJson('/api/locations/districts?province_code=02')->assertOk();
        $districtResponseZeroPadded->assertJsonCount($provinceTwo->districts()->count(), 'data');

        $districtResponseNumeric = $this->getJson('/api/locations/districts?province_code=2')->assertOk();
        $districtResponseNumeric->assertJsonCount($provinceTwo->districts()->count(), 'data');

        $communeResponse = $this->getJson('/api/locations/communes?district_code=102')->assertOk();
        $communeResponse->assertJsonCount($district->communes()->count(), 'data');
        $communeResponse->assertJsonFragment(['code' => '010201', 'name_kh' => $commune->name_kh]);

        $villageResponse = $this->getJson('/api/locations/villages?commune_code=10201')->assertOk();
        $villageResponse->assertJsonCount($commune->villages()->count(), 'data');
        $villageResponse->assertJsonFragment(['code' => '01020101', 'name_kh' => $village->name_kh]);
    }

    public function test_location_endpoints_require_authentication(): void
    {
        $this->getJson('/api/locations/provinces')->assertUnauthorized();
        $this->getJson('/api/locations/districts?province_code=01')->assertUnauthorized();
        $this->getJson('/api/locations/communes?district_code=0102')->assertUnauthorized();
        $this->getJson('/api/locations/villages?commune_code=010201')->assertUnauthorized();
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'id' => 'usr_'.Str::upper(Str::random(12)),
            'first_name' => 'Location',
            'last_name' => 'Reader',
            'username' => 'Location Reader',
            'email' => uniqid('location_reader_', true).'@example.test',
            'role_code' => 'adminpreschool',
            'department_code' => 'education',
        ]);
    }
}
