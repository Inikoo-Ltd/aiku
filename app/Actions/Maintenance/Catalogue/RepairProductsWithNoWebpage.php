<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2025 12:48:39 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class RepairProductsWithNoWebpage
{
    use WithActionUpdate;

    protected function handle(Product $product): void
    {
        StoreProductWebpage::run($product);
    }

    public string $commandSignature = 'repair:products_no_webpage {shop}';

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        $count = Product::where('shop_id', $shop->id)->whereNull('webpage_id')->where('is_for_sale', true)->count();


        $command->info("Found products for sale no webpage : $count");

        Product::where('shop_id', $shop->id)->whereNull('webpage_id')->where('is_for_sale', true)
            ->chunk(1000, function ($products) use ($command) {
                foreach ($products as $product) {
                    $command->info($product->code);
                    $this->handle($product);
                }
            });
    }

}
