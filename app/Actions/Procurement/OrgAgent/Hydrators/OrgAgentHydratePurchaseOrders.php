<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 17:14:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgAgentHydratePurchaseOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(OrgAgent $orgAgent): string
    {
        return $orgAgent->id;
    }

    public function handle(OrgAgent $orgAgent): void
    {
        $stats = [
            'number_purchase_orders' => $orgAgent->purchaseOrders()->count(),
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'purchase_orders',
            field: 'state',
            enum: PurchaseOrderStateEnum::class,
            models: PurchaseOrder::class,
            where: function ($q) use ($orgAgent) {
                $q->where('parent_id', $orgAgent->id)->where('parent_type', 'OrgAgent');
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'purchase_orders',
            field: 'delivery_state',
            enum: PurchaseOrderDeliveryStateEnum::class,
            models: PurchaseOrder::class,
            where: function ($q) use ($orgAgent) {
                $q->where('parent_id', $orgAgent->id)->where('parent_type', 'OrgAgent');
            }
        ));

        $orgAgent->stats()->update($stats);
    }


}
