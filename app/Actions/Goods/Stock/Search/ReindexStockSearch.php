<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-11-2024, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Goods\Stock\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Goods\Stock;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexStockSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:stocks';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Stock::class, $reindex, $reset);
    }
}
