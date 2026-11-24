<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 7 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Procurement\OrgPartner;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ToProduceItemsCantBeDoneNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = false;

    public function __construct(public OrgPartner $orgPartner, public string $note)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return __(':partner cannot make some of your lines', ['partner' => $this->orgPartner->partner->name]);
    }

    public function body(): string
    {
        return $this->note;
    }

    public function url(): string
    {
        return route($this->route()['name'], $this->route()['parameters']);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'to_produce_cant_be_done',
            'slug'  => (string) $this->orgPartner->id,
            'route' => $this->route(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()->subject($this->title())
            ->line($this->plainLine($this->note))
            ->line($this->plainLine(__('A line they cannot make at all is off your Orders list now, so you can order it elsewhere; pick "Cannot be made" on the Orders page to see it again. Where they made only part of a line, the line stays on Orders with the missing part marked "Cannot be made".')))
            ->action(__('Open the lines'), $this->url())
            ->line(__('You get this email because you added lines for :partner that it cannot make.', ['partner' => $this->orgPartner->partner->name]));
    }

    /**
     * @return array{name: string, parameters: array<string, mixed>}
     */
    private function route(): array
    {
        return [
            'name'       => 'grp.org.procurement.org_partners.show.shopping_list.sent',
            'parameters' => [
                'organisation' => $this->orgPartner->organisation->slug,
                'orgPartner'   => $this->orgPartner->id,
                'filter'       => ['state' => 'dismissed'],
            ],
        ];
    }
}
