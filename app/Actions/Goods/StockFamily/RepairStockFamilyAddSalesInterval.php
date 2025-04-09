<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Goods\StockFamily;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Goods\StockFamily;

class RepairStockFamilyAddSalesInterval
{
    use WithActionUpdate;



    protected function handle(StockFamily $stockFamily): void
    {
        if (!$stockFamily->salesIntervals) {
            $stockFamily->salesIntervals()->create();
        }

    }

    public string $commandSignature = 'stock_families:add_sales_interval';

    public function asCommand(): void
    {

        foreach (StockFamily::all() as $stockFamily) {
            $this->handle($stockFamily);
        }
    }

}
