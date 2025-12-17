<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:36:28 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\HydrateModel;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Collection;

class HydratePurchaseOrder extends HydrateModel
{
    public string $commandSignature = 'hydrate:purchase-order {organisations?*} {--s|slugs=}';

    public function handle(PurchaseOrder $purchaseOrder): void
    {
        PurchaseOrderHydrateTransactions::run($purchaseOrder);
    }

    protected function getModel(string $slug): PurchaseOrder
    {
        return PurchaseOrder::where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return PurchaseOrder::get();
    }
}
