<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Aug 2025 22:18:22 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Models\Catalogue\Asset;
use App\Models\Masters\MasterAsset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateAssets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterAsset $masterAsset): string
    {
        return $masterAsset->id;
    }

    public function handle(MasterAsset $masterAsset): void
    {
        $stats = [
            'number_assets' => DB::table('assets')->where('master_asset_id', $masterAsset->id)->count(),
            'number_current_assets' => DB::table('assets')->where('master_asset_id', $masterAsset->id)->whereIn('state', [
                AssetStateEnum::ACTIVE,
                AssetStateEnum::DISCONTINUING,
            ])->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'assets',
                field: 'state',
                enum: AssetStateEnum::class,
                models: Asset::class,
                where: function ($q) use ($masterAsset) {
                    $q->where('master_asset_id', $masterAsset->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'assets',
                field: 'type',
                enum: AssetTypeEnum::class,
                models: Asset::class,
                where: function ($q) use ($masterAsset) {
                    $q->where('master_asset_id', $masterAsset->id);
                }
            )
        );

        $masterAsset->stats()->update($stats);
    }


}
