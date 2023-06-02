<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 22:41:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Warehouse\Hydrators;

use App\Actions\WithTenantJob;
use App\Models\Inventory\Warehouse;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydrateStocks implements ShouldBeUnique
{
    use AsAction;
    use WithTenantJob;

    public function handle(Warehouse $warehouse): void
    {

        $warehouse->stats->update(
            [
                'stock_value' => $warehouse->locations()->sum('stock_value')
            ]
        );
    }

    public function getJobUniqueId(Warehouse $warehouse): int
    {
        return $warehouse->id;
    }
}
