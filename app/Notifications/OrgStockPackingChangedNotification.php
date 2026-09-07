<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Inventory\Warehouse;
use App\Models\Inventory\OrgStock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrgStockPackingChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrgStock $orgStock,
        public Warehouse $warehouse,
        public string $body
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => __('Packing changed: :code', ['code' => $this->orgStock->code]),
            'body'  => $this->body,
            'type'  => 'inventory',
            'slug'  => $this->orgStock->slug,
            'route' => [
                'name'       => 'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show',
                'parameters' => [
                    $this->orgStock->organisation->slug,
                    $this->warehouse->slug,
                    $this->orgStock->slug,
                ],
            ],
        ];
    }
}
