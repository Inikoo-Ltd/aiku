<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Apr 2026 16:14:01 Nepal Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnitFamily\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Goods\TradeUnitFamily;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexTradeUnitFamiliesSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:trade_unit_families';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(TradeUnitFamily::class, $reindex, $reset);
    }

}
