<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To a manager: a guru said answering Merlin's questions is not their job.
 */
class ShoppingAssistantGuruRefusedNotification extends Notification
{
    use SendsMailThroughSes;

    public function __construct(public User $guru, public Organisation $organisation, public ?Shop $shop = null)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'assistant_question',
            'route' => $this->url(),
        ];
    }

    public function title(): string
    {
        return __(':name does not want to answer Merlin\'s questions', ['name' => $this->guru->contact_name ?: $this->guru->username]);
    }

    public function body(): string
    {
        $name = $this->guru->contact_name ?: $this->guru->username;

        if ($this->shop) {
            return __(':name said answering the questions customers ask Merlin is not their job. They are still the guru of :shop and keep getting its questions. To choose someone else, change the guru in the shop settings.', [
                'name' => $name,
                'shop' => $this->shop->name,
            ]);
        }

        return __(':name said answering the questions customers ask Merlin is not their job. They are still :organisation\'s guru and keep getting the questions. To choose someone else, change the question person in the organisation settings.', [
            'name'         => $name,
            'organisation' => $this->organisation->name,
        ]);
    }

    public function url(): string
    {
        if ($this->shop) {
            return route('grp.org.shops.show.settings.edit', [$this->organisation->slug, $this->shop->slug]);
        }

        return route('grp.organisations.edit', $this->organisation->slug);
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()->subject($this->title())->line($this->body())->action($this->shop ? __('Shop settings') : __('Organisation settings'), $this->url());
    }
}
