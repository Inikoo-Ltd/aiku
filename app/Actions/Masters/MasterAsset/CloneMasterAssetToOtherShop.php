<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\StoreProductFromMasterProduct;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateMasterAssets;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterAssets;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterAssets;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Helpers\SendSlackNotification;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class CloneMasterAssetToOtherShop extends OrgAction
{
    use WithNoStrictRules;
    use WithMastersEditAuthorisation;

    /**
     * @var \App\Models\Masters\MasterProductCategory
     */
    private MasterProductCategory $masterFamily;

    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $masterFamily, Array $modelData): MasterProductCategory
    {
        $shopProducts = Arr::pull($modelData, 'shop_products', []);
        $masterAsset = MasterAsset::where('slug', $modelData['masterProduct'])->first();

        StoreProductFromMasterProduct::make()->action($masterAsset, [
            'shop_products' => $shopProducts
        ]);


        return $masterFamily;
    }
    
    public function asController(String $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $this->masterFamily = MasterProductCategory::find($masterFamily);
        $this->initialisationFromGroup($this->masterFamily->group, $request);

        return $this->handle($this->masterFamily, $this->validatedData);
    }

    public function rules(): array
    {
        $rules = [
            'masterFamily'                      => ['required', 'int'],
            'masterProduct'                     => ['required', 'string'],
            'shop_products'                     => ['sometimes', 'array'],
            'shop_products.*.create_in_shop'    => ['sometimes', 'string'],
            'shop_products.*.price'             => ['required', 'min:0'],
            'shop_products.*.rrp'               => ['required', 'min:0'],
        ];

        return $rules;
    }
}
