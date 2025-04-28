<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 17:47:10 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Actions\Goods\Stock\Hydrators\StockHydrateUniversalSearch;
use App\Models\Goods\Stock;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStockUniversalSearch
{
    use asAction;

    public string $commandSignature = 'stocks:search';

    public function handle(Stock $stock): void
    {
        StockHydrateUniversalSearch::run($stock);
    }

    public function asCommand(Command $command): int
    {

        $command->withProgressBar(Stock::all(), function (Stock $stock) {
            $this->handle($stock);
        });
        return 0;
    }
}
