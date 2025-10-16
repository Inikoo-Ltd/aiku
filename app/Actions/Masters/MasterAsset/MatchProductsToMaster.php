<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Oct 2025 14:39:33 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Asset\UpdateAsset;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\OrgAction;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class MatchProductsToMaster extends OrgAction
{
    use AsAction;


    public function handle(Product $product): Product
    {
        $masterShop = $product->shop->masterShop;

        if (!$masterShop) {
            return $product;
        }

        $masterAsset = MasterAsset::where('master_shop_id', $masterShop->id)->whereRaw('LOWER(code) = LOWER(?)', [$product->code])
            ->where('type', MasterAssetTypeEnum::PRODUCT)
            ->first();

        if (!$masterAsset) {
            return $product;
        }

        UpdateAsset::make()->action(
            $product->asset,
            [
                'master_asset_id' => $masterAsset->id,
            ]
        );


        UpdateProduct::make()->action($product, [
            'master_product_id' => $masterAsset->id,
        ]);


        return $product;
    }

    public function getCommandSignature(): string
    {
        return 'products:match_to_master';
    }

    public function asCommand(Command $command): int
    {
        $command->info('Matching products to masters');

        $chunkSize = 100;
        $count = 0;
        $matchedCount = 0;

        $totalCount = Product::whereNull('master_product_id')->count();

        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();


        Product::whereNull('master_product_id')->chunk(
            $chunkSize,
            function ($products) use (&$count, &$matchedCount, $bar, $command) {
                foreach ($products as $product) {
                    try {
                        $hadMaster = (bool)$product->master_product_id;
                        $this->handle($product);
                        $hasNowMaster = (bool)$product->master_product_id;

                        if (!$hadMaster && $hasNowMaster) {
                            $matchedCount++;
                        }
                    } catch (Exception $e) {
                        $command->error("Error processing asset $product->id: {$e->getMessage()}");
                    }
                    $count++;
                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->newLine();
        $command->info("$count assets processed, $matchedCount newly matched to master products");

        return 0;
    }
}
