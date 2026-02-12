<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Feb 2026 17:55:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Catalogue\ProductCategory\CloneProductCategoryImagesFromMaster;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CopyMasterFamilyImagesFromChildren
{
    use asAction;
    use WithAttachMediaToModel;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop $from, Command $command): void
    {

        dd('this should no run anymore because some images will be lost');
        $seederShop = 13;

        /** @var MasterProductCategory $masterFamily */
        foreach ($from->getMasterFamilies() as $masterFamily) {
            $mainImage  = null;
            $images     = [];
            $imagesData = [];
            $position   = 0;


            if ($masterFamily->image_id) {
                $images[$masterFamily->image_id]     = $masterFamily->image_id;
                $imagesData[$masterFamily->image_id] = [
                    'scope'     => 'catalogue',
                    'group_id'  => $masterFamily->group_id,
                    'position'  => $position,
                    'is_public' => true,
                ];
                $mainImage                           = $masterFamily->image_id;
            }

            foreach ($masterFamily->productCategories as $productCategory) {
                if (!$productCategory->image_id) {
                    continue;
                }

                if (!$mainImage && $productCategory->shop_id == $seederShop) {
                    $mainImage = $productCategory->image_id;
                }

                $images[$productCategory->image_id]     = $productCategory->image_id;
                $imagesData[$productCategory->image_id] = [
                    'scope'           => 'catalogue',
                    'group_id'        => $productCategory->group_id,
                    'organisation_id' => $productCategory->organization_id,
                    'position'        => $position,
                    'is_public'       => true,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                    'data'            => '{}',
                ];
                $position++;
            }


            if (!empty($images)) {
                $masterFamily->images()->sync($imagesData);
                if (!$mainImage) {
                    $mainImage = $images[array_key_first($images)];
                }
                $masterFamily->update(['image_id' => $mainImage]);
            }


            foreach ($masterFamily->productCategories as $productCategory) {
                CloneProductCategoryImagesFromMaster::run($productCategory);
            }


            $command->info("Updating ".$masterFamily->code.'  '.$masterFamily->image_id.'  -> '.implode(',', $images).' main '.$mainImage);
        }
    }


    public function getCommandSignature(): string
    {
        return 'repair:copy_master_family_images_from_children {master}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $fromShop = MasterShop::where('slug', $command->argument('master'))->firstOrFail();

        $this->handle($fromShop, $command);

        return 0;
    }


}
