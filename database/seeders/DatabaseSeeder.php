<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if (! $email || ! $password || strlen($password) < 10) {
            $this->command?->warn('Đặt ADMIN_EMAIL và ADMIN_PASSWORD (ít nhất 10 ký tự) trong .env để tạo quản trị viên.');

            return;
        }
        if (User::where('role', 'admin')->exists()) {
            return;
        }
        User::firstOrCreate(['email' => $email], ['name' => 'Admin tổng', 'password' => $password, 'role' => 'admin', 'active' => true]);
    }
}
