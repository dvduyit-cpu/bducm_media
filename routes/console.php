<?php

use App\Models\MediaRequest;
use App\Notifications\RequestUpdated;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('media:remind', function () {
    $count = 0;
    MediaRequest::active()->where('due_at', '<=', now()->addDays(2))->with(['assignee', 'contact'])->chunkById(100, function ($items) use (&$count) {
        foreach ($items as $item) {
            foreach (collect([$item->assignee, $item->contact])->filter()->unique('id') as $user) {
                $sent = $user->notifications()->where('type', RequestUpdated::class)->whereDate('created_at', today())->get()->contains(fn ($n) => ($n->data['request_id'] ?? null) === $item->id && str_starts_with($n->data['message'] ?? '', 'Nhắc hạn:'));
                if (! $sent) {
                    $user->notify(new RequestUpdated($item, 'Nhắc hạn: '.($item->overdue ? 'Công việc đã quá hạn' : 'Công việc sắp đến hạn').' • '.$item->due_at->format('d/m/Y H:i')));
                    $count++;
                }
            }
        }
    });
    $this->info("Đã gửi {$count} thông báo nhắc hạn.");
})->purpose('Nhắc việc sắp đến hạn và quá hạn, tối đa một lần mỗi ngày');
Schedule::command('media:remind')->dailyAt('08:00')->withoutOverlapping();
