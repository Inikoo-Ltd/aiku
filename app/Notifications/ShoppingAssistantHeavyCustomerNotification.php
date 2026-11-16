<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

class ShoppingAssistantHeavyCustomerNotification extends Notification
{
    use SendsMailThroughSes;

    public function __construct(public string $website, public string $customer, public float $spent, public float $monthly)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function title(): string
    {
        return __('AI shopping assistant: one customer used :share of :website\'s budget', ['share' => Number::percentage($this->monthly > 0 ? $this->spent / $this->monthly * 100 : 100), 'website' => $this->website]);
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject($this->title())
            ->line(__(':customer has spent :spent of the :monthly monthly AI budget of :website this month.', [
                'customer' => $this->customer,
                'spent'    => Number::currency($this->spent, 'USD'),
                'monthly'  => Number::currency($this->monthly, 'USD'),
                'website'  => $this->website,
            ]))
            ->line(__('Nobody has been blocked. If the budget runs out, the assistant pauses on this website until next month.'))
            ->action(__('Open the AI Assistant report'), route('grp.websites.shopping_assistant.show'));
    }
}
