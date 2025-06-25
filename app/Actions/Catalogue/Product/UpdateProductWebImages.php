<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Jun 2025 22:08:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\WithUpdateWebImages;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsObject;

class UpdateProductWebImages
{
    use AsObject;
    use WithUpdateWebImages;

    public function handle(Product $product): Product
    {
        return $this->updateWebImages($product);
    }


}
