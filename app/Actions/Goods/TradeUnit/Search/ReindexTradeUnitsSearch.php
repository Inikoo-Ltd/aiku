<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Apr 2026 16:07:28 Nepal Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexTradeUnitsSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:trade_units';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(TradeUnit::class, $reindex, $reset);
    }

}
