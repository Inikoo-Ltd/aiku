<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Inventory\LocationOrgStock;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent as the audit begins so every tab showing this location locks its controls, stopping two
 * people counting the same shelf into each other. BroadcastLowStockAudited releases them again.
 */
class BroadcastLowStockAuditedStart implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $data;

    private string $organisationSlug;

    private ?string $warehouseSlug;

    public function __construct(LocationOrgStock $locationOrgStock)
    {
        $this->organisationSlug = $locationOrgStock->organisation->slug;
        $this->warehouseSlug    = $locationOrgStock->warehouse?->slug;

        $orgStock = $locationOrgStock->orgStock;
        $location = $locationOrgStock->location;

        $this->data = [
            'org_stock_id'          => $orgStock?->id,
            'org_stock_code'        => $orgStock?->code,
            'warehouse_id'          => $locationOrgStock->warehouse_id,
            'warehouse_slug'        => $this->warehouseSlug,
            'location_org_stock_id' => $locationOrgStock->id,
            'location_id'           => $location?->id,
            'location_code'         => $location?->code,
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
        return 'low_stock_audited_start';
    }
}
