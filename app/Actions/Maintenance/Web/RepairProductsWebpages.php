<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Aug 2025 16:06:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairProductsWebpages
{
    use WithActionUpdate;
    use WithRepairWebpages;


    /**
     * @throws \Throwable
     */
    protected function handle(Product $product, Command $command): void
    {

        $created = false;
        $published = false;

        if (!$product->is_main) {
            // Delete Webpage
            return;
        }

        if (in_array($product->state, [
            ProductStateEnum::ACTIVE,
            ProductStateEnum::DISCONTINUING
        ]) && $product->is_for_sale) {

            $webpage = null;
            if (!$product->webpage) {
                try {
                    $webpage = StoreProductWebpage::make()->action($product);
                    $created = true;
                } catch (Exception) {
                    //
                }

            } else {
                $webpage = $product->webpage;
            }

            if ($webpage && $webpage->state == WebpageStateEnum::IN_PROCESS || $webpage->state == WebpageStateEnum::READY) {
                PublishWebpage::make()->action(
                    $webpage,
                    [
                        'comment' => 'Initial commit',
                    ]
                );
                $published = true;
            }


            if ($created || $published) {
                $command->line($product->slug);
            }




        }
    }

    public string $commandSignature = 'repair:product_webpages';

    public function asCommand(Command $command): void
    {
        $shops = Shop::where('state', ShopStateEnum::OPEN)->pluck('id');

        // Process webpages in chunks to save memory
        DB::table('products')
            ->whereIn('shop_id', $shops)
            ->select('id')
            ->whereIn('state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING])
            ->orderBy('id')
            ->chunk(
                100,
                function ($productIds) use ($command) {
                    foreach ($productIds as $productId) {
                        $product = Product::find($productId->id);
                        if ($product) {
                            $this->handle($product, $command);
                        }
                    }
                }
            );
    }

}
