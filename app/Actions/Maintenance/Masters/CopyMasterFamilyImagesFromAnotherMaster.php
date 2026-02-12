<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Feb 2026 17:55:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Catalogue\ProductCategory\CloneProductCategoryImagesFromMaster;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CopyMasterFamilyImagesFromAnotherMaster
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop $from, MasterShop $to, Command $command): void
    {
        foreach ($to->getMasterFamilies() as $masterFamily) {
            $fromFamily = MasterProductCategory::where('master_shop_id', $from->id)->where('type', MasterProductCategoryTypeEnum::FAMILY)
                ->whereRaw("lower(code) = lower(?)", [$masterFamily->code])
                ->first();

            if ($fromFamily && $fromFamily->image) {
                $command->info("Updating ".$masterFamily->code.'  '.$masterFamily->image_id.'  -> '.$fromFamily->image_id);

                $mainImage  = null;
                $imagesData = [];
                $position   = 0;
                $mainImage  = $fromFamily->image_id;
                foreach ($fromFamily->images as $image) {
                    $imagesData[$image->id] = [
                        'scope'      => 'catalogue',
                        'group_id'   => $fromFamily->group_id,
                        'position'   => $position,
                        'is_public'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'data'       => '{}',
                    ];
                    $position++;
                }
                $masterFamily->images()->sync($imagesData);
                $masterFamily->images()->sync($imagesData);
                if (!$mainImage) {
                    $mainImage = $imagesData[array_key_first($imagesData)];
                }
                $masterFamily->update(['image_id' => $mainImage]);


                foreach ($masterFamily->productCategories as $productCategory) {
                    CloneProductCategoryImagesFromMaster::run($productCategory);
                }
            }
        }
    }


    public function getCommandSignature(): string
    {
        return 'repair:copy_master_family_images_from_another_master {from} {to}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $fromShop = MasterShop::where('slug', $command->argument('from'))->firstOrFail();
        $toShop   = MasterShop::where('slug', $command->argument('to'))->firstOrFail();

        $this->handle($fromShop, $toShop, $command);

        return 0;
    }


}
