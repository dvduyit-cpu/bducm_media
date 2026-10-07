<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HostingDataSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('hosting-data.json')), true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($data): void {
            foreach (array_keys($data) as $table) {
                if ($table !== 'access_roles' && DB::table($table)->exists()) {
                    throw new RuntimeException('Chỉ nhập dữ liệu vào database mới. Bảng '.$table.' đã có dữ liệu.');
                }
            }

            DB::table('access_roles')->delete();

            foreach ($data as $table => $rows) {
                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        });

        $this->command?->info('Đã nhập dữ liệu hiện có vào database hosting.');
    }
}
