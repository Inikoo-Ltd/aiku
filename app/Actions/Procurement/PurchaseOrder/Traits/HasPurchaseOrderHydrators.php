<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 09 May 2023 17:02:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\Traits;

use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydratePurchaseOrders;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydratePurchaseOrders;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydratePurchaseOrders;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydratePurchaseOrders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePurchaseOrders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePurchaseOrders;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;

trait HasPurchaseOrderHydrators
{
    public function purchaseOrderHydrate(PurchaseOrder $purchaseOrder): void
    {
        /** @var OrgSupplier|OrgAgent|OrgPartner $parent */
        $parent = $purchaseOrder->parent;

        if (class_basename($parent) == 'OrgSupplier') {
            OrgSupplierHydratePurchaseOrders::dispatch($parent);
            SupplierHydratePurchaseOrders::dispatch($parent->supplier);
        } elseif (class_basename($parent) == 'OrgAgent') {
            OrgAgentHydratePurchaseOrders::dispatch($parent);
            AgentHydratePurchaseOrders::dispatch($parent->agent);
        }
        GroupHydratePurchaseOrders::dispatch($purchaseOrder->group);
        OrganisationHydratePurchaseOrders::dispatch($purchaseOrder->organisation);
    }
}
