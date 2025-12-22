<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 15:34:01 Malaysia Time, Cyberjaya, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFamilyNavigation
{
    use WithFamilyNavigation;
    use AsObject;

    public function handle(ProductCategory $productCategory, ActionRequest $request): array
    {
        return [
            'previous' => $this->getPreviousModel($productCategory, $request),
            'next'     => $this->getNextModel($productCategory, $request),
        ];
    }

}
