<?php

namespace Tests\Feature;

use Database\Seeders\HostingDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class HostingDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_is_restored_with_original_values_and_relationships(): void
    {
        $this->seed(HostingDataSeeder::class);
        $data = json_decode(file_get_contents(database_path('hosting-data.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($data as $table => $rows) {
            $this->assertDatabaseCount($table, count($rows));
            foreach ($rows as $row) {
                $this->assertDatabaseHas($table, $row);
            }
        }
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_existing_data_is_preserved_when_import_is_rejected(): void
    {
        DB::table('departments')->insert(['name' => 'Existing department', 'code' => 'EXISTING']);
        $roles = DB::table('access_roles')->get()->toArray();

        try {
            $this->seed(HostingDataSeeder::class);
            $this->fail('Import should reject an existing database.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('departments', $exception->getMessage());
        }

        $this->assertDatabaseCount('departments', 1);
        $this->assertDatabaseHas('departments', ['code' => 'EXISTING']);
        $this->assertEquals($roles, DB::table('access_roles')->get()->toArray());
        $this->assertDatabaseCount('users', 0);
    }

    public function test_repeated_import_is_rejected_without_changing_restored_data(): void
    {
        $this->seed(HostingDataSeeder::class);
        $users = DB::table('users')->get()->toArray();

        try {
            $this->seed(HostingDataSeeder::class);
            $this->fail('Repeated import should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('đã có dữ liệu', $exception->getMessage());
        }

        $this->assertEquals($users, DB::table('users')->get()->toArray());
    }
}
