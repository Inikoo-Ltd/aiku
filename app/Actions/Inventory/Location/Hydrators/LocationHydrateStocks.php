<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 22:54:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\Hydrators;

use App\Models\Inventory\Location;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class LocationHydrateStocks implements ShouldBeUnique
{
    use AsAction;


    public function getJobUniqueId(Location $location): string
    {
        return $location->id;
    }

    public function handle(Location $location): void
    {
        $location->update(
            [
                'has_stock_slots'        => $location->locationOrgStocks()->where('dropshipping_pipe', false)->count() > 0,
                'has_dropshipping_slots' => $location->locationOrgStocks()->where('dropshipping_pipe', true)->count()  > 0
            ]
        );


        $stats = [
            'number_org_stock_slots' => $location->locationOrgStocks()->count()
        ];

        $location->stats()->update($stats);
    }

}
