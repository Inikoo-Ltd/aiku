<?php

/*
 * Author Louis Perez
 * Created on 08-07-2026-15h-35m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Events;

use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastStockMovement implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $data;
    private Organisation $organisation;

    public function __construct(LocationOrgStock $locationOrgStock)
    {
        $this->organisation = $locationOrgStock->organisation;
        $orgStock     = $locationOrgStock?->orgStock;
        $location     = $locationOrgStock->location;

        $this->data     = [
            'title'             => "Stock Updated",
            'body'              => "Stock data updated for Item: {$orgStock->code}",
            'affected_data'     => [
                'org_stock_id'      => $orgStock->id,
                'location_id'       => $location->id,
                'new_quantity'      => $locationOrgStock->quantity,
            ]
        ];
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("grp.{$this->organisation->slug}.stock_movement");
    }

    public function broadcastAs(): string
    {
        return 'stock_update';
    }
}
