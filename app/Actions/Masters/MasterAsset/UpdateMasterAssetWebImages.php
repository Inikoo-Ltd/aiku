<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Dec 2025 10:27:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\WithUpdateWebImages;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsObject;

class UpdateMasterAssetWebImages
{
    use AsObject;
    use WithUpdateWebImages;

    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        return $this->updateWebImages($masterAsset);
    }


}
