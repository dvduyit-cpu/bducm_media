<?php

namespace App\Notifications;

use App\Models\MediaRequest;
use Illuminate\Notifications\Notification;

class RequestUpdated extends Notification
{
    public function __construct(public MediaRequest $item, public string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['request_id' => $this->item->id, 'title' => $this->item->title, 'message' => $this->message];
    }
}
