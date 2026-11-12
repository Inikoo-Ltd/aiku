<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Tasks\StaffTask;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A customer asked the AI shopping assistant when a product is back in stock and our orders give no
 * reliable date: the organisation's purchasing people are told, and again while nobody has taken it.
 */
class ShoppingAssistantRestockQuestionNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    public function __construct(public StaffTask $task, public bool $isReminder = false)
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
            'type'  => 'restock_question',
            'route' => $this->url(),
        ];
    }

    public function url(): string
    {
        return route('grp.tasks.show', $this->task->reference);
    }

    public function title(): string
    {
        $customer = (string) data_get($this->task->data, 'customer_name', '');

        return $this->isReminder
            ? __('Nobody has answered :customer yet about a restock (:reference)', ['customer' => $customer, 'reference' => $this->task->reference])
            : __('Restock question from :customer (:reference)', ['customer' => $customer, 'reference' => $this->task->reference]);
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

        return $message->action(__('Open the task'), $this->url());
    }
}
