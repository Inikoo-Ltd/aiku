<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 May 2023 14:50:49 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithNoStrictProcurementOrderRules;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateStockDelivery extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictProcurementOrderRules;
    use WithNoStrictRules;

    private StockDelivery $stockDelivery;

    private const DATA_FIELDS = [
        'delivery_type',
        'invoice_number',
        'invoice_date',
        'estimated_dispatched_date',
        'estimated_receiving_date',
        'incoterm',
        'port_of_export',
        'port_of_import',
        'delivery_address',
    ];

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        foreach (self::DATA_FIELDS as $field) {
            if (array_key_exists($field, $modelData)) {
                $modelData['data'][$field] = Arr::pull($modelData, $field);
            }
        }

        return $this->update($stockDelivery, $modelData, ['data']);
    }

    public function rules(): array
    {
        $rules = [
            'reference'                 => [
                'sometimes',
                'required',
                $this->strict ? 'alpha_dash' : 'string',
            ],
            'delivery_type'             => ['sometimes', 'nullable', 'string', 'in:parcel,container'],
            'invoice_number'            => ['sometimes', 'nullable', 'string'],
            'invoice_date'              => ['sometimes', 'nullable', 'date'],
            'estimated_dispatched_date' => ['sometimes', 'nullable', 'date'],
            'estimated_receiving_date'  => ['sometimes', 'nullable', 'date'],
            'incoterm'                  => ['sometimes', 'nullable', 'string'],
            'port_of_export'            => ['sometimes', 'nullable', 'string'],
            'port_of_import'            => ['sometimes', 'nullable', 'string'],
            'delivery_address'          => ['sometimes', 'nullable', 'string'],
        ];

        if ($this->strict) {
            $rules['reference'][] = new IUnique(
                table: 'stock_deliveries',
                extraConditions: [
                    [
                        'column' => 'organisation_id',
                        'value'  => $this->organisation->id
                    ],
                    [
                        'column'   => 'id',
                        'operator' => '!=',
                        'value'    => $this->stockDelivery->id
                    ]
                ]
            );
        }

        if (!$this->strict) {
            $rules['state'] = ['sometimes', Rule::enum(StockDeliveryStateEnum::class)];

            $rules = $this->noStrictUpdateRules($rules);
            $rules = $this->noStrictProcurementOrderRules($rules);
            $rules = $this->noStrictStockDeliveryRules($rules);
        }

        return $rules;
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): StockDelivery
    {
        $this->strict = $strict;
        if (!$audit) {
            StockDelivery::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->stockDelivery  = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $this->validatedData);
    }
}
