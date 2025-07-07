<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 19:23:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Actions\Traits\WithImageStats;
use App\Models\Goods\TradeUnit;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitHydrateImages implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithImageStats;

    public function getJobUniqueId(TradeUnit $tradeUnit): string
    {
        return $tradeUnit->id;
    }

    public function handle(TradeUnit $tradeUnit): void
    {
        $stats = $this->calculateImageStatsUsingDB(
            model: $tradeUnit,
            modelType: 'TradeUnit',
            hasPublicImages: false,
            useTotalImageSize: true
        );

        $tradeUnit->stats()->update($stats);
    }
}
