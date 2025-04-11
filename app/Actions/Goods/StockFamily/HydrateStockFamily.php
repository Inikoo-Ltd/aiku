<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\StockFamily;

use App\Actions\Goods\StockFamily\Hydrators\StockFamilyHydrateSalesIntervals;
use App\Actions\Goods\StockFamily\Hydrators\StockFamilyHydrateStocks;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Goods\StockFamily;

class HydrateStockFamily
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:stock_families {--s|slugs=}';

    public function __construct()
    {
        $this->model = StockFamily::class;
    }

    public function handle(StockFamily $stockFamily): void
    {
        StockFamilyHydrateStocks::run($stockFamily);
        StockFamilyHydrateSalesIntervals::run($stockFamily);
    }



}
