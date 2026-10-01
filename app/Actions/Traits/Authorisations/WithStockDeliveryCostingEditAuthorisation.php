<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\GoodsIn\StockDelivery;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

trait WithStockDeliveryCostingEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $accountingManager = "org-supervisor.{$this->organisation->id}.accounting";

        if (Arr::has($this->costingStockDelivery($request)?->data ?? [], 'costing_reopened')) {
            return $request->user()->authTo($accountingManager);
        }

        return $request->user()->authTo([
            "procurement.{$this->organisation->id}.edit",
            $accountingManager,
        ]);
    }

    private function costingStockDelivery(ActionRequest $request): ?StockDelivery
    {
        return $request->route('stockDelivery')
            ?? $request->route('stockDeliveryItem')?->stockDelivery
            ?? $request->route('stockDeliveryCost')?->stockDelivery;
    }
}
