<?php

use App\Models\MediaRequest;
use App\Notifications\RequestUpdated;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local')) {
    throw new RuntimeException('Local only');
}
$data = json_decode(file_get_contents(dirname(__DIR__).'/.runtime/e2e-request.json'), true, flags: JSON_THROW_ON_ERROR);
$item = MediaRequest::whereKey($data['id'])->where('title', $data['title'])->whereHas('creator', fn ($q) => $q->where('email', 'nhanvien@bdu.local'))->firstOrFail();
if (! str_starts_with($item->title, '__E2E ')) {
    throw new RuntimeException('Not E2E data');
}
foreach ($item->attachments as $attachment) {
    Storage::disk('local')->delete($attachment->path);
}
DB::table('notifications')->where('type', RequestUpdated::class)->where('data->request_id', $item->id)->delete();
$item->delete();
echo "Removed only the generated E2E request.\n";
