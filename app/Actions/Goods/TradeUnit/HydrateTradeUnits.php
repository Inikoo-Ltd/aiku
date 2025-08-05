<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Aug 2025 20:28:01 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitHydrateImages;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitHydrateStatus;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateCustomerExclusiveProducts;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateMarketingIngredients;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateOrgStocks;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateProducts;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateStocks;
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
        TradeUnitsHydrateStocks::run($tradeUnit);
        TradeUnitHydrateStatus::run($tradeUnit);
        TradeUnitHydrateImages::run($tradeUnit);
        TradeUnitsHydrateMarketingIngredients::run($tradeUnit);
    }

}
