<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 7 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Procurement\OrgPartner;
use Illuminate\Notifications\Notification;

class ToProduceItemsCantBeDoneNotification extends Notification
{
    public function __construct(public OrgPartner $orgPartner, public string $note)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => __(':partner cannot make some of your lines', ['partner' => $this->orgPartner->partner->name]),
            'body'  => $this->note,
            'type'  => 'to_produce_cant_be_done',
            'slug'  => (string) $this->orgPartner->id,
            'route' => [
                'name'       => 'grp.org.procurement.org_partners.show.shopping_list.sent',
                'parameters' => [
                    $this->orgPartner->organisation->slug,
                    $this->orgPartner->id,
                ],
            ],
        ];
    }
}
