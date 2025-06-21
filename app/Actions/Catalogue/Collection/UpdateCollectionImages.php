<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Jun 2025 22:08:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\WithUpdateImages;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class UpdateCollectionImages
{
    use AsObject;
    use WithUpdateImages;

    public function handle(Collection $collection): ProductCategory
    {
        return $this->updateImages($collection);
    }


}
