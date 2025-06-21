<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Jun 2025 23:59:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\ProductCategory;

class RedoProductCategoryWebImages
{
    use WithHydrateCommand;

    public string $commandSignature = 'product_categories:redo_web_images {organisations?*} {--S|shop= shop slug} {--s|slug=} ';

    public function __construct()
    {
        $this->model = ProductCategory::class;
    }

    public function handle(ProductCategory $productCategory): void
    {
        UpdateProductCategoryWebImages::run($productCategory);

    }

}
