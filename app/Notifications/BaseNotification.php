<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class BaseNotification extends Notification
{
    // use Queueable;

    protected $title;
    protected $message;
    protected $data;
    protected $type;

    public function __construct($title, $message, $data = [], $type = 'info')
    {
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
        $this->type = $type;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return array_merge([
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
        ], $this->data);
    }
}
