<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued, so asking with a known email takes as long as asking with an unknown one.
 */
class SupplierPortalLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsMailThroughSes;

    public function __construct(public string $url, public string $supplierName, public string $groupName, public int $days)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject(__('Your link to :supplier with :group', ['supplier' => $this->supplierName, 'group' => $this->groupName]))
            ->line(__('Here is your private link to see how the products of :supplier sell with :group, and your purchase orders.', ['supplier' => $this->supplierName, 'group' => $this->groupName]))
            ->action(__('Open my page'), $this->url)
            ->line(__('It works for :days days and only for you, so please do not forward it. If you did not ask for it, you can ignore this email.', ['days' => $this->days]));
    }
}
