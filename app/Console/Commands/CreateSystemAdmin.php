<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSystemAdmin extends Command
{
    protected $signature = 'media:create-admin {email=admin@bdu.local} {--reset-accounts : Lưu trữ tài khoản cũ và tạo lại quyền truy cập}';

    protected $description = 'Tạo một admin tổng; chỉ đặt lại tài khoản khi có --reset-accounts';

    public function handle(): int
    {
        $existing = User::where('role', 'admin')->whereNull('archived_at')->first();
        if ($existing && ! $this->option('reset-accounts')) {
            $this->info('Admin tổng đã có: '.$existing->email);

            return self::SUCCESS;
        }
        $email = $this->argument('email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || (! $this->option('reset-accounts') && User::where('email', $email)->exists())) {
            $this->error('Email không hợp lệ hoặc đã được sử dụng.');

            return self::FAILURE;
        }
        $password = Str::password(18);
        $archived = DB::transaction(function () use ($existing, $email, $password) {
            $count = 0;
            if ($this->option('reset-accounts')) {
                User::whereNull('archived_at')->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->lockForUpdate()->get()->each(function (User $user) use (&$count) {
                    $user->update(['archived_email' => $user->email, 'email' => 'archived.user.'.$user->id.'@bdu.invalid', 'archived_at' => now(), 'active' => false, 'permissions' => [], 'view_all_units' => false]);
                    $user->remember_token = null;
                    $user->save();
                    $count++;
                });
            }
            $data = ['name' => 'Admin tổng', 'email' => $email, 'password' => $password, 'role' => 'admin', 'active' => true, 'permissions' => [], 'view_all_units' => true];
            if ($existing) {
                $existing->update($data);
                $existing->remember_token = null;
                $existing->save();
            } else {
                User::create($data);
            }

            return $count;
        });
        $this->info('Đã lưu trữ '.$archived.' tài khoản cũ.');
        $this->info('Email: '.$email);
        $this->info('Mật khẩu: '.$password);

        return self::SUCCESS;
    }
}
