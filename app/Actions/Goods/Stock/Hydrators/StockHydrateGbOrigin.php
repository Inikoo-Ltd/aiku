<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\Hydrators;

use App\Models\Goods\Stock;
use Lorisleiva\Actions\Concerns\AsObject;

class StockHydrateGbOrigin
{
    use AsObject;

    /**
     * A SKO is GB-origin when any of its trade units is made in GB, the country the invoices print.
     */
    public function handle(Stock $stock): void
    {
        $isGbOrigin = $stock->tradeUnits()->whereRelation('countryOrigin', 'code', 'GB')->exists();

        if ($stock->is_gb_origin !== $isGbOrigin) {
            $stock->update(['is_gb_origin' => $isGbOrigin]);
        }
    }
}
