<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\StoreProductFromMasterProduct;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class CloneMasterAssetToOtherShop extends OrgAction
{
    use WithNoStrictRules;
    use WithMastersEditAuthorisation;

    /**
     * @var \App\Models\Masters\MasterProductCategory
     */
    private MasterProductCategory $masterFamily;
    private MasterAsset $masterAsset;
    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $masterFamily, array $modelData): MasterProductCategory
    {
        $shopProducts = Arr::pull($modelData, 'shop_products', []);

        StoreProductFromMasterProduct::make()->action($this->masterAsset, [
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

    public function prepareForValidation(): void
    {
        if($this->has('masterProduct')){
            $this->masterAsset = MasterAsset::where('slug', $this->masterProduct)->first();
            $this->set('masterAsset', $this->masterAsset->replicate()->toArray());
        }
    }

    public function rules(): array
    {
        $rules = [
            'masterFamily'                      => ['required', 'int'],
            'masterAsset'                       => ['required', 'array'],
            'masterAsset.unit'                  => ['required', 'string'],
            'masterAsset.units'                 => ['required', 'numeric'],
            'shop_products'                     => ['sometimes', 'array'],
            'shop_products.*.create_in_shop'    => ['sometimes', 'string'],
            'shop_products.*.price'             => ['required', 'min:0'],
            'shop_products.*.rrp'               => ['required', 'min:0'],
        ];

        return $rules;
    }

    public function getValidationMessages(): array
    {
        return [
            'masterAsset.unit.required'  => 'Master product have missing Unit. Please edit master product first',
            'masterAsset.units.required' => 'Master product have missing Units. Please contact administrator to fix this issue',
            'shop_products.*.price'      => 'Required to input Price on selected shop',
            'shop_products.*.rrp'        => 'Required to input RRP on selected shop',
        ];
    }
}
