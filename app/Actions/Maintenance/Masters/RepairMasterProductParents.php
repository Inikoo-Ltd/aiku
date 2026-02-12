<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Feb 2026 20:16:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Catalogue\Product\UpdateProductFamily;
use App\Actions\Catalogue\ProductCategory\CloneProductCategoryParentsFromMaster;
use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterAsset;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterProductParents extends OrgAction
{
    use AsAction;


    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        $masterFamily = $masterAsset->masterFamily;
        if (!$masterFamily) {
            return $masterAsset;
        }

        $masterDepartment    = $masterFamily->masterDepartment;
        $masterSubDepartment = $masterFamily->masterSubDepartment;

        $masterAsset->update([
            'master_department_id'     => $masterDepartment?->id,
            'master_sub_department_id' => $masterSubDepartment?->id,
        ]);


        foreach ($masterAsset->products as $product) {
            $family = ProductCategory::where('shop_id', $product->shop_id)->where('master_product_category_id', $masterFamily->id)->first();
            if ($family) {
                UpdateProductFamily::make()->action($product, [
                    'family_id' => $family->id
                ]);
            }
        }

        foreach (ProductCategory::where('master_product_category_id', $masterFamily->id)->get() as $family) {
            CloneProductCategoryParentsFromMaster::run($family);
        }

        return $masterAsset;
    }

    public function getCommandSignature(): string
    {
        return 'repair:master_products_parents {masterAsset?}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if ($command->argument('masterAsset')) {
            $masterAsset = MasterAsset::where('slug', $command->argument('masterAsset'))->firstOrFail();
            $command->info("Fixing master product parents for $masterAsset->code");
            $this->handle($masterAsset);

            return 0;
        }


        $command->info('Fix master product parents');

        $chunkSize = 100;
        $count     = 0;

        $totalCount = MasterAsset::count();

        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();


        MasterAsset::chunk(
            $chunkSize,
            function ($masterAssets) use (&$count, $bar, $command) {
                foreach ($masterAssets as $masterAsset) {
                    try {
                        $this->handle($masterAsset);
                    } catch (Exception $e) {
                        $command->error("Error processing master asset $masterAsset->id: {$e->getMessage()}");
                    }
                    $count++;
                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->newLine();

        return 0;
    }
}
