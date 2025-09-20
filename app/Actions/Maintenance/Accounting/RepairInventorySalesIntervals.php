<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Sept 2025 10:00:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;

class RepairInventorySalesIntervals
{
    use WithActionUpdate;


    public string $commandSignature = 'repair:inventory_sales_intervals';

    public function asCommand(): void
    {
        OrgStock::withTrashed()->orderBy('id')
            ->chunk(1000, function ($models) {
                foreach ($models as $model) {
                    if (!$model->salesIntervals) {
                        $model->salesIntervals()->create();
                    }
                }
            });
        OrgStockFamily::withTrashed()->orderBy('id')
            ->chunk(1000, function ($models) {
                foreach ($models as $model) {
                    if (!$model->salesIntervals) {
                        $model->salesIntervals()->create();
                    }
                }
            });
    }

}
