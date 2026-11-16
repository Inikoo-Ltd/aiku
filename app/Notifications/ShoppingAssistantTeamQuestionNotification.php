<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Tasks\StaffTask;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A customer asked the AI shopping assistant something our texts do not answer: the organisation's
 * question person (and later its backup) is asked.
 */
class ShoppingAssistantTeamQuestionNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    public function __construct(public StaffTask $task)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->summary(),
            'type'  => 'assistant_question',
            'route' => $this->url(),
        ];
    }

    public function url(): string
    {
        return route('grp.tasks.show', $this->task->reference);
    }

    public function title(): string
    {
        return __(':customer asks (:shop)', ['customer' => (string) data_get($this->task->data, 'customer_name'), 'shop' => (string) data_get($this->task->data, 'shop_code')]);
    }

    public function summary(): string
    {
        return (string) $this->task->description;
    }

    public function toMail($notifiable): MailMessage
    {
        $message = $this->newMailMessage()->subject($this->title());

        foreach (preg_split('/\n+/', $this->summary()) as $line) {
            if (trim($line) !== '') {
                $message->line($line);
            }
        }

        return $message->action(__('Answer'), $this->url());
    }
}
