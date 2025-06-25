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
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToProduct extends OrgAction
{
    use WithUploadProductImage;


    public function asController(Organisation $organisation, Product $product, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);

        $this->handle($product, 'image', $this->validatedData);
    }
}
