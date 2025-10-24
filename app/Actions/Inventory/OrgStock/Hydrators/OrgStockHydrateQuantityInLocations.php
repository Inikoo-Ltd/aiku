<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Jun 2023 21:00:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockHydrateQuantityInLocations implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(OrgStock $orgStock): int
    {
        return $orgStock->id;
    }

    public function handle(OrgStock $orgStock): void
    {
        $quantityInLocations = $orgStock->locationOrgStocks()->sum('quantity');

        $quantityAvailable = $quantityInLocations - $orgStock->quantity_in_submitted_orders - $orgStock->quantity_to_be_picked;

        if ($quantityAvailable < 0) {
            $quantityAvailable = 0;
        }

        $orgStock->update([
            'quantity_in_locations' => $quantityInLocations,
            'quantity_available'    => $quantityAvailable,
        ]);

        if ($orgStock->wasChanged('quantity_in_locations')) {
            foreach ($orgStock->products as $product) {
                ProductHydrateAvailableQuantity::run($product);
            }
        }
    }


}
