<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 10 May 2023 14:06:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\Traits;

use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateStockDeliveries;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateStockDeliveries;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateStockDeliveries;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydrateStockDeliveries;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStockDeliveries;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateStockDeliveries;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;

trait HasStockDeliveryHydrators
{
    public function runStockDeliveryHydrators(StockDelivery $stockDelivery): void
    {
        /** @var OrgSupplier|OrgAgent|OrgPartner $parent */
        $parent = $stockDelivery->parent;

        if (class_basename($parent) == 'OrgSupplier') {
            OrgSupplierHydrateStockDeliveries::dispatch($parent)->delay($this->hydratorsDelay);
            SupplierHydrateStockDeliveries::dispatch($parent->supplier)->delay($this->hydratorsDelay);
        } elseif (class_basename($parent) == 'OrgAgent') {
            OrgAgentHydrateStockDeliveries::dispatch($parent)->delay($this->hydratorsDelay);
            AgentHydrateStockDeliveries::dispatch($parent->agent)->delay($this->hydratorsDelay);
        }

        OrganisationHydrateStockDeliveries::dispatch($stockDelivery->organisation)->delay($this->hydratorsDelay);
        GroupHydrateStockDeliveries::dispatch($stockDelivery->group)->delay($this->hydratorsDelay);
    }
}
