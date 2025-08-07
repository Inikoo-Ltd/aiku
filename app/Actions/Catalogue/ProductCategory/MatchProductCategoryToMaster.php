<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Aug 2025 07:47:47 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class MatchProductCategoryToMaster extends OrgAction
{
    use AsAction;


    public function handle(ProductCategory $productCategory): ProductCategory
    {

        $masterShop = $productCategory->shop->masterShop;

        if (!$masterShop) {
            return $productCategory;
        }

        $masterProductCategory = MasterProductCategory::where('master_shop_id', $masterShop->id)->whereRaw('LOWER(code) = LOWER(?)', [$productCategory->code])
            ->where('type', $productCategory->type->value)
            ->first();



        if ($masterProductCategory) {
            UpdateProductCategory::make()->action(
                $productCategory,
                [
                    'master_product_category_id' => $masterProductCategory->id,
                ]
            );
        }

        return $productCategory;
    }

    public function getCommandSignature(): string
    {
        return 'product:match_to_master';
    }

    public function asCommand(Command $command): int
    {


        $command->info('Matching product categories to master product categories');

        $chunkSize = 100;
        $count = 0;
        $matchedCount = 0;

        // Get total count for progress bar
        $totalCount = ProductCategory::count();

        // Create a progress bar
        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        // Process product categories in chunks

        //where('type',ProductCategoryTypeEnum::DEPARTMENT)->where('shop_id',1)
        ProductCategory::chunk(
            $chunkSize,
            function ($productCategories) use (&$count, &$matchedCount, $bar, $command) {
                foreach ($productCategories as $productCategory) {
                    try {
                        $hadMaster = (bool)$productCategory->master_product_category_id;
                        $this->handle($productCategory);
                        $hasNowMaster = (bool)$productCategory->master_product_category_id;

                        if (!$hadMaster && $hasNowMaster) {
                            $matchedCount++;
                        }
                    } catch (Exception $e) {
                        $command->error("Error processing product category {$productCategory->id}: {$e->getMessage()}");
                    }
                    $count++;
                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->newLine();
        $command->info("$count product categories processed, $matchedCount newly matched to master product categories");

        return 0;
    }
}
