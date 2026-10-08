<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 7 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Production\Production;
use Illuminate\Notifications\Notification;

class PartnerLinePokedNotification extends Notification
{
    public function __construct(public Production $production, public string $buyerName, public string $note)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => __(':buyer needs this urgently', ['buyer' => $this->buyerName]),
            'body'  => $this->note,
            'type'  => 'partner_line_poked',
            'slug'  => (string) $this->production->id,
            'route' => [
                'name'       => 'grp.org.productions.show.to_produce.index',
                'parameters' => [
                    $this->production->organisation->slug,
                    $this->production->slug,
                ],
            ],
        ];
    }
}
