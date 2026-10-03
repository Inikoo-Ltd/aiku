<?php

namespace App\Actions\Goods\Stock;

use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\Concerns\AsObject;

class SyncStockCosmeticFromCpnp
{
    use AsObject;

    public function handle(TradeUnit $tradeUnit): void
    {
        $tradeUnit->stocks()->get()->each(function (Stock $stock) {
            $isCosmetic = $stock->tradeUnits()->whereNotNull('cpnp_number')->where('cpnp_number', '!=', '')->exists();
            if ($stock->is_cosmetic !== $isCosmetic) {
                $stock->update(['is_cosmetic' => $isCosmetic]);
            }
        });
    }
}
