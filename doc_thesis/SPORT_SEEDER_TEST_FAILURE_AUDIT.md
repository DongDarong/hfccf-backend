# Sport Report Testing Seeder - Test Failure Audit

**Date:** 2026-07-24  
**Status:** INVESTIGATION COMPLETE - ROOT CAUSE IDENTIFIED  
**Issue:** PHPUnit tests fail with foreign key constraint violation on SQLite in-memory, while manual seeding works on MySQL

---

## Executive Summary

The `SportReportTestingSeeder` **succeeds** when run manually via:
```bash
php artisan db:seed --class=SportReportTestingSeeder
```

But **fails** in the PHPUnit test suite against SQLite in-memory database with:
```
SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed
```

**Root Cause:** The test does not seed required lookup data (`HfccfAuthSeeder`), causing foreign key violations when `SportReportTestingSeeder` tries to insert users with non-existent role and department codes.

---

## Foreign Key Chain Analysis

### Users Table Foreign Keys

**File:** `database/migrations/2026_04_24_150000_create_hfccf_auth_tables.php` (Lines 107-114)

```php
$table->foreign('role_code', 'fk_users_role')
    ->references('code')
    ->on('roles')
    ->cascadeOnUpdate();
$table->foreign('department_code', 'fk_users_department')
    ->references('code')
    ->on('departments')
    ->cascadeOnUpdate();
```

**Additional FK:** After migration `2026_05_09_051215_create_user_lookups_tables.php` (Lines 66-68):
```php
$table->string('status', 32)->change();
$table->foreign('status')->references('code')->on('user_statuses')->cascadeOnUpdate();
```

### Full Foreign Key Chain

```
users.role_code → roles.code (FK)
users.department_code → departments.code (FK)
users.status → user_statuses.code (FK)

roles.scope → role_scopes.code (FK, added in refactor migration)
roles.domain_code → domain_codes.code (FK, added in refactor migration)
roles.department_code → departments.code (FK)
```

---

## Parent Records Required

### 1. Department Records

**Source:** `database/seeders/HfccfAuthSeeder.php` (Lines 16-21)

Must exist for users with `department_code = 'sports'`:

```php
DB::table('departments')->upsert([
    ['code' => 'operations', ...],
    ['code' => 'education', ...],
    ['code' => 'sports', 'name' => 'Sports', ...],  ← REQUIRED
    ['code' => 'administration', ...],
], ['code'], [...]);
```

### 2. Role Records

**Source:** `database/seeders/HfccfAuthSeeder.php` (Line 28)

Must exist for users with `role_code = 'adminsport'`:

```php
['code' => 'adminsport', 'name' => 'Sport Admin', 'scope' => 'admin', 
 'domain_code' => 'sport', 'department_code' => 'sports', 'sort_order' => 5],
```

### 3. User Status Records

**Source:** `database/migrations/2026_05_09_051215_create_user_lookups_tables.php` (Lines 33-38)

Must exist in `user_statuses` table:
- `active` ✓ (Migration creates this)
- `pending` ✓ (Migration creates this)
- `inactive` ✓ (Migration creates this)  
- `suspended` ✓ (Migration creates this)

### 4. Role Scope & Domain Code Records

**Source:** Same lookup migration (Lines 40-44, 46-52)

Must exist:
- `role_scopes`: super_admin, admin, staff, portal ✓ (Migration creates these)
- `domain_codes`: global, english, preschool, scholarship, sport ✓ (Migration creates these)

---

## Failure Point

**First Missing Parent Record:**

When `SportReportTestingSeeder` attempts to insert first user:

```php
'role_code' => 'adminsport',           ← FK references roles.code
'department_code' => 'sports',         ← FK references departments.code
'status' => 'active',                  ← FK references user_statuses.code
```

**The missing parent row:** `departments` table has NO `'sports'` row

Foreign key constraint fails immediately on INSERT because the parent record doesn't exist.

---

## Why MySQL Seeding Works

When running `php artisan db:seed --class=SportReportTestingSeeder` on a local MySQL database that has been previously used or where `php artisan migrate` was run:

1. **Database is already populated** from prior runs
2. **Roles and departments exist** from prior database seeding or manual setup
3. `SportReportTestingSeeder` executes successfully because parent records already exist
4. **Seeder appears independent** but actually depends on prior database state

**This is a false positive success** — it works only because:
- The test/dev MySQL database has accumulated data from previous runs
- Not because `SportReportTestingSeeder` is truly independent
- A fresh MySQL database would fail identically to the SQLite test

---

## Test Bootstrap Analysis

### RefreshDatabase Behavior

**File:** `tests/Feature/Sport/SportReportTestingSeederTest.php` (Lines 15, 20, 24)

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class SportReportTestingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_runs_successfully()
    {
        $this->seed(SportReportTestingSeeder::class);  ← ONLY this seeder
        // ...
    }
}
```

**RefreshDatabase behavior:**
1. Runs ALL migrations on fresh database (creates tables with all FKs)
2. Does NOT automatically run any seeders
3. Each test method receives a fresh database state
4. Test explicitly calls `$this->seed(SportReportTestingSeeder::class)` — **only this one**

**Result:** Parent lookups are never seeded

### Test Database Configuration

**File:** `phpunit.xml` (Lines 28-29)

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

- In-memory SQLite database
- No shared state between tests
- Foreign key constraints enforced strictly

### Correct Test Pattern (from existing tests)

**File:** `tests/Feature/AuthApiTest.php` (Lines 26-31)

```php
protected function setUp(): void
{
    parent::setUp();
    
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->seed(DatabaseSeeder::class);  ← Seeds HfccfAuthSeeder FIRST
}
```

Other tests that need lookups (roles, departments, users):
- `AssessmentExportPipelineTest`
- `AssessmentFormPersistenceTest`
- `AssessmentPrintRendererTest`
- `EnglishApiTest`
- `EnrollStudentCreatesPaymentTest`
- `GuardianPortalApiTest`

All explicitly seed `DatabaseSeeder` in `setUp()` method.

---

## Problem Attribution

### Does NOT belong to:
- ❌ SportReportTestingSeeder (seeder logic is sound)
- ❌ migrations (properly define foreign keys and lookups)
- ❌ RefreshDatabase trait (behaves correctly per design)
- ❌ SQLite (correctly enforces constraints)

### DOES belong to:
✅ **Test bootstrap / test setup**

The test class `SportReportTestingSeederTest` fails to seed required parent records before calling `SportReportTestingSeeder`.

---

## Missing Parent Rows - Exact Chain

```
Test starts
  ↓
RefreshDatabase runs migrations
  → Creates 'roles' table (empty)
  → Creates 'departments' table (empty)
  → Creates 'user_statuses' table (empty - will be seeded by migration)
  → Creates 'users' table (empty, with FKs to roles, departments, user_statuses)
  ↓
Test calls: $this->seed(SportReportTestingSeeder::class)
  ↓
SportReportTestingSeeder:upsertTestUser() attempts:
  INSERT INTO users (role_code='adminsport', department_code='sports', status='active', ...)
  ↓
Database checks FKs:
  ✗ roles.code='adminsport' does not exist → FK VIOLATION
  ✗ departments.code='sports' does not exist → FK VIOLATION
  ✓ user_statuses.code='active' exists (migration seeded it)
  ↓
SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed
```

---

## Verification: Parent Record Existence

### In Working MySQL State (Manual Seed)

Rows that must pre-exist:

| Table | code | notes |
|-------|------|-------|
| departments | sports | Seeded by HfccfAuthSeeder line 19 |
| roles | adminsport | Seeded by HfccfAuthSeeder line 28 |
| roles | coach | Seeded by HfccfAuthSeeder line 32 |
| user_statuses | active | Seeded by lookup migration line 34 |
| user_statuses | pending | Seeded by lookup migration line 35 |
| role_scopes | admin | Seeded by lookup migration line 42 |
| role_scopes | staff | Seeded by lookup migration line 42 |
| domain_codes | sport | Seeded by lookup migration line 51 |

### In Failing SQLite Test State

After RefreshDatabase + migrations only:

| Table | Contains |
|-------|----------|
| departments | **EMPTY** ← Missing 'sports' |
| roles | **EMPTY** ← Missing 'adminsport' and 'coach' |
| user_statuses | `active`, `pending`, `inactive`, `suspended` (migration seeds) |
| role_scopes | `super_admin`, `admin`, `staff` (migration seeds) |
| domain_codes | `global`, `english`, `preschool`, `scholarship`, `sport` (migration seeds) |

---

## Summary: Why The Failure Occurs

| Component | MySQL Manual Seed | SQLite PHPUnit |
|-----------|------------------|-----------------|
| Database | Existing state | Fresh (via migration only) |
| AuthSeeder | Pre-seeded (prior runs) | NOT seeded |
| Roles table | Contains 'adminsport' ✓ | EMPTY ✗ |
| Departments table | Contains 'sports' ✓ | EMPTY ✗ |
| SportSeeder runs | Finds parent rows ✓ | FK error ✗ |

---

## Conclusion

**The test setup is incomplete.** The test calls `SportReportTestingSeeder` without first seeding the required lookup tables that `SportReportTestingSeeder` depends on.

**The seeder is NOT broken.** It works correctly when run in an environment where parent records exist (like manual seeding on a previously-used MySQL database).

**Fix belongs in:** Test class setUp method or in the test method itself, NOT in the seeder.

---

**Status:** ✅ Investigation complete. Root cause identified. Awaiting approval before proposing fix.
