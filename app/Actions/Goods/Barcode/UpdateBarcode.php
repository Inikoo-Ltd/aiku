<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Barcode;

use App\Actions\Goods\Barcode\Hydrators\GroupHydrateBarcodes;
use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Http\Resources\Helpers\BarcodeResource;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Barcode;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateBarcode extends GrpAction
{
    use WithActionUpdate;

    private Barcode $barcode;

    public function handle(Barcode $barcode, array $modelData): Barcode
    {
        $previousActiveTradeUnit = $barcode->tradeUnitsActive->first();

        if (Arr::has($modelData, 'trade_unit')) {
            $newTradeUnitId = Arr::pull($modelData, 'trade_unit', null);
            $newTradeUnit = TradeUnit::find($newTradeUnitId);

            SyncBarcodeToTradeUnit::make()->action($barcode, $newTradeUnit);

            $barcode->refresh();

            if ($barcode->tradeUnitsActive()->exists()) {
                data_set($modelData, 'status', BarcodeStatusEnum::USED);
            } else {
                data_set($modelData, 'status', BarcodeStatusEnum::AVAILABLE);
            }
        }

        $barcode = $this->update($barcode, $modelData, ['data']);

        $changes = Arr::except($barcode->getChanges(), ['updated_at', 'last_fetched_at']);

        if (Arr::hasAny($changes, ['status'])) {
            GroupHydrateBarcodes::run($barcode->group);
        }

        if (Arr::hasAny($changes, ['number'])) {
            UpdateTradeUnit::make()->action($previousActiveTradeUnit, [
                'barcode_id'        => $barcode->id
            ]);
        }

        return $barcode;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("goods.edit");
    }

    public function rules(): array
    {
        $rules = [
            'number'        => [
                'sometimes',
                'required',
                'numeric',
                new IUnique(
                    table: 'barcodes',
                    extraConditions: [
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
            'note'          => ['sometimes', 'nullable', 'string', 'max:1000'],
            'data'          => ['sometimes', 'nullable', 'array'],
            'status'        => ['sometimes', 'required', Rule::enum(BarcodeStatusEnum::class)],
            'trade_unit'    => [
                'sometimes',
                'nullable',
                'exists:trade_units,id',
                new IUnique(
                    table: 'model_has_barcodes',
                    column: 'model_id',
                    extraConditions: [
                        ['column' => 'status', 'value' => true],
                        ['column' => 'model_type', 'value'  => class_basename(TradeUnit::class)]
                    ],
                    caseSensitive: false
                ),
            ],
        ];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function asController(Barcode $barcode, ActionRequest $request): Barcode
    {
        $this->initialisation(group(), $request);

        return $this->handle($barcode, $this->validatedData);
    }

    public function action(Barcode $barcode, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Barcode
    {
        $this->strict = $strict;
        if (!$audit) {
            Barcode::disableAuditing();
        }
        $this->asAction       = true;
        $this->barcode        = $barcode;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($barcode->group, $modelData);

        return $this->handle($barcode, $this->validatedData);
    }

    public function jsonResponse(Barcode $barcode): BarcodeResource
    {
        return new BarcodeResource($barcode);
    }
}
