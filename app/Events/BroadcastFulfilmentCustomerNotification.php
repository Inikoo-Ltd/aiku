<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Group;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastFulfilmentCustomerNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $data;

    public Group $group;

    public PalletDelivery|PalletReturn $parent;

    public function __construct(Group $group, PalletDelivery|PalletReturn $parent)
    {
        $this->parent = $parent;
        $this->group = $group;
        $this->data = [
            'title' => $parent->state->notifications($parent->reference)[$parent->state->value]['title'],
            'body' => $parent->state->notifications($parent->reference)[$parent->state->value]['subtitle'],
            'type' => class_basename($parent),
            'slug' => $parent->slug,
            'id' => $parent->id,
            'route' => match (class_basename($parent)) {
                'PalletDelivery' => route('grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show', [
                    'organisation' => $parent->organisation->slug,
                    'fulfilment' => $parent->fulfilment->slug,
                    'fulfilmentCustomer' => $parent->fulfilmentCustomer->slug,
                    $parent->slug,
                ]),
                'PalletReturn' => route('grp.org.fulfilments.show.crm.customers.show.pallet_returns.show', [
                    'organisation' => $parent->organisation->slug,
                    'fulfilment' => $parent->fulfilment->slug,
                    'fulfilmentCustomer' => $parent->fulfilmentCustomer->slug,
                    $parent->slug,
                ]),
                default => null
            },
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.'.$this->group->id.".fulfilmentCustomer.{$this->parent->fulfilmentCustomer->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return class_basename($this->parent);
    }
}
