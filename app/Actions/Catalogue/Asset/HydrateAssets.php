<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Feb 2023 22:01:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Asset;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateHistoricAssets;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateSales;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateTransactions;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\Asset;

class HydrateAssets
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:assets {organisations?*} {--S|shop= shop slug} {--slugs=}';

    public function __construct()
    {
        $this->model = Asset::class;
    }

    public function handle(Asset $asset): void
    {
        AssetHydrateHistoricAssets::run($asset);
        AssetHydrateSales::run($asset);
        AssetHydrateTransactions::run($asset);
    }


}
