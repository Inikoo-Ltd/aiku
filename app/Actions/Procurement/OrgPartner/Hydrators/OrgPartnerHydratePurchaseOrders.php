<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 20:16:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgPartnerHydratePurchaseOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(OrgPartner $orgPartner): string
    {
        return $orgPartner->id;
    }

    public function handle(OrgPartner $orgPartner): void
    {
        $stats = [
            'number_purchase_orders' => $orgPartner->purchaseOrders()->count(),
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'purchase_orders',
            field: 'state',
            enum: PurchaseOrderStateEnum::class,
            models: PurchaseOrder::class,
            where: function ($q) use ($orgPartner) {
                $q->where('parent_id', $orgPartner->id)->where('parent_type', 'OrgPartner');
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'purchase_orders',
            field: 'delivery_state',
            enum: PurchaseOrderDeliveryStateEnum::class,
            models: PurchaseOrder::class,
            where: function ($q) use ($orgPartner) {
                $q->where('parent_id', $orgPartner->id)->where('parent_type', 'OrgPartner');
            }
        ));

        $orgPartner->stats()->update($stats);
    }


}
