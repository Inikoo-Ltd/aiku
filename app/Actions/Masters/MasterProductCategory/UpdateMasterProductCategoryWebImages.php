<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Dec 2025 10:27:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\WithUpdateWebImages;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class UpdateMasterProductCategoryWebImages
{
    use AsObject;
    use WithUpdateWebImages;

    public function handle(MasterProductCategory $masterProductCategory): MasterProductCategory
    {
        return $this->updateWebImages($masterProductCategory);
    }


}
