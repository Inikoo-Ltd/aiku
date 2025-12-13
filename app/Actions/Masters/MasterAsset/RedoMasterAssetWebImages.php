<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Dec 2025 10:26:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Masters\MasterAsset;

class RedoMasterAssetWebImages
{
    use WithHydrateCommand;

    public string $commandSignature = 'master_asset:redo_web_images {--s|slug=} ';

    public function __construct()
    {
        $this->model = MasterAsset::class;
    }

    public function handle(MasterAsset $masterAsset): void
    {
        UpdateMasterAssetWebImages::run($masterAsset);

    }

}
