<?php

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateMasterPricesRRPtoChild
{
    use AsAction;

    public function handle(MasterAsset $masterAsset)
    {
        $masterPrices   = $masterAsset->master_prices;
        $masterRRPs     = $masterAsset->master_rrps;

        $currencies     = Currency::whereIn('id', $masterAsset->products->pluck('currency_id'))->pluck('code', 'id');

        foreach($masterAsset->products as $product) {
            $currencyCode = $currencies->get($product->currency_id);

            $dataToBeUpdated = [];

            $price  = data_get($masterPrices, "{$currencyCode}.value", null);
            if ($price) {
                data_set($dataToBeUpdated, 'price', $price);
            }

            $rrpPerUnit    = data_get($masterRRPs, "{$currencyCode}.value", null);
            if ($rrpPerUnit) {
                data_set($dataToBeUpdated, 'rrp_per_unit', $rrpPerUnit);
            }

            UpdateProduct::make()->action($product, $dataToBeUpdated);
        }
    }
}
