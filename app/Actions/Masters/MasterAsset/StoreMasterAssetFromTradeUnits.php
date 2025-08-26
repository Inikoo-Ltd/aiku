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

class StoreMasterAssetFromTradeUnits extends GrpAction
{
    use WithNoStrictRules;
    use WithMastersEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $parent, array $modelData)
    {
        dd($modelData);
        DB::transaction(function () use ($parent, $modelData) {
            $data = [
                'code' => Arr::get($modelData,'code'),
                'name' => Arr::get($modelData, 'name'),
                'price' => Arr::get($modelData, 'price'),
                'unit' => Arr::get($modelData, 'unit'),
                'is_main' => Arr::get($modelData, 'is_main'),
            ];
            $masterAsset = StoreMasterAsset::run($parent, $data);

            foreach (Arr::get($modelData, 'items', []) as $item) {
                $tradeUnit = TradeUnit::find(Arr::get($item, 'id'));
                $masterAsset->tradeUnits()->attach($tradeUnit->id, [
                    'quantity' => Arr::get($item, 'quantity')
                ]);
                $masterAsset->refresh();
            }
        });
    }

    public function rules(): array
    {
        $rules = [
            'code'  => ['required', 'string'],
            'name'  => ['required', 'string'],
            'unit'  => ['required', 'string'],
            'price'  =>  ['required', 'numeric', 'min:0'],
            'is_main'  => ['required', 'boolean'],
            'trade_units'                     => [
                'required',
                'array'
            ],
            'trade_units.*.id'     => [
                'required',
                'integer',
                'exists:trade_units,id'
            ],
            'trade_units.*.quantity'             => [
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
        // dd($request->all());
        $this->initialisation($masterFamily->group, $request);

        return $this->handle($masterFamily, $this->validatedData);
    }

}
