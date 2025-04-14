<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Sept 2024 14:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Actions\Goods\Stock\Hydrators\StockHydrateGrossWeightFromTradeUnits;
use App\Actions\Goods\Stock\Hydrators\StockHydrateSalesIntervals;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Goods\Stock;

class HydrateStock
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:stocks';

    public function __construct()
    {
        $this->model = Stock::class;
    }

    public function handle(Stock $stock): void
    {
        StockHydrateGrossWeightFromTradeUnits::run($stock);
        StockHydrateSalesIntervals::run($stock);
    }

}
