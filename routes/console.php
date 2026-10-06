<?php

use App\Models\MediaRequest;
use App\Models\User;
use App\Notifications\RequestUpdated;
use App\Notifications\WeeklyRegistrationReminder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('media:remind', function () {
    $count = 0;
    MediaRequest::active()->where('due_at', '<=', now()->addDays(2))->with(['supportDepartments'])->chunkById(100, function ($items) use (&$count) {
        foreach ($items as $item) {
            $units = [$item->department_id, ...$item->supportDepartments->pluck('id')->all()];
            $recipients = User::where('active', true)->where(fn ($q) => $q->whereIn('role', ['admin', 'office'])->orWhereIn('department_id', $units)->orWhereIn('id', array_filter([$item->assignee_id, $item->media_assignee_id])))->get()->filter(fn (User $recipient) => $item->canNotify($recipient));
            foreach ($recipients as $user) {
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

Artisan::command('media:registration-remind', function () {
    $week = now()->startOfWeek()->addWeek()->toDateString();
    $count = 0;
    User::where('active', true)->whereNotNull('department_id')->whereIn('role', ['head', 'staff'])->chunkById(100, function ($users) use ($week, &$count) {
        foreach ($users as $user) {
            $sent = $user->notifications()->where('type', WeeklyRegistrationReminder::class)->where('data->week', $week)->exists();
            if (! $sent) {
                $user->notify(new WeeklyRegistrationReminder($week));
                $count++;
            }
        }
    });
    $this->info("Đã gửi {$count} thông báo đăng ký tuần kế tiếp.");
})->purpose('Nhắc đăng ký hoạt động tuần kế tiếp, tối đa một lần mỗi người mỗi tuần');
Schedule::command('media:registration-remind')->weeklyOn(4, '15:00')->withoutOverlapping();
