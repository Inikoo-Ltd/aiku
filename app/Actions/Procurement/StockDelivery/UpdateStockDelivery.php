<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 May 2023 14:50:49 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithNoStrictProcurementOrderRules;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Procurement\StockDelivery;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdateStockDelivery extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictProcurementOrderRules;
    use WithNoStrictRules;

    private StockDelivery $stockDelivery;

    public function handle(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        return $this->update($stockDelivery, $modelData, ['data']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'reference' => [
                'sometimes',
                'required',
                $this->strict ? 'alpha_dash' : 'string',
            ],
        ];

        if ($this->strict) {
            $rules['reference'][] = new IUnique(
                table: 'stock_deliveries',
                extraConditions: [
                    [
                        'column' => 'organisation_id',
                        'value' => $this->organisation->id,
                    ],
                    [
                        'column' => 'id',
                        'operator' => '!=',
                        'value' => $this->stockDelivery->id,
                    ],
                ]
            );
        }

        if (! $this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
            $rules = $this->noStrictProcurementOrderRules($rules);
            $rules = $this->noStrictStockDeliveryRules($rules);
        }

        return $rules;
    }

    public function action(StockDelivery $stockDelivery, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): StockDelivery
    {
        $this->strict = $strict;
        if (! $audit) {
            StockDelivery::disableAuditing();
        }
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }
}
