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
use Illuminate\Support\HtmlString;

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

    /**
     * In the language the supplier chose on the log in page, from the group, with a short English copy at the end.
     */
    public function toMail($notifiable): MailMessage
    {
        $message = $this->newMailMessage()
            ->markdown('notifications::email', ['shop' => $this->groupName])
            ->subject(__('Your link to :supplier with :group', ['supplier' => $this->supplierName, 'group' => $this->groupName]))
            ->line($this->intro(app()->getLocale()))
            ->action(__('Open my page'), $this->url)
            ->line($this->warning(app()->getLocale()));

        if (app()->isProduction()) {
            $message->from('help@aiku.io', $this->groupName);
        }

        if (app()->getLocale() !== 'en') {
            $message->line(new HtmlString(
                '<span style="font-size: 12px; color: #9ca3af;">English: '.e($this->intro('en')).' '.e($this->warning('en')).'</span>'
            ));
        }

        return $message;
    }

    private function intro(string $locale): string
    {
        return __('Here is your private link to see how the products of :supplier sell with :group, and your purchase orders.', ['supplier' => $this->supplierName, 'group' => $this->groupName], $locale);
    }

    private function warning(string $locale): string
    {
        return __('It works for :days days and only for you, so please do not forward it. If you did not ask for it, you can ignore this email.', ['days' => $this->days], $locale);
    }
}
