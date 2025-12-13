<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Dec 2025 10:29:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\Catalogue\WithUpdateWebImages;
use App\Models\Masters\MasterCollection;
use Lorisleiva\Actions\Concerns\AsObject;

class UpdateMasterCollectionWebImages
{
    use AsObject;
    use WithUpdateWebImages;

    public function handle(MasterCollection $masterCollection): MasterCollection
    {
        return $this->updateWebImages($masterCollection);
    }


}
