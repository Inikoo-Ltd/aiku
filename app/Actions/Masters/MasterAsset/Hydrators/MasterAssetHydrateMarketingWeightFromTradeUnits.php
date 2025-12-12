<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Dec 2025 00:11:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Traits\Hydrators\WithWeightFromTradeUnits;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateMarketingWeightFromTradeUnits implements ShouldBeUnique
{
    use AsAction;
    use WithWeightFromTradeUnits;

    public function getJobUniqueId(int|null $masterAssetId): string
    {
        return $masterAssetId ?? 'empty';
    }

    public function handle(int|null $masterAssetId): void
    {
        if ($masterAssetId === null) {
            return;
        }
        $masterAsset = MasterAsset::find($masterAssetId);
        if (!$masterAsset || $masterAsset->type != MasterAssetTypeEnum::PRODUCT || !$masterAsset->is_single_trade_unit) {
            return;
        }

        $tradeUnit       = $masterAsset->tradeUnits()->whereNotNull('marketing_weight')->orderBy('marketing_weight', 'desc')->first();
        $marketingWeight = $tradeUnit?->marketing_weight;


        $masterAsset->updateQuietly(
            [
                'marketing_weight' => $marketingWeight,
            ]
        );
    }


}
