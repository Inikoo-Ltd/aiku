<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 16:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Notifications;

use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a supplier portal contact what the buyer did with the deposit it asked for: paid, or rejected with a reason.
 */
class SupplierDepositAnsweredNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsMailThroughSes;

    public function __construct(public string $title, public string $body, public string $url, public string $groupName)
    {
        $this->afterCommit();
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = $this->newMailMessage()
            ->markdown('notifications::email', ['shop' => $this->groupName])
            ->subject($this->title)
            ->line($this->plainLine($this->body))
            ->action(__('Open my page'), $this->url);

        if (app()->isProduction()) {
            $message->from('help@aiku.io', $this->groupName);
        }

        return $message;
    }
}
