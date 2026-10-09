<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 09 May 2023 17:02:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\Traits;

use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydratePurchaseOrders;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydratePurchaseOrders;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydratePurchaseOrders;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydratePurchaseOrders;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydratePurchaseOrders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePurchaseOrders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePurchaseOrders;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;

trait HasPurchaseOrderHydrators
{
    public function purchaseOrderHydrate(PurchaseOrder $purchaseOrder): void
    {
        /** @var OrgSupplier|OrgPartner $parent */
        $parent = $purchaseOrder->parent;

        if (class_basename($parent) == 'OrgSupplier') {
            OrgSupplierHydratePurchaseOrders::dispatch($parent)->afterCommit();
            SupplierHydratePurchaseOrders::dispatch($parent->supplier)->afterCommit();
            if ($orgAgent = $purchaseOrder->orgAgentOfOrder()) {
                OrgAgentHydratePurchaseOrders::dispatch($orgAgent)->afterCommit();
                AgentHydratePurchaseOrders::dispatch($orgAgent->agent)->afterCommit();
            }
        } elseif (class_basename($parent) == 'OrgPartner') {
            OrgPartnerHydratePurchaseOrders::dispatch($parent)->afterCommit();
        }
        GroupHydratePurchaseOrders::dispatch($purchaseOrder->group)->afterCommit();
        OrganisationHydratePurchaseOrders::dispatch($purchaseOrder->organisation)->afterCommit();
    }
}
