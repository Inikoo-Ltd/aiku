<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 02:37:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateMasterAssets;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterAssets;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterAssets;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterAsset extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithMastersEditAuthorisation;


    private MasterAsset $masterAsset;

    public function handle(MasterAsset $masterAsset, array $modelData): MasterAsset
    {

        if (Arr::has($modelData, 'master_family_id')) {

            $masterFamily = null;
            if ($modelData['master_family_id']) {
                $masterFamily = MasterProductCategory::where('id', $modelData['master_family_id'])->first();
            }

            $masterDepartmentID = $masterFamily?->master_department_id;
            data_set($modelData, 'master_department_id', $masterDepartmentID);
        }

        if (Arr::has($modelData, 'name_i8n')) {
            UpdateMasterProductTranslationsFromUpdate::make()->action($masterAsset, [
                'translations' => [
                    'name' => Arr::pull($modelData, 'name_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_title_i8n')) {
            UpdateMasterProductTranslationsFromUpdate::make()->action($masterAsset, [
                'translations' => [
                    'description_title' => Arr::pull($modelData, 'description_title_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_i8n')) {
            UpdateMasterProductTranslationsFromUpdate::make()->action($masterAsset, [
                'translations' => [
                    'description' => Arr::pull($modelData, 'description_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_extra_i8n')) {
            UpdateMasterProductTranslationsFromUpdate::make()->action($masterAsset, [
                'translations' => [
                    'description_extra' => Arr::pull($modelData, 'description_extra_i8n')
                ]
            ]);
        }

        $tradeUnits     = Arr::pull($modelData, 'trade_units', []);

        $masterAsset    = DB::transaction(function () use ($masterAsset, $modelData, $tradeUnits) {
            /** @var MasterAsset $masterAsset */
            if (count($tradeUnits) > 0) {
                $this->processTradeUnits($masterAsset, $tradeUnits);
                if (count($tradeUnits) == 1) {
                    data_set($modelData, 'units', $tradeUnits[0]['quantity']);
                    data_set($modelData, 'unit', $tradeUnits[0]['type']);
                } else {
                    data_set($modelData, 'units', 1);
                    data_set($modelData, 'unit', 'bundle');
                }
            }
            $this->update($masterAsset, $modelData);

            return ModelHydrateSingleTradeUnits::run($masterAsset);
        });

        CloneMasterAssetImagesFromTradeUnits::run($masterAsset);

        if ($masterAsset->wasChanged('master_family_id')) {
            $masterFamily = MasterProductCategory::find($masterAsset->master_family_id);

            $shopsUnderMaster = DB::table('shops')
                ->where('master_shop_id', $masterAsset->masterShop->id)
                ->pluck('id')
                ->toArray();

            $masterFamilyIds = $masterFamily->productCategories()
                ->whereIn('shop_id', $shopsUnderMaster)
                ->pluck('id', 'shop_id');

            $products = $masterAsset->products()
                ->whereIn('shop_id', $shopsUnderMaster)
                ->get();

            foreach ($products as $product) {
                if (isset($masterFamilyIds[$product->shop_id])) {
                    $product->updateQuietly([
                        'family_id' => $masterFamilyIds[$product->shop_id]
                    ]);
                }
            }
        }

        if ($masterAsset->wasChanged('status')) {
            GroupHydrateMasterAssets::dispatch($masterAsset->group)->delay($this->hydratorsDelay);
            MasterShopHydrateMasterAssets::dispatch($masterAsset->masterShop)->delay($this->hydratorsDelay);
            if ($masterAsset->masterdepartment) {
                MasterDepartmentHydrateMasterAssets::dispatch($masterAsset->masterDepartment)->delay($this->hydratorsDelay);
            }
            if ($masterAsset->masterFamily) {
                MasterFamilyHydrateMasterAssets::dispatch($masterAsset->masterFamily)->delay($this->hydratorsDelay);
            }
        }

        return $masterAsset;
    }

    public function processTradeUnits(MasterAsset $masterAsset, array $tradeUnitsRaw): void
    {
        $stocks = [];
        $tradeUnits = [];
        foreach ($tradeUnitsRaw as $item) {
            $tradeUnit = TradeUnit::find(Arr::get($item, 'id'));
            $tradeUnits[$tradeUnit->id] = [
                'quantity' => Arr::get($item, 'quantity')
            ];


            foreach ($tradeUnit->stocks as $stock) {
                $stocks[$stock->id] = [
                    'quantity' =>  Arr::get($item, 'quantity') / $stock->pivot->quantity ,
                ];
            }
        }

        $masterAsset->tradeUnits()->sync($tradeUnits);
        $masterAsset->stocks()->sync($stocks);
        $masterAsset->refresh();
    }

    public function rules(): array
    {
        $rules = [
            'code'             => [
                'sometimes',
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_assets',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'value' => $this->masterAsset->id, 'operator' => '!=']
                    ]
                ),
            ],
            'trade_units'      => ['sometimes', 'array', 'nullable'],
            'name'             => ['sometimes', 'required', 'max:250', 'string'],
            'price'            => ['sometimes', 'required', 'numeric', 'min:0'],
            'description'      => ['sometimes', 'required', 'max:1500'],
            'description_title' => ['sometimes', 'nullable', 'max:255'],
            'description_extra' => ['sometimes', 'nullable', 'max:65500'],
            'rrp'              => ['sometimes', 'required', 'numeric'],
            'unit'             => ['sometimes', 'string'],
            'data'             => ['sometimes', 'array'],
            'status'           => ['sometimes', 'required', 'boolean'],
            'master_family_id' => [
                'sometimes',
                'nullable',
                Rule::exists('master_product_categories', 'id')
                    ->where('master_shop_id', $this->masterAsset->master_shop_id)
                    ->where('type', MasterProductCategoryTypeEnum::FAMILY)
            ],
            'name_i8n' => ['sometimes', 'array'],
            'description_title_i8n' => ['sometimes', 'array'],
            'description_i8n' => ['sometimes', 'array'],
            'description_extra_i8n' => ['sometimes', 'array'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterAsset $masterAsset, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): MasterAsset
    {
        $this->strict = $strict;
        if (!$audit) {
            MasterAsset::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->masterAsset    = $masterAsset;

        $this->initialisationFromGroup($masterAsset->group, $modelData);

        return $this->handle($masterAsset, $this->validatedData);
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): MasterAsset
    {
        $this->masterAsset = $masterAsset;
        $this->initialisationFromGroup($masterAsset->group, $request);

        return $this->handle($masterAsset, $this->validatedData);
    }
}
