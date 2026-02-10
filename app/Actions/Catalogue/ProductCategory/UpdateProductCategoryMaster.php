<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Feb 2026 15:11:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductCategoryMaster
{
    use AsAction;

    public function handle(ProductCategory $productCategory, MasterProductCategory $masterProductCategory): ProductCategory
    {
        UpdateProductCategory::make()->action(
            $productCategory,
            [
                'master_product_category_id' => $masterProductCategory?->id,
            ]
        );
        return $productCategory;
    }

    public function getCommandSignature(): string
    {
        return 'product_category:update_master {product_category} {master_product_category}';
    }

    public function asCommand(Command $command): int
    {
        $productCategory = ProductCategory::where('slug', $command->argument('product_category'))->firstOrFail();
        $masterProductCategory = MasterProductCategory::where('slug', $command->argument('master_product_category'))->firstOrFail();
        $this->handle($productCategory, $masterProductCategory);
        return 0;
    }

}
