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
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitHydrateStatusFromOrgStocks implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(TradeUnit $tradeUnit): string
    {
        return $tradeUnit->id;
    }


    public function handle(TradeUnit $tradeUnit): void
    {
        $oldStatus = $tradeUnit->status;
        $status    = $this->getTradeUnitStatusFromOrgStocks($tradeUnit);


        $tradeUnit->update([
            'status' => $status,
        ]);


        if ($tradeUnit->status !== $oldStatus) {
            GroupHydrateTradeUnits::dispatch($tradeUnit->group)->delay(now()->addSeconds(30));
        }
    }


    public function getTradeUnitStatusFromOrgStocks(TradeUnit $tradeUnit): TradeUnitStatusEnum
    {
        $numberOrgStocks              = 0;
        $numberDiscontinuedOrgStocks  = 0;
        $numberDiscontinuingOrgStocks = 0;
        $numberAbnormalityOrgStocks   = 0;
        foreach ($tradeUnit->orgStocks as $orgStock) {
            $numberOrgStocks++;
            if ($orgStock->state == OrgStockStateEnum::DISCONTINUED) {
                $numberDiscontinuedOrgStocks++;
            }
            if ($orgStock->state == OrgStockStateEnum::DISCONTINUING) {
                $numberDiscontinuingOrgStocks++;
            }
            if ($orgStock->state == OrgStockStateEnum::ABNORMALITY) {
                $numberAbnormalityOrgStocks++;
            }
        }

        if ($numberOrgStocks == 0) {
            return TradeUnitStatusEnum::IN_PROCESS;
        }

        if ($numberOrgStocks == $numberDiscontinuedOrgStocks) {
            return TradeUnitStatusEnum::DISCONTINUED;
        }

        if ($numberOrgStocks == $numberDiscontinuingOrgStocks) {
            return TradeUnitStatusEnum::DISCONTINUING;
        }

        if ($numberOrgStocks == $numberAbnormalityOrgStocks) {
            return TradeUnitStatusEnum::ANOMALITY;
        }

        return TradeUnitStatusEnum::ACTIVE;
    }

    public string $commandSignature = 'hydrate:trade_units_status {tradeUnitSlug?}';

    public function asCommand(Command $command): int
    {
        if ($command->argument('tradeUnitSlug')) {
            $tradeUnit = TradeUnit::where('slug', $command->argument('tradeUnitSlug'))->firstOrFail();
            $this->handle($tradeUnit);
            $tradeUnit->refresh();
            $command->info("Trade Unit $tradeUnit->code status -> {$tradeUnit->status->value}");

            return 0;
        }

        $command->withProgressBar(TradeUnit::all(), function (TradeUnit $tradeUnit) {
            $this->handle($tradeUnit);
        });

        $command->newLine();

        return 0;
    }


}
