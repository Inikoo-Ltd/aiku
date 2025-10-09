<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Sept 2024 14:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateAssets;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Models\Masters\MasterAsset;

class HydrateMasterAssets
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:master_assets';

    public function __construct()
    {
        $this->model = MasterAsset::class;
    }

    public function handle(MasterAsset $masterAsset): void
    {
        ModelHydrateSingleTradeUnits::run($masterAsset);
        MasterAssetHydrateAssets::run($masterAsset);
    }

}
