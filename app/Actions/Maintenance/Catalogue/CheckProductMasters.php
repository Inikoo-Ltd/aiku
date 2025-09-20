<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Sept 2025 14:20:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Maintenance\Masters\AddMissingMasterAssets;
use App\Actions\Masters\MasterAsset\MatchAssetsToMaster;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CheckProductMasters
{
    use WithActionUpdate;


    /**
     * @throws \Throwable
     */
    public function handle(Product $product, Command $command): void
    {
        MatchAssetsToMaster::run($product->asset, $product->shop->masterShop);
        if ($product->is_main  && !$product->master_product_id && in_array($product->state, [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING])) {
            $command->error($product->code.'  ('.$product->state->value.')  has no master product');
            $product->refresh();
            if ($product->is_main && !$product->master_product_id) {
                $command->info("Found main product with no master asset $product->slug");
                AddMissingMasterAssets::make()->upsertMasterProduct($product->shop->masterShop, $product);
            }

        }

    }


    public string $commandSignature = 'check:product_masters {shop?}';

    public function asCommand(Command $command): void
    {
        //        if ($command->argument('product')) {
        //            $product = Product::find($command->argument('product'));
        //            $this->handle($product, $command);
        //        } else {

        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();


        $count = Product::where('shop_id', $shop->id)->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Product::where('shop_id', $shop->id)->orderBy('id')
            ->chunk(100, function (Collection $models) use ($bar, $command) {
                foreach ($models as $model) {
                    $this->handle($model, $command);
                    $bar->advance();
                }
            });
    }
    //   }

}
