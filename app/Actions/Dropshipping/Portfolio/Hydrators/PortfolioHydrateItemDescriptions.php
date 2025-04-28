<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Apr 2025 12:49:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio\Hydrators;

use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PortfolioHydrateItemDescriptions implements ShouldBeUnique
{
    use AsAction;


    public function getJobUniqueId(Portfolio $portfolio): string
    {
        return $portfolio->id;
    }


    public function handle(Portfolio $portfolio): void
    {

        if(!$portfolio->item_type || !$portfolio->item_id){
            return ;
        }

        /** @var Product|StoredItem $item */
        $item = $portfolio->item;

        $code = $item instanceof StoredItem ? $item->reference : $item->code;
        $name = $item->name;

        $portfolio->update(
            [
                'code' => $code,
                'name' => $name,
            ]
        );
    }
}
