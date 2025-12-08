<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 10 May 2023 14:06:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\StockDelivery\Traits;

use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateStockDeliveries;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydrateStockDeliveries;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\StockDelivery;

trait HasStockDeliveryHydrators
{
    public function runStockDeliveryHydrators(StockDelivery $stockDelivery): void
    {
        /** @var OrgSupplier|OrgAgent|OrgPartner $parent */
        $parent = $stockDelivery->parent;

        if (class_basename($parent) == 'OrgSupplier') {
            SupplierHydrateStockDeliveries::dispatch($parent->supplier)->delay($this->hydratorsDelay);
        } elseif (class_basename($parent) == 'OrgAgent') {
            AgentHydrateStockDeliveries::dispatch($parent->agent)->delay($this->hydratorsDelay);
        }

    }
}
