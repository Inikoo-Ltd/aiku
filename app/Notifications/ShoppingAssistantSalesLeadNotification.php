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
 * A customer told the AI shopping assistant they plan a big order and agreed to a call: the sales
 * leads manager of the organisation is told at once, and again while nobody has taken the task.
 */
class ShoppingAssistantSalesLeadNotification extends Notification
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
            'type'  => 'sales_lead',
            'route' => $this->url(),
        ];
    }

    public function url(): string
    {
        return route('grp.tasks.show', $this->task->reference);
    }

    public function title(): string
    {
        return $this->isReminder
            ? __('Nobody has called :customer yet about a big order (:reference)', ['customer' => $this->customerName(), 'reference' => $this->task->reference])
            : __('Big order lead: :customer wants a call (:reference)', ['customer' => $this->customerName(), 'reference' => $this->task->reference]);
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

    private function customerName(): string
    {
        return (string) data_get($this->task->data, 'customer_name', $this->task->subject);
    }
}
