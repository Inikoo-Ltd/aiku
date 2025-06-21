<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Jun 2025 22:08:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\WithUpdateWebImages;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class UpdateProductCategoryWebImages
{
    use AsObject;
    use WithUpdateWebImages;

    public function handle(ProductCategory $productCategory): ProductCategory
    {
        return $this->updateWebImages($productCategory);
    }


}
