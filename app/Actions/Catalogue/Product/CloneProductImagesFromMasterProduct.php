<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Sept 2025 11:11:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Concerns\CanCloneImages;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneProductImagesFromMasterProduct implements ShouldBeUnique
{
    use AsAction;
    use CanCloneImages;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        $canUpdate = false;
        if (!$product->is_single_trade_unit) {
            $canUpdate = true;
        }
        if ($product->masterProduct && !$product->masterProduct->follow_trade_unit_media) {
            $canUpdate = true;
        }



        if (!$canUpdate) {
            CloneProductImagesFromTradeUnits::run($product);

            return;
        }


        $masterProduct = $product->masterProduct;

        if (!$masterProduct) {
            return;
        }

        $this->syncProductImages($masterProduct, $product);
    }

    public string $commandSignature = 'catalogue:product:clone-images-from-master-product {product?}';

    public function asCommand(Command $command): int
    {
        if ($command->argument('product')) {
            /** @var Product $product */
            $product = Product::where('slug', $command->argument('product'))
                ->firstOrFail();

            $this->handle($product);

            $command->info("Images cloned from master product for product: $product->code");

            return 0;
        }

        $aikuShops = Shop::where('is_aiku', true)->pluck('id')->toArray();

        $query = Product::whereIn('shop_id', $aikuShops)->where('is_single_trade_unit', false);
        $total = $query->count();

        $command->getOutput()->progressStart($total);

        $query->chunk(100, function ($products) use ($command) {
            foreach ($products as $product) {
                $this->handle($product);
                $command->getOutput()->progressAdvance();
            }
        });

        $command->getOutput()->progressFinish();

        return 0;
    }
}
