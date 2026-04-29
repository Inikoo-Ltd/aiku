<?php

namespace App\Actions\Goods\TradeUnit;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Goods\TradeUnitFamily;

class HydrateTradeUnitBrandTagsFromFamily
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:trade_units {--s|slug=}';

    public function __construct()
    {
        $this->model = TradeUnitFamily::class;
    }

    public function handle(TradeUnitFamily $tradeUnitFamily): void
    {
        $tradeUnitFamily->refresh();
        
        $newData = [
            'brands' => $tradeUnitFamily->brand()?->id,
            'tags'   => $tradeUnitFamily->tags?->pluck('id')?->toArray() ?? []
        ];

        foreach ($tradeUnitFamily->tradeUnits as $tradeUnit) {
            UpdateTradeUnit::make()->action($tradeUnit, $newData);
        }
    }
}
