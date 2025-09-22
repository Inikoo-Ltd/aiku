<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 22 Jan 2024 10:20:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnitFamily\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitFamilyHydrateTradeUnits implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(TradeUnitFamily $tradeUnitFamily): string
    {
        return $tradeUnitFamily->id;
    }

    public function handle(TradeUnitFamily $tradeUnitFamily): void
    {
        $stats = [
            'number_trade_units' => $tradeUnitFamily->tradeUnits()->count()
        ];
        
        $tradeUnitFamily->stats()->update(
            $stats
        );
    }
}
