<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterAssets;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterAssets;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreMasterProductFromTradeUnits extends GrpAction
{
    use WithNoStrictRules;
    use WithMastersEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $parent, array $modelData): MasterAsset
    {
        $tradeUnits = Arr::pull($modelData, 'trade_units', []);
        $shopProducts = Arr::pull($modelData, 'shop_products', []);

        if (!Arr::has($modelData, 'unit') && count($tradeUnits) == 1) {
            data_set($modelData, 'unit', Arr::get($tradeUnits, '0.type'));
        }


        $masterAsset = DB::transaction(function () use ($parent, $modelData, $tradeUnits, $shopProducts) {
            $data        = [
                'code'    => Arr::get($modelData, 'code'),
                'name'    => Arr::get($modelData, 'name'),
                'unit'    => Arr::get($modelData, 'unit'),
                'is_main' => true,
                'type'    => MasterAssetTypeEnum::PRODUCT,
                'trade_units'  => $tradeUnits,
                'shop_products' => $shopProducts
            ];

            $masterAsset = StoreMasterAsset::make()->action($parent, $data);
            $masterAsset->refresh();
            return $masterAsset;
        });

        MasterShopHydrateMasterAssets::dispatch($masterAsset->masterShop)->delay($this->hydratorsDelay);
        GroupHydrateMasterAssets::dispatch($parent->group)->delay($this->hydratorsDelay);


        return $masterAsset;
    }

    public function rules(): array
    {
        return [
            'code'                     => [
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_assets',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
            'name'                   => ['required', 'string'],
            'unit'                   => ['sometimes', 'string'],
            'price'                  => ['sometimes', 'numeric', 'min:0'],
            'trade_units'            => [
                'required',
                'array'
            ],
            'trade_units.*.id'       => [
                'required',
                'integer',
                'exists:trade_units,id'
            ],
            'trade_units.*.quantity' => [
                'required',
                'numeric',
                'min:1'
            ],
            'shop_products' => ['sometimes', 'array']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterProductCategory $parent, array $modelData, int $hydratorsDelay = 0, $strict = true, $audit = true): MasterAsset
    {
        if (!$audit) {
            MasterAsset::disableAuditing();
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $this->initialisation($parent->group, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    public function asController(MasterProductCategory $masterFamily, ActionRequest $request): MasterAsset
    {
        $this->initialisation($masterFamily->group, $request);
        return $this->handle($masterFamily, $this->validatedData);
    }
}
