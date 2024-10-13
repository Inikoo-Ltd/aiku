<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 May 2023 11:42:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\Goods\Stock\Hydrators\StockHydrateGrossWeightFromTradeUnits;
use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Goods\TradeUnit;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateTradeUnit extends GrpAction
{
    use WithActionUpdate;


    private TradeUnit $tradeUnit;

    public function handle(TradeUnit $tradeUnit, array $modelData): TradeUnit
    {
        $tradeUnit = $this->update($tradeUnit, $modelData, ['data', 'dimensions']);
        if ($tradeUnit->wasChanged('gross_weight')) {
            foreach ($tradeUnit->stocks as $stock) {
                StockHydrateGrossWeightFromTradeUnits::dispatch($stock);
            }

        }

        return $tradeUnit;
    }

    public function rules(): array
    {
        $rules = [
            'code'             => [
                'sometimes',
                'required',
                'max:64',
                new AlphaDashDot(),
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'trade_units',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->tradeUnit->id
                        ],

                    ]
                ),
            ],
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
            'description'      => ['sometimes', 'required', 'string', 'max:1024'],
            'barcode'          => ['sometimes', 'required'],
            'gross_weight'     => ['sometimes', 'required', 'numeric'],
            'net_weight'       => ['sometimes', 'required', 'numeric'],
            'marketing_weight' => ['sometimes', 'required', 'numeric'],
            'dimensions'       => ['sometimes', 'required'],
            'type'             => ['sometimes', 'required'],
            'image_id'         => ['sometimes', 'required', 'exists:media,id'],
            'data'             => ['sometimes', 'required']
        ];

        if (!$this->strict) {
            $rules['gross_weight'] = ['sometimes', 'nullable', 'numeric'];
            $rules['net_weight']   = ['sometimes', 'nullable', 'numeric'];
        }

        return $rules;
    }

    public function action(TradeUnit $tradeUnit, array $modelData, int $hydratorsDelay = 0, bool $strict = true): TradeUnit
    {
        $this->tradeUnit      = $tradeUnit;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($tradeUnit->group, $modelData);

        return $this->handle($tradeUnit, $this->validatedData);
    }

    public function asController(TradeUnit $tradeUnit, ActionRequest $request): TradeUnit
    {
        $this->tradeUnit      = $tradeUnit;
        $this->initialisation($tradeUnit->group, $request);
        return $this->handle($tradeUnit, $this->validatedData);
    }
}
