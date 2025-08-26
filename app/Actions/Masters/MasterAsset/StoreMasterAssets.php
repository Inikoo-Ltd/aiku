<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreMasterAssets extends GrpAction
{
    use WithNoStrictRules;
    use WithMastersEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $parent, array $modelData)
    {
        DB::transaction(function () use ($parent, $modelData) {
            foreach (Arr::get($modelData, 'items', []) as $item) {
                $tradeUnit = TradeUnit::find(Arr::get($item, 'trade_unit_id'));
                // dd(Arr::get($item, 'units', 0));
                // dd($tradeUnit->unit);
                $data = [
                    'code' => $tradeUnit->code,
                    'name' => $tradeUnit->name,
                    'unit' => $tradeUnit->unit,
                    'units' => Arr::get($item, 'units', 0),
                    'description' => $tradeUnit->description,
                    'type' => MasterAssetTypeEnum::PRODUCT
                ];
                $masterAsset = StoreMasterAsset::run($parent, $data);
                $masterAsset->tradeUnits()->attach($tradeUnit->id);
                $masterAsset->refresh();
            }
        });
    }

    public function rules(): array
    {
        $rules = [
            'items'                     => [
                'required',
                'array'
            ],
            'items.*.trade_unit_id'     => [
                'required',
                'integer',
                'exists:trade_units,id'
            ],
            'items.*.units'             => [
                'required',
                'numeric',
                'min:1'
            ],
        ];

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterProductCategory $parent, array $modelData, int $hydratorsDelay = 0, $strict = true, $audit = true)
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

    public function asController(MasterProductCategory $masterFamily, ActionRequest $request)
    {
        $this->initialisation($masterFamily->group, $request);

        return $this->handle($masterFamily, $this->validatedData);
    }

}
