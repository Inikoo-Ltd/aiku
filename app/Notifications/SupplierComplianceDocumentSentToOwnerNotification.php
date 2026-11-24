<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-24 10:02
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Notifications;

use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierComplianceDocumentSentToOwnerNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsMailThroughSes;

    public function __construct(public string $title, public string $body, public string $whyLine)
    {
        $this->afterCommit();
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject($this->title)
            ->line($this->plainLine($this->body))
            ->line($this->plainLine($this->whyLine));
    }
}
