<?php

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\UpdateProductIndex;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateIndexFromMasterShopToShops
{
    use AsAction;
    
    public function getJobUniqueId(MasterShop $masterShop, MasterProductCategory $masterProductCategory): string
    {
        return "{$masterShop->code}_{$masterProductCategory->code}";
    }

    public function handle(MasterShop $masterShop, MasterProductCategory $masterProductCategory, array $indexOrders): void
    {
        $productCategories = $masterProductCategory->productCategories->keyBy('shop_id');
        foreach($masterShop->shops as $shop) {
            if (!data_get($shop->settings, 'catalog.family_indexing_follow_master', true)) {
                continue;
            }

            if($productCategory = $productCategories[$shop->id]) {

                $indexOrderProduct = Product::whereIn('code', array_keys($indexOrders))
                    ->where('shop_id', $shop->id)
                    ->get()
                    ->mapWithKeys(function ($item) use ($indexOrders, $masterProductCategory) {
                        return [
                            $item->code => [
                                'id'                                                    => $item->id,
                                'code'                                                  => $item->code,
                                "index_under_{$masterProductCategory->type->value}"     => data_get($indexOrders, "$item->code.index_under_master_{$masterProductCategory->type->value}", null),
                            ]
                        ];
                    })
                    ->toArray();

                UpdateProductIndex::make()->asAction($productCategory, ['products'  => $indexOrderProduct]);
            }
        }
    }
}
