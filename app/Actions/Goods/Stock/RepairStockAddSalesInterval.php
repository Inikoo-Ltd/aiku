<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Apr 2025 15:41:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Goods\Stock;

class RepairStockAddSalesInterval
{
    use WithActionUpdate;



    protected function handle(Stock $stock): void
    {
        if (!$stock->salesIntervals) {
            $stock->salesIntervals()->create();
        }

    }

    public string $commandSignature = 'stocks:add_sales_interval';

    public function asCommand(): void
    {

        foreach (Stock::withTrashed()->orderBy('id')->get() as $stock) {
            $this->handle($stock);
        }
    }

}
