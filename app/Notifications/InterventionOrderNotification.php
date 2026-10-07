<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 7 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Procurement\OrgPartner;
use Illuminate\Notifications\Notification;

class InterventionOrderNotification extends Notification
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
            'title' => __('Order placed on your behalf · :partner', ['partner' => $this->orgPartner->partner->name]),
            'body'  => $this->note.'. '.__('They are marked as suggested: submit to keep them, or drop them.'),
            'type'  => 'intervention_order',
            'slug'  => (string) $this->orgPartner->id,
            'route' => [
                'name'       => 'grp.org.procurement.org_partners.show.shopping_list.index',
                'parameters' => [
                    $this->orgPartner->organisation->slug,
                    $this->orgPartner->id,
                ],
            ],
        ];
    }
}
