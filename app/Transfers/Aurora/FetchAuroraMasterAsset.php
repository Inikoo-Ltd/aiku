<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:50:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Catalogue\Product\ProductUnitRelationshipType;
use App\Enums\Goods\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Facades\DB;

class FetchAuroraMasterAsset extends FetchAurora
{
    use WithAuroraImages;
    use WithMasterFetch;

    protected function parseModel(): void
    {
        $masterShop = $this->getMasterShop('Product Store Key');

        if ($masterShop == null) {
            return;
        }

        if ($this->auroraModelData->{'Product Status'} != 'Active' or $this->auroraModelData->{'Product Web Configuration'} == 'Offline') {
            return;
        }

        $masterFamilyId = null;

        $this->parsedData['parent'] = $masterShop;
        if ($this->auroraModelData->{'Product Family Category Key'}) {
            /** @var MasterProductCategory $masterFamily */
            $masterFamily = $this->parseMasterFamily($this->organisation->id.':'.$this->auroraModelData->{'Product Family Category Key'});
            if ($masterFamily) {
                $this->parsedData['parent'] = $masterFamily;
                $masterFamilyId = $masterFamily->id;
            }
        }


        $data = [];

        $units = $this->auroraModelData->{'Product Units Per Case'};
        if ($units == 0) {
            $units = 1;
        }

        $created_at = $this->parseDatetime($this->auroraModelData->{'Product Valid From'});
        if (!$created_at) {
            $created_at = $this->parseDatetime($this->auroraModelData->{'Product For Sale Since Date'});
        }
        if (!$created_at) {
            $created_at = $this->parseDatetime($this->auroraModelData->{'Product First Sold Date'});
        }


        $code = $this->cleanTradeUnitReference($this->auroraModelData->{'Product Code'});

        $name = $this->auroraModelData->{'Product Name'};
        if (!$name) {
            $name = $code;
        }


        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Product Store Key'});


        $price = $this->auroraModelData->{'Product Price'};
        $price = GetCurrencyExchange::run($shop->currency, $this->organisation->group->currency) * $price;


        $this->parsedData['master_asset'] = [
            'is_main'                => true,
            'type'                   => MasterAssetTypeEnum::PRODUCT,
            'code'                   => $code,
            'name'                   => $name,
            'price'                  => $price,
            'unit'                   => $this->auroraModelData->{'Product Unit Label'},
            'data'                   => $data,
            'created_at'             => $created_at,
            'trade_unit_composition' => ProductUnitRelationshipType::SINGLE,
            'source_id'              => $this->organisation->id.':'.$this->auroraModelData->{'Product ID'},
            'images'                 => $this->parseImages(),
            'fetched_at'             => now(),
            'last_fetched_at'        => now(),
            'master_family_id'       => $masterFamilyId
        ];


        if ($this->auroraModelData->{'is_variant'} == 'Yes') {
            $this->parsedData['master_asset']['is_main'] = false;
            $mainProduct                                 = $this->parseProduct($this->organisation->id.':'.$this->auroraModelData->{'variant_parent_id'});


            $this->parsedData['master_asset']['variant_ratio']      = $units / $mainProduct->units;
            $this->parsedData['master_asset']['variant_is_visible'] = $this->auroraModelData->{'Product Show Variant'} == 'Yes';
            $this->parsedData['master_asset']['main_product_id']    = $mainProduct->id;
        }
    }

    private function parseImages(): array
    {
        $images = $this->getModelImagesCollection(
            'Product',
            $this->auroraModelData->{'Product ID'}
        )->map(function ($auroraImage) {
            return $this->fetchImage($auroraImage);
        });

        return $images->toArray();
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Product Dimension')
            ->where('Product ID', $id)->first();
    }
}
