<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Catalogue\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class BroadcastProductAvailability implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public array $data;
    private string $organisationSlug;

    public function __construct(Product $product)
    {
        $this->organisationSlug = $product->organisation->slug;
        $this->data             = [
            'product_id'         => $product->id,
            'available_quantity' => $product->available_quantity,
            'is_on_demand'       => $product->is_on_demand,
            'status'             => $product->status?->value,
        ];
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("grp.$this->organisationSlug.product_availability");
    }

    public function broadcastAs(): string
    {
        return 'product_availability';
    }
}
