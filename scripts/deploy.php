<?php

use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);
if (! is_file($root.'/.env')) {
    fwrite(STDERR, "Hãy sao chép .env.example thành .env và cấu hình hosting trước.\n");
    exit(1);
}
require $root.'/vendor/autoload.php';

function runDeploymentCommand(string $root, array $arguments): void
{
    $process = proc_open([PHP_BINARY, $root.'/artisan', ...$arguments, '--no-interaction'], [STDIN, STDOUT, STDERR], $pipes, $root);
    if (! is_resource($process)) {
        throw new RuntimeException('Không thể chạy Artisan. Kiểm tra quyền chạy PHP CLI trên hosting.');
    }
    $code = proc_close($process);
    if ($code !== 0) {
        exit($code);
    }
}

try {
    $environment = Dotenv::createArrayBacked($root)->safeLoad();
    runDeploymentCommand($root, ['config:clear']);
    if (empty($environment['APP_KEY'])) {
        runDeploymentCommand($root, ['key:generate', '--force']);
    }
    runDeploymentCommand($root, ['migrate', '--force']);

    $application = require $root.'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();
    if (! User::where('role', 'admin')->whereNull('archived_at')->where('active', true)->exists()) {
        if (! filter_var($environment['ADMIN_EMAIL'] ?? '', FILTER_VALIDATE_EMAIL) || strlen($environment['ADMIN_PASSWORD'] ?? '') < 10) {
            fwrite(STDERR, "Chưa có tài khoản Admin. Đặt ADMIN_EMAIL và ADMIN_PASSWORD (ít nhất 10 ký tự) trong .env rồi chạy lại composer run deploy.\n");
            exit(1);
        }
        runDeploymentCommand($root, ['db:seed', '--class=DatabaseSeeder', '--force']);
    }
    runDeploymentCommand($root, ['optimize:clear']);
    runDeploymentCommand($root, ['optimize']);
    echo "Triển khai hoàn tất. Document root cần trỏ đến thư mục public.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Không thể triển khai: '.$exception->getMessage()."\n");
    exit(1);
}
