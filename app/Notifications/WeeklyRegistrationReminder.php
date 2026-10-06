<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class WeeklyRegistrationReminder extends Notification
{
    public function __construct(public string $week) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['week' => $this->week, 'title' => 'Đăng ký lịch hoạt động tuần kế tiếp', 'message' => 'Vui lòng đăng ký hoạt động tuần bắt đầu '.$this->week.' để VP BGĐ tổng hợp và điều phối.', 'url' => route('requests.create')];
    }
}
