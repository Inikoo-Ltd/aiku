<?php

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Product\CloneProductImagesFromTradeUnits;
use App\Actions\Masters\MasterAsset\CloneMasterAssetImagesFromTradeUnits;
use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\ActionRequest;

class HydrateProductImagesFromTradeUnits extends OrgAction
{
    public function handle(Product $product)
    {
        $masterAsset                = $product->masterProduct;
        $masterFollowTradeUnitMedia = $masterAsset?->follow_trade_unit_media;

        $followMaster = false;
        if ($masterAsset) {
            if ($masterAsset->is_single_trade_unit && $masterFollowTradeUnitMedia) {
                CloneMasterAssetImagesFromTradeUnits::run($masterAsset);
            }

            if (!$masterFollowTradeUnitMedia) {
                $followMaster = true;
            }
        }

        if ($product->is_single_trade_unit && !$followMaster) {
            CloneProductImagesFromTradeUnits::run($product);
        }
    }

    public function asController(Product $product, ActionRequest $request)
    {
        $this->initialisationFromShop($product->shop, $request);

        $this->handle($product);
    }
}
