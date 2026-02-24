<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\CloneProductImagesFromMasterProduct;
use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithImageUpdate;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterProductImages extends GrpAction
{
    use WithActionUpdate;
    use WithImageUpdate;

    public function handle(MasterAsset $masterAsset, array $modelData, bool $updateDependants = false): MasterAsset
    {
        if (!$masterAsset->is_single_trade_unit || !$masterAsset->follow_trade_unit_media) {
            $this->updateModelImages($masterAsset, $modelData);

            data_set($modelData, 'bucket_images', true);

            $this->update($masterAsset, $modelData);

            if ($updateDependants) {
                $this->updateDependants($masterAsset);
            }
        }

        return $masterAsset;
    }

    public function updateDependants(MasterAsset $seedMasterAsset): void
    {
        foreach ($seedMasterAsset->products as $product) {
            if ($product) {
                $canUpdate = false;
                if (!$product->is_single_trade_unit) {
                    $canUpdate = true;
                }
                if (!$seedMasterAsset->follow_trade_unit_media) {
                    $canUpdate = true;
                }
                if ($canUpdate) {
                    CloneProductImagesFromMasterProduct::run($product);
                }
            }
        }
    }


    public function rules(): array
    {
        return $this->imageUpdateRules();
    }


    public function asController(MasterAsset $masterAsset, ActionRequest $request): void
    {
        $this->initialisation($masterAsset->group, $request);

        $this->handle($masterAsset, $this->validatedData, true);
    }
}
