<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Dispatching\DeliveryNote;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UpdateOrderNotesEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public DeliveryNote $deliveryNote;

    public function __construct(DeliveryNote $deliveryNote)
    {
        $this->deliveryNote         = $deliveryNote;
    }


    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.dn.' . $this->deliveryNote->id)
        ];
    }

    public function broadcastWith(): array
    {
        return [
            "note_list" => [
                [
                    "note"     => $this->deliveryNote->shipping_notes ?? '',
                    "field"    => "shipping_notes"
                ],
                [
                    "note"     => $this->deliveryNote->customer_notes ?? '',
                    "field"    => "customer_notes"
                ],
                [
                    "note"     => $this->deliveryNote->public_notes ?? '',
                    "field"    => "public_notes"
                ],
                [
                    "note"     => $this->deliveryNote->internal_notes ?? '',
                    "field"    => "internal_notes"
                ]
            ]
        ];
    }

    public function broadcastAs(): string
    {
        return 'dn-note-update';
    }
}
