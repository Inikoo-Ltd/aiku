<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Dec 2025 16:04:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateAssets;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\OrgAction;
use App\Models\Masters\MasterAsset;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterProductIsForSale extends OrgAction
{
    use AsAction;


    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset, bool $updateProducts, Command $command): MasterAsset
    {
        MasterAssetHydrateAssets::run($masterAsset->id);


        $numberProductsIsForSale    = $masterAsset->stats->number_current_assets;
        $numberProductsIsNotForSale = $masterAsset->stats->number_assets_forced_not_for_sale;


        $isForSale = null;
        if ($numberProductsIsForSale == 0 && $numberProductsIsNotForSale == 0) {
            $isForSale = true;
            if (!$masterAsset->is_for_sale) {
                $command->info("Found $numberProductsIsForSale products for sale and $numberProductsIsNotForSale products not for sale for $masterAsset->code");
                $command->info("Fixing is_for_sale ***********  when is no products for $masterAsset->code $masterAsset->slug");
            }
        } elseif ($numberProductsIsForSale > 0) {

            $isForSale = true;

            if (!$masterAsset->is_for_sale) {
                $command->info("Found $numberProductsIsForSale products for sale and $numberProductsIsNotForSale products not for sale for $masterAsset->code");
                $command->info("Fixing FOR SALE when ANY products for sale for $masterAsset->code $masterAsset->slug");
            }
        } elseif ($numberProductsIsForSale == 0 && $numberProductsIsNotForSale > 0) {
            $isForSale = false;

            if ($masterAsset->is_for_sale) {
                $command->info("Found $numberProductsIsForSale products for sale and $numberProductsIsNotForSale products not for sale for $masterAsset->code");
                $command->info("Fixing NOT FOR SALE when ALL products not for sale for $masterAsset->code  $masterAsset->slug");
            }
        }


        if ($isForSale === null) {
            $command->error("XX $numberProductsIsForSale products for sale and $numberProductsIsNotForSale ERROR for $masterAsset->slug");

            dd('x');
        }

        if ($masterAsset->is_for_sale === null || $masterAsset->is_for_sale != $isForSale) {
            $command->info("Updating is_for_sale for $masterAsset->code   ($masterAsset->slug) from $masterAsset->is_for_sale to $isForSale");

            $masterAsset = UpdateMasterAsset::run($masterAsset, [
                'is_for_sale' => $isForSale
            ]);
        }

        if ($updateProducts) {
            $masterAsset->refresh();

            foreach ($masterAsset->products as $product) {
                UpdateProduct::run($product, [
                    'is_for_sale'              => $masterAsset->is_for_sale,
                    'not_for_sale_from_master' => !$masterAsset->is_for_sale
                ]);
            }
        }

        return $masterAsset;
    }

    public function getCommandSignature(): string
    {
        return 'repair:master_products_for_sale {masterAsset?} {--update_products}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $updateProducts = $command->option('update_products');


        if ($command->argument('masterAsset')) {
            $masterAsset = MasterAsset::where('slug', $command->argument('masterAsset'))->firstOrFail();
            $command->info("Fix masters for sale for $masterAsset->code");
            $this->handle($masterAsset, $updateProducts, $command);

            return 0;
        }

        $command->info('Fix masters for sale');

        $chunkSize = 100;
        $count     = 0;

        $totalCount = MasterAsset::count();

        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();


        MasterAsset::chunk(
            $chunkSize,
            function ($masterAssets) use (&$count, $bar, $command, $updateProducts) {
                foreach ($masterAssets as $asset) {
                    try {
                        $this->handle($asset, $updateProducts, $command);
                    } catch (Exception $e) {
                        $command->error("Error processing asset $asset->id: {$e->getMessage()}");
                    }
                    $count++;
                    if ($updateProducts) {
                        $bar->advance();
                    }
                }
            }
        );

        $bar->finish();
        $command->newLine();

        return 0;
    }
}
