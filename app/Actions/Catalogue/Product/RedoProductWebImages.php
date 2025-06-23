<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Jun 2025 21:31:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\Product;

class RedoProductWebImages
{
    use WithHydrateCommand;

    public string $commandSignature = 'products:redo_web_images {organisations?*} {--S|shop= shop slug} {--s|slug=} ';

    public function __construct()
    {
        $this->model = Product::class;
    }

    public function handle(Product $product): void
    {
        UpdateProductWebImages::run($product);

    }

}
