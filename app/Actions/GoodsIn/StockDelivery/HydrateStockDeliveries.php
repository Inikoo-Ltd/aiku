<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 00:52:25 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\HydrateModel;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Support\Collection;

class HydrateStockDeliveries extends HydrateModel
{
    public string $commandSignature = 'hydrate:stock-deliveries {organisations?*} {--i|id=}';

    public function handle(StockDelivery $stockDelivery): void
    {
        StockDeliveriesHydrateItems::run($stockDelivery);
    }

    protected function getModel(string $slug): StockDelivery
    {
        return StockDelivery::where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return StockDelivery::get();
    }
}
