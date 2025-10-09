<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Apr 2025 15:18:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterFamilyHydratePendingMasterAssets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterProductCategory $masterFamily): string
    {
        return $masterFamily->id;
    }

    public function handle(MasterProductCategory $masterFamily): void
    {
        if ($masterFamily->type != MasterProductCategoryTypeEnum::FAMILY) {
            return;
        }

        $masterAssetIds = $masterFamily->masterAssets()->pluck('id')->filter()->toArray();

        $count = DB::table('trade_units')
            ->where('trade_units.group_id', $masterFamily->group_id)
            ->where('trade_units.code', 'like', $masterFamily->code . '%')
            ->leftJoin('model_has_trade_units', function ($join) {
                $join->on('trade_units.id', '=', 'model_has_trade_units.trade_unit_id')
                    ->where('model_has_trade_units.model_type', '=', 'MasterAsset');
            })
            ->when(!empty($masterAssetIds), function ($query) use ($masterAssetIds) {
                $query->whereNotIn('trade_units.id', function ($subquery) use ($masterAssetIds) {
                    $subquery->select('trade_unit_id')
                        ->from('model_has_trade_units')
                        ->where('model_type', 'MasterAsset')
                        ->whereIn('model_id', $masterAssetIds);
                });
            })
            ->groupBy('trade_units.id')
            ->count();

        $stats = [
            'number_pending_master_assets' => $count,
        ];

        $masterFamily->stats()->update($stats);
    }


}
