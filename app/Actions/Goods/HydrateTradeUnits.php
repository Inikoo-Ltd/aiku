<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 09 Feb 2022 15:04:15 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Actions\Goods;

use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitHydrateStatus;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateCustomerExclusiveProducts;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateOrgStocks;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateProducts;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Goods\TradeUnit;

class HydrateTradeUnits
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:trade_units {--s|slug=}';

    public function __construct()
    {
        $this->model = TradeUnit::class;
    }

    public function handle(TradeUnit $tradeUnit): void
    {
        TradeUnitsHydrateCustomerExclusiveProducts::run($tradeUnit);
        TradeUnitsHydrateProducts::run($tradeUnit);
        TradeUnitsHydrateOrgStocks::run($tradeUnit);
        TradeUnitHydrateStatus::run($tradeUnit);
    }

}
