<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Aug 2024 12:03:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockFamily\Hydrators;

use App\Actions\Inventory\OrgStockFamily\UpdateOrgStockFamily;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockFamilyHydrateOrgStocks implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public function getJobUniqueId(OrgStockFamily $orgStockFamily): string
    {
        return $orgStockFamily->id;
    }

    public function handle(OrgStockFamily $orgStockFamily): void
    {
        $stats = [
            'number_org_stocks' => $orgStockFamily->orgStocks()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'org_stocks',
                field: 'state',
                enum: OrgStockStateEnum::class,
                models: OrgStock::class,
                where: function ($q) use ($orgStockFamily) {
                    $q->where('org_stock_family_id', $orgStockFamily->id);
                }
            )
        );




        UpdateOrgStockFamily::make()->action(
            $orgStockFamily,
            [
                'state' => $this->getOrgStockFamilyState($stats)
            ]
        );

        $stats['number_current_org_stocks'] = Arr::get($stats, 'number_org_stocks_state_active', 0) + Arr::get($stats, 'number_org_stocks_state_discontinuing', 0);
        $orgStockFamily->stats()->update($stats);
    }

    public function getOrgStockFamilyState($stats): OrgStockFamilyStateEnum
    {
        if ($stats['number_org_stocks'] == 0) {
            return OrgStockFamilyStateEnum::IN_PROCESS;
        }

        if (Arr::get($stats, 'number_org_stocks_state_active', 0) > 0) {
            return OrgStockFamilyStateEnum::ACTIVE;
        }

        if (Arr::get($stats, 'number_org_stocks_state_discontinuing', 0) > 0) {
            return OrgStockFamilyStateEnum::DISCONTINUING;
        }

        if (Arr::get($stats, 'number_org_stocks_state_in_process', 0) > 0) {
            return OrgStockFamilyStateEnum::IN_PROCESS;
        }

        return OrgStockFamilyStateEnum::DISCONTINUED;

    }


}
