<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Dec 2025 22:34:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnitFamily;

use App\Actions\Goods\TradeUnitFamily\Hydrators\TradeUnitFamilyHydrateTradeUnits;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Goods\TradeUnitFamily;

class HydrateTradeUnitFamilies
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:trade_unit_families {--s|slug=}';

    public function __construct()
    {
        $this->model = TradeUnitFamily::class;
    }

    public function handle(TradeUnitFamily $tradeUnitFamily): void
    {
        TradeUnitFamilyHydrateTradeUnits::run($tradeUnitFamily);
    }

}
