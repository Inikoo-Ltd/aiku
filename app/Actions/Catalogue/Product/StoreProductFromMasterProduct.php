<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:05:20 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Asset\StoreAsset;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateForSale;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateProductVariants;
use App\Actions\Catalogue\Product\Traits\WithProductOrgStocks;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateExclusiveProducts;
use App\Actions\GrpAction;
use App\Actions\OrgAction;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Enums\Catalogue\Product\ProductUnitRelationshipType;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Masters\MasterAsset;
use App\Models\SysAdmin\Organisation;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreProductFromMasterProduct extends GrpAction
{
    use WithNoStrictRules;
    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset): Product
    {
        $productCategories = $masterAsset->masterFamily->productCategories;


        foreach($productCategories as $productCategory) {
            $orgStocks = [];
            foreach ($masterAsset->stocks as $stock) {
                foreach ($stock->orgStocks()->where('organisation_id', $productCategory->organisation_id) as $orgStock) {
                    $orgStock[$orgStock->id] = [
                        'quantity' => $orgStock->quantity,
                    ];
                }
            }
            $data = [
                'code' => $masterAsset->code,
                'name' => $masterAsset->name,
                'price' => $masterAsset->price,
                'unit'    => $masterAsset->unit,
                'is_main' => true,
                'org_stocks'  => $orgStocks
            ];
            $product = StoreProduct::run($productCategory, $data);

        }
        return $product;
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterAsset $masterAsset, int $hydratorsDelay = 0, $strict = true, $audit = true): Product
    {
        if (!$audit) {
            Product::disableAuditing();
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $group = $masterAsset->group;

        $this->initialisation($group, []);

        return $this->handle($masterAsset);
    }

}
