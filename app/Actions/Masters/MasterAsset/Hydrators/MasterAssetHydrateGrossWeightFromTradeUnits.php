<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Sept 2024 17:15:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Traits\Hydrators\WithWeightFromTradeUnits;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateGrossWeightFromTradeUnits implements ShouldBeUnique
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
        if (!$masterAsset || $masterAsset->type != MasterAssetTypeEnum::PRODUCT) {
            return;
        }
        $masterAsset->updateQuietly(
            [
                'gross_weight' => $this->getWeightFromTradeUnits($masterAsset),
            ]
        );
    }


}
