<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateAssets;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterAssets;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateOrders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterAssets;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrders;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
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

        if (!Arr::has($modelData, 'unit') && count($tradeUnits) == 1) {
            data_set($modelData, 'unit', Arr::get($tradeUnits, '0.unit'));
        }


        $masterAsset = DB::transaction(function () use ($parent, $modelData, $tradeUnits) {
            $data        = [
                'code'    => Arr::get($modelData, 'code'),
                'name'    => Arr::get($modelData, 'name'),
                'price'   => Arr::get($modelData, 'price'),
                'unit'    => Arr::get($modelData, 'unit'),
                'is_main' => true,
                'type'    => MasterAssetTypeEnum::PRODUCT
            ];
            $masterAsset = StoreMasterAsset::run($parent, $data);
            $masterAsset->refresh();
            foreach ($tradeUnits as $item) {
                $tradeUnit = TradeUnit::find(Arr::get($item, 'id'));
                $masterAsset->tradeUnits()->attach($tradeUnit->id, [
                    'quantity' => Arr::get($item, 'quantity')
                ]);
                $masterAsset->refresh();
            }

            return $masterAsset;
        });

        MasterShopHydrateMasterAssets::dispatch($masterAsset->masterShop)->delay($this->hydratorsDelay);
        GroupHydrateMasterAssets::dispatch($parent->group)->delay($this->hydratorsDelay);


        return $masterAsset;
    }

    public function rules(): array
    {
        return [
            'code'                   => ['required', 'string'],
            'name'                   => ['required', 'string'],
            'unit'                   => ['sometimes', 'string'],
            'price'                  => ['required', 'numeric', 'min:0'],
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

    public function htmlResponse(MasterAsset $masterAsset): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        return Redirect::route('grp.masters.master_shops.show.master_products.show', [
            'masterShop'    => $masterAsset->masterShop->slug,
            'masterProduct' => $masterAsset->slug
        ]);
    }
}
