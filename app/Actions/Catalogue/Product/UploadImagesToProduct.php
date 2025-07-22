<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 15:50:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\WithUploadProductImage;
use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToProduct extends OrgAction
{
    use WithUploadProductImage;


    public function asController(Product $product, ActionRequest $request): void
    {
        $this->initialisationFromShop($product->shop, $request);

        $this->handle($product, 'image', $this->validatedData);
    }
}
