<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\GoodsIn\StockDelivery;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexStockDeliverySearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:stock_deliveries';


    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(StockDelivery::class, $reindex, $reset);
    }


}
