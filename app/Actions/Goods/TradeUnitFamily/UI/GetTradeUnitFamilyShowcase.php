<?php

namespace App\Actions\Goods\TradeUnitFamily\UI;

use App\Http\Resources\Goods\TradeUnitFamilyResource;
use App\Models\Goods\TradeUnitFamily;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTradeUnitFamilyShowcase
{
    use AsObject;

    public function handle(TradeUnitFamily $tradeUnitFamily): array
    {
        return [
           'tradeUnitFamily' => TradeUnitFamilyResource::make($tradeUnitFamily)->resolve()
        ];
    }


}
