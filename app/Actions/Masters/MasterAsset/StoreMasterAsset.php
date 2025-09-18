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

class StoreMasterAsset extends OrgAction
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
    public function handle(MasterProductCategory $masterFamily, array $modelData): MasterAsset
    {
        $tradeUnits   = Arr::pull($modelData, 'trade_units', []);
        $shopProducts = Arr::pull($modelData, 'shop_products', []);

        $units = Arr::get($modelData, 'units', 1);

        data_set($modelData, 'units', $units);


        data_set($modelData, 'group_id', $masterFamily->group_id);


        data_set($modelData, 'master_department_id', $masterFamily->master_department_id);
        data_set($modelData, 'master_shop_id', $masterFamily->master_shop_id);
        data_set($modelData, 'master_family_id', $masterFamily->id);
        if ($masterFamily->master_sub_department_id) {
            data_set($modelData, 'master_sub_department_id', $masterFamily->master_sub_department_id);
        }


        data_set($modelData, 'bucket_images', $this->strict);

        $masterAsset = DB::transaction(function () use ($masterFamily, $modelData, $tradeUnits, $shopProducts) {
            /** @var MasterAsset $masterAsset */
            $masterAsset = $masterFamily->masterAssets()->create($modelData);
            $masterAsset->stats()->create();
            $masterAsset->orderingIntervals()->create();
            $masterAsset->salesIntervals()->create();
            $masterAsset->orderingStats()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $masterAsset->timeSeries()->create(['frequency' => $frequency]);
            }
            $this->processTradeUnits($masterAsset, $tradeUnits);
            $masterAsset->refresh();

            if ($masterAsset->type == MasterAssetTypeEnum::PRODUCT  && count($shopProducts) > 0) {

                StoreProductFromMasterProduct::make()->action($masterAsset, [
                    'shop_products' => $shopProducts
                ]);
            }


            return ModelHydrateSingleTradeUnits::run($masterAsset);
        });

        CloneMasterAssetImagesFromTradeUnits::run($masterAsset);

        GroupHydrateMasterAssets::dispatch($masterFamily->group)->delay($this->hydratorsDelay);
        MasterShopHydrateMasterAssets::dispatch($masterAsset->masterShop)->delay($this->hydratorsDelay);
        if ($masterAsset->masterdepartment) {
            MasterDepartmentHydrateMasterAssets::dispatch($masterAsset->masterDepartment)->delay($this->hydratorsDelay);
        }
        if ($masterAsset->masterFamily) {
            MasterFamilyHydrateMasterAssets::dispatch($masterAsset->masterFamily)->delay($this->hydratorsDelay);
        }

        SendSlackNotification::dispatch($masterAsset);

        return $masterAsset;
    }

    public function processTradeUnits(MasterAsset $masterAsset, array $tradeUnits): void
    {
        $stocks = [];
        foreach ($tradeUnits as $item) {
            $tradeUnit = TradeUnit::find(Arr::get($item, 'id'));
            $masterAsset->tradeUnits()->attach($tradeUnit->id, [
                'quantity' => Arr::get($item, 'quantity')
            ]);


            foreach ($tradeUnit->stocks as $stock) {
                $stocks[$stock->id] = [
                    'quantity' =>  Arr::get($item, 'quantity') / $stock->pivot->quantity ,
                ];
            }
        }

        $masterAsset->stocks()->sync($stocks);
        $masterAsset->refresh();

    }


    public function rules(): array
    {
        $rules = [
            'code'                     => [
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_assets',
                    extraConditions: [
                        ['column' => 'master_shop_id', 'value' => $this->masterFamily->master_shop_id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'                     => ['required', 'max:250', 'string'],
            'master_family_id'         => [
                'sometimes',
                'nullable',
                Rule::exists('master_product_categories', 'id')
                    ->where('master_shop_id', $this->masterFamily->master_shop_id)
                    ->where('type', ProductCategoryTypeEnum::FAMILY)
            ],
            'master_sub_department_id' => [
                'sometimes',
                'nullable',
                Rule::exists('master_product_categories', 'id')
                    ->where('master_shop_id', $this->masterFamily->master_shop_id)
                    ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ],
            'image_id'                 => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->group->id)],
            'price'                    => ['sometimes', 'numeric', 'min:0'],
            'unit'                     => ['sometimes', 'required', 'string'],
            'rrp'                      => ['sometimes', 'required', 'numeric', 'min:0'],
            'description'              => ['sometimes', 'nullable', 'max:15000'],
            'data'                     => ['sometimes', 'array'],
            'is_main'                  => ['sometimes', 'boolean'],
            'main_master_asset_id'     => [
                'sometimes',
                'nullable',
                Rule::exists('master_assets', 'id')
                    ->where('group_id', $this->group->id)
            ],
            'variant_ratio'            => ['sometimes', 'required', 'numeric', 'gt:0'],
            'variant_is_visible'       => ['sometimes', 'required', 'boolean'],
            'trade_units'              => ['sometimes', 'array', 'nullable'],
            'type'                     => ['required', Rule::enum(MasterAssetTypeEnum::class)],
            'shop_products'            => ['sometimes', 'array'],
            'units'                  => ['sometimes'],
            'description_title'      => ['sometimes', 'string', 'nullable', 'max:300'],
            'description_extra'      => ['sometimes', 'string', 'nullable', 'max:15000'],
            'marketing_weight'       => ['sometimes', 'numeric', 'min:0'],
            'gross_weight'           => ['sometimes', 'numeric', 'min:0'],
            'marketing_dimensions'   => ['sometimes'],

        ];

        if (!$this->strict) {
            $rules['status'] = ['sometimes', 'required', 'boolean'];
            $rules           = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterProductCategory $masterFamily, array $modelData, int $hydratorsDelay = 0, $strict = true, $audit = true): MasterAsset
    {
        if (!$audit) {
            MasterAsset::disableAuditing();
        }

        $this->masterFamily = $masterFamily;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;


        $this->initialisationFromGroup($masterFamily->group, $modelData);
        return $this->handle($masterFamily, $this->validatedData);
    }

}
