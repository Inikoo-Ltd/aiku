<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2025 12:48:39 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class RepairProductsWebpageSync
{
    use WithActionUpdate;

    protected function handle(Product $product, Command $command): void
    {
        if ($product->is_for_sale) {
            $webpage = $product->webpage;
            if (!$webpage) {
                $command->info("Error product $product->code dont have webpage");
            } else {
                if ($webpage->state == WebpageStateEnum::CLOSED) {
                    $command->info("Error product $product->code webpage is closed");
                }
            }
        } else {
            if ($webpage = $product->webpage) {
                if ($webpage->state == WebpageStateEnum::LIVE) {
                    $command->info("Error NOT FOR SALE product $product->code webpage live");
                }
            }
        }
    }

    public string $commandSignature = 'repair:products_webpage_sync {shop}';

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        $count = Product::where('shop_id', $shop->id)
            ->whereIn('state', [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING])->count();


        $command->info("Found products for sale no webpage : $count");

        Product::where('shop_id', $shop->id)->whereNull('webpage_id')
            ->chunk(1000, function ($products) use ($command) {
                foreach ($products as $product) {
                    $this->handle($product, $command);
                }
            });
    }

}
