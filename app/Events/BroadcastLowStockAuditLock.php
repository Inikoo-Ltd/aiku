<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Inventory\Warehouse;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Someone has started counting before anything has been saved: a quantity being typed into the
 * low stock list, or the audit modal being opened on the SKO. It holds the other view shut so
 * two people do not count the same shelf at once, and is sent again with is_locked false when
 * they stop.
 */
class BroadcastLowStockAuditLock implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $data;

    private string $organisationSlug;

    private string $warehouseSlug;

    public function __construct(Warehouse $warehouse, array $modelData)
    {
        $this->organisationSlug = $warehouse->organisation->slug;
        $this->warehouseSlug    = $warehouse->slug;

        $this->data = [
            'org_stock_id'          => (int) data_get($modelData, 'org_stock_id'),
            'location_org_stock_id' => data_get($modelData, 'location_org_stock_id'),
            'is_locked'             => (bool) data_get($modelData, 'is_locked'),
            'source'                => data_get($modelData, 'source'),
        ];
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("grp.{$this->organisationSlug}.warehouse.{$this->warehouseSlug}.low_stock_audit");
    }

    public function broadcastAs(): string
    {
        return 'low_stock_audit_lock';
    }
}
