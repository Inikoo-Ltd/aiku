<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Mar 2026 15:40:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneProductParentsFromMaster
{
    use asAction;

    public function handle(Product $product, Command|null $command = null): Product
    {
        $masterProduct = $product->masterProduct;
        if (!$masterProduct) {
            $command?->error($product->slug.' :master product not found');

            return $product;
        }

        $masterFamily = $masterProduct->masterFamily;
        if (!$masterFamily) {
            $command?->error($product->slug.' :master family not found');

            return $product;
        }

        $family = $masterFamily->productCategories()->where('shop_id', $product->shop_id)->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)->first();


        if ($family && $product->family_id != $family->id) {
            if ($product->family) {
                $command?->info($product->slug.' : update family from  '.$product->family->slug.'  '.$product->family_id.' to   '.$family->slug.' '.$family->id);
            } else {
                $command?->info($product->slug.' : add family  '.$product->family_id.' to   '.$family->slug.' '.$family->id);
            }

            UpdateProductFamily::make()->action($product, [
                'family_id' => $family->id
            ]);
        }


        return $product;
    }


    public function getCommandSignature(): string
    {
        return 'product:get_parents_from_master {parent?} {slug?}';
    }

    public function asCommand(Command $command): int
    {
        $shopsIds = null;

        if ($command->argument('parent')) {
            if ($command->argument('parent') == 'product' || $command->argument('parent') == 'p') {
                /** @var Product $product */
                $product = Product::where('slug', $command->argument('slug'))
                    ->firstOrFail();

                $this->handle($product,$command);


                return 0;
            }
            if ($command->argument('parent') == 'shop' || $command->argument('parent') == 's') {
                $shop     = Shop::where('slug', $command->argument('slug'))->firstOrFail();
                $shopsIds = [$shop->id];
            }
        }

        if ($shopsIds === null) {
            $shopsIds = Shop::where('is_aiku', true)->pluck('id')->toArray();
        }

        $query = Product::whereIn('shop_id', $shopsIds);
        $total = $query->count();

        $command->getOutput()->progressStart($total);

        $query->chunk(100, function ($products) use ($command) {
            foreach ($products as $product) {
                $this->handle($product,$command);
                $command->getOutput()->progressAdvance();
            }
        });

        $command->getOutput()->progressFinish();

        return 0;
    }


}
