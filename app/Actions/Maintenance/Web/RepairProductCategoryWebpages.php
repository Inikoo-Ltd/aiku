<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Sept 2025 13:59:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairProductCategoryWebpages
{
    use WithActionUpdate;
    use WithRepairWebpages;


    /**
     * @throws \Throwable
     */
    protected function handle(ProductCategory $productCategory, Command $command): void
    {

        $created = false;
        $published = false;



        if (in_array($productCategory->state, [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
        ])) {

            $webpage = null;
            if (!$productCategory->webpage) {
                try {
                    $webpage = StoreProductCategoryWebpage::make()->action($productCategory);
                    $created = true;
                } catch (Exception) {
                    //
                }

            } else {
                $webpage = $productCategory->webpage;
            }

            if ($webpage && ($webpage->state == WebpageStateEnum::IN_PROCESS || $webpage->state == WebpageStateEnum::READY)) {
                PublishWebpage::make()->action(
                    $webpage,
                    [
                        'comment' => 'Initial commit',
                    ]
                );
                $published = true;
            }


            if ($created || $published) {
                $command->line($productCategory->slug);
            }




        }
    }

    public string $commandSignature = 'repair:product_category_webpages';

    public function asCommand(Command $command): void
    {
        $shops = Shop::where('state', ShopStateEnum::OPEN)->pluck('id');

        // Process webpages in chunks to save memory
        DB::table('product_categories')
            ->whereIn('shop_id', $shops)
            ->select('id')
            ->whereIn('state', [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING])
            ->orderBy('id')
            ->chunk(
                100,
                function ($productIds) use ($command) {
                    foreach ($productIds as $productId) {
                        $productCategory = ProductCategory::find($productId->id);
                        if ($productCategory) {
                            $this->handle($productCategory, $command);
                        }
                    }
                }
            );
    }

}
