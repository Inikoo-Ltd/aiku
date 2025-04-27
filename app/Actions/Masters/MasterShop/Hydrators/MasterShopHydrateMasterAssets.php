<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Dec 2024 01:14:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateMasterAssets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterShop $masterShop): string
    {
        return $masterShop->id;
    }

    public function handle(MasterShop $masterShop): void
    {
        $stats = [
            'number_master_assets' => $masterShop->masterAssets()->count(),
            'number_current_master_assets' => $masterShop->masterAssets()->where('status', true)->count(),

        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'current_master_assets',
                field: 'type',
                enum: MasterAssetTypeEnum::class,
                models: MasterAsset::class,
                where: function ($q) use ($masterShop) {
                    $q->where('master_shop_id', $masterShop->id)->where('status', true);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'master_assets',
                field: 'type',
                enum: MasterAssetTypeEnum::class,
                models: MasterAsset::class,
                where: function ($q) use ($masterShop) {
                    $q->where('master_shop_id', $masterShop->id);
                }
            )
        );

        $masterShop->stats()->update($stats);
    }


}
