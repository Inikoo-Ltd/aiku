<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Nov 2023 15:04:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Models\Helpers\Query;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateQueries implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }


    public function handle(Shop $shop): void
    {
        $stats = [
            'number_customer_queries'  => Query::where('shop_id', $shop->id)->where('model', 'Customer')->count(),
            'number_prospect_queries'  => Query::where('shop_id', $shop->id)->where('model', 'Prospect')->count(),
        ];


        $shop->crmStats()->update($stats);
    }


}
