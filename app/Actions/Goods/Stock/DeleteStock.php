<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Actions\Goods\StockFamily\Hydrators\StockFamilyHydrateStocks;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStocks;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithOrganisationArgument;
use App\Models\Goods\Stock;
use Exception;
use Illuminate\Console\Command;

class DeleteStock
{
    use WithActionUpdate;
    use WithOrganisationArgument;
    use WithGoodsEditAuthorisation;

    public string $commandSignature = 'delete:stock {stock}';

    public function handle(Stock $stock, array $deletedData = [], bool $skipHydrate = false): Stock
    {
        $stock->delete();
        $stock = $this->update($stock, $deletedData, ['data']);

        if (!$skipHydrate) {
            GroupHydrateStocks::dispatch(group());
            if ($stock->stock_family_id) {
                StockFamilyHydrateStocks::dispatch($stock->stockFamily);
            }
        }

        return $stock;
    }


    public function asCommand(Command $command): int
    {

        try {
            /** @var Stock $stock */
            $stock = Stock::findOrFail($command->argument('stock'));
        } catch (Exception $e) {
            $command->error('Stock not found '.$e->getMessage());
            return 1;
        }

        $this->handle($stock);

        return 0;
    }
}
