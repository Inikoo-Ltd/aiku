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
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
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
     * @throws \Throwable
     */
    public function handle(MasterShop|MasterProductCategory $parent, array $modelData): MasterAsset
    {
        $tradeUnits = Arr::pull($modelData, 'trade_units', []);
        $shopProducts = Arr::pull($modelData, 'shop_products', []);

        if (count($tradeUnits) == 1) {
            $units = $tradeUnits[array_key_first($tradeUnits)]['quantity'];
        } else {
            $units = 1;
        }

        data_set($modelData, 'units', $units);


        data_set($modelData, 'group_id', $parent->group_id);

        if ($parent instanceof MasterProductCategory) {
            data_set($modelData, 'master_department_id', $parent->master_department_id);
            data_set($modelData, 'master_shop_id', $parent->master_shop_id);

            if ($parent->type == MasterProductCategoryTypeEnum::FAMILY) {
                data_set($modelData, 'master_family_id', $parent->id);
                if ($parent->master_sub_department_id) {
                    data_set($modelData, 'master_sub_department_id', $parent->master_sub_department_id);
                }
            }
            if ($parent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                data_set($modelData, 'master_sub_department_id', $parent->id);
            }
        }

        $masterAsset = DB::transaction(function () use ($parent, $modelData, $tradeUnits, $shopProducts) {
            /** @var MasterAsset $masterAsset */
            $masterAsset = $parent->masterAssets()->create($modelData);
            $masterAsset->stats()->create();
            $masterAsset->orderingIntervals()->create();
            $masterAsset->salesIntervals()->create();
            $masterAsset->orderingStats()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $masterAsset->timeSeries()->create(['frequency' => $frequency]);
            }
            $this->processTradeUnits($masterAsset, $tradeUnits);
            $masterAsset->refresh();

            if ($masterAsset->type == MasterAssetTypeEnum::PRODUCT) {
                StoreProductFromMasterProduct::make()->action($masterAsset, [
                    'shop_products' => $shopProducts
                ]);
            }

            return ModelHydrateSingleTradeUnits::run($masterAsset);
        });

        GroupHydrateMasterAssets::dispatch($parent->group)->delay($this->hydratorsDelay);
        MasterShopHydrateMasterAssets::dispatch($masterAsset->masterShop)->delay($this->hydratorsDelay);
        if ($masterAsset->masterdepartment) {
            MasterDepartmentHydrateMasterAssets::dispatch($masterAsset->masterDepartment)->delay($this->hydratorsDelay);
        }
        if ($masterAsset->masterFamily) {
            MasterFamilyHydrateMasterAssets::dispatch($masterAsset->masterFamily)->delay($this->hydratorsDelay);
        }


        return $masterAsset;
    }

    public function processTradeUnits(MasterAsset $masterAsset, array $tradeUnits)
    {
        foreach ($tradeUnits as $item) {
            $tradeUnit = TradeUnit::find(Arr::get($item, 'id'));
            $masterAsset->tradeUnits()->attach($tradeUnit->id, [
                'quantity' => Arr::get($item, 'quantity')
            ]);

            if (!empty($tradeUnit->stock)) { //TODO: Need to know what to do if trade unit has no stock
                foreach ($tradeUnit->stocks as $stock) {
                    $stocks[$stock->id] = [
                        'quantity' => $stock->pivot->quantity,
                    ];
                }
                $masterAsset->stocks()->sync($stocks);
            }

            $masterAsset->refresh();
        }
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
                        ['column' => 'group_id', 'value' => $this->group->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'                     => ['required', 'max:250', 'string'],
            'master_family_id'         => [
                'sometimes',
                'nullable',
                Rule::exists('master_product_categories', 'id')
                    ->where('group_id', $this->group->id)
                    ->where('type', ProductCategoryTypeEnum::FAMILY)
            ],
            'master_sub_department_id' => [
                'sometimes',
                'nullable',
                Rule::exists('master_product_categories', 'id')
                    ->where('group_id', $this->group->id)
                    ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ],
            'image_id'                 => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->group->id)],
            'price'                    => ['required', 'numeric', 'min:0'],
            'unit'                     => ['sometimes', 'required', 'string'],
            'rrp'                      => ['sometimes', 'required', 'numeric', 'min:0'],
            'description'              => ['sometimes', 'nullable', 'max:10000'],
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
            'shop_products'            => ['sometimes', 'array']

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
    public function action(MasterShop|MasterProductCategory $parent, array $modelData, int $hydratorsDelay = 0, $strict = true, $audit = true): MasterAsset
    {
        if (!$audit) {
            MasterAsset::disableAuditing();
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $this->initialisationFromGroup($parent->group, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

}
