<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Mar 2023 05:16:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\Hydrators;

use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTradeUnits;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitStats;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitHydrateStatus implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(TradeUnit $tradeUnit): string
    {
        return $tradeUnit->id;
    }


    public function handle(TradeUnit $tradeUnit): void
    {
        if ($tradeUnit->status == TradeUnitStatusEnum::ANOMALITY) {
            return;
        }

        $status = $this->getTradeUnitStatusFromChildren($tradeUnit->stats);

        $tradeUnit->update([
            'status' => $status,
        ]);


        if ($tradeUnit->wasChanged('status')) {
            GroupHydrateTradeUnits::dispatch($tradeUnit->group)->delay(now()->addSeconds(30));
        }
    }


    public function getTradeUnitStatusFromChildren(TradeUnitStats $stats): TradeUnitStatusEnum
    {


        if ( $stats->number_current_stocks >0  && ($stats->number_current_products>0 || $stats->number_customer_exclusive_current_products>0    )   ) {
            return TradeUnitStatusEnum::ACTIVE;
        }


        if ($stats->number_stocks_state_discontinued == 0 && $stats->number_stocks_state_suspended == 0  &&  $stats->number_products_state_discontinued==0 && $stats->number_customer_exclusive_products_state_discontinued  ) {
            return TradeUnitStatusEnum::IN_PROCESS;
        }

        return TradeUnitStatusEnum::DISCONTINUED;
    }



}
