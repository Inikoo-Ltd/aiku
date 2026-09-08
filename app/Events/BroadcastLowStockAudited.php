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
 * An audit can be done from the low stock list or from the stock controller on the SKO itself,
 * and either one leaves the other stale. Both ends listen to this so whichever tab is open
 * catches up with the count that was just made.
 */
class BroadcastLowStockAudited implements ShouldBroadcastNow
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
            'org_stock_slug'        => $orgStock?->slug,
            'org_stock_code'        => $orgStock?->code,
            'warehouse_id'          => $locationOrgStock->warehouse_id,
            'warehouse_slug'        => $this->warehouseSlug,
            'location_org_stock_id' => $locationOrgStock->id,
            'location_id'           => $location?->id,
            'location_code'         => $location?->code,
            'quantity'              => (float) $locationOrgStock->quantity,
            'audited_at'            => $locationOrgStock->audited_at,
            'is_low_stock_checked'  => (bool) $locationOrgStock->is_low_stock_checked,
        ];
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }

    /**
     * Scoped to the warehouse: an audit only concerns the people counting that warehouse, and
     * the pages that show it are warehouse pages.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("grp.{$this->organisationSlug}.warehouse.{$this->warehouseSlug}.low_stock_audit");
    }

    public function broadcastAs(): string
    {
        return 'low_stock_audited';
    }
}
