<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Mar 2026 20:39:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class FixProductTradeUnitsFromMaster
{
    use AsAction;

    public function handle(MasterAsset $masterProduct): void
    {
        $tradeUnitData = [];
        foreach ($masterProduct->tradeUnits as $tradeUnit) {
            $tradeUnitData[] = [
                'id'       => $tradeUnit->id,
                'quantity' => data_get($tradeUnit, 'pivot.quantity'),
            ];
        }

        foreach ($masterProduct->products as $product) {
            SyncProductTradeUnits::run($product, $tradeUnitData);
        }

    }

    public string $commandSignature = 'master_product:fix_shop_products_trade_units {masterProduct}';

    public function asCommand(Command $command): void
    {
        $masterProduct = MasterAsset::where('slug', $command->argument('masterProduct'))->firstOrFail();
        $this->handle($masterProduct);
    }

}
