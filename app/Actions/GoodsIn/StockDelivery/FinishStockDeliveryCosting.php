<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class FinishStockDeliveryCosting extends OrgAction
{
    private StockDelivery $stockDelivery;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("org-supervisor.{$this->organisation->id}.accounting");
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDelivery->state !== StockDeliveryStateEnum::PLACED
            || $this->stockDelivery->is_costed
            || !Arr::has($this->stockDelivery->data, 'costing_reopened')) {
            $validator->errors()->add('state', __('This stock delivery costing is not being updated'));
        }
    }

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        $stockDelivery = EvaluateStockDeliveryCosting::run($stockDelivery, finishing: true);

        if ($stockDelivery->is_costed) {
            return $stockDelivery;
        }

        $unbalanced = EvaluateStockDeliveryCosting::unbalancedHandSplits($stockDelivery);
        if ($unbalanced) {
            throw ValidationException::withMessages([
                'costing' => collect($unbalanced)->map(fn (array $totals, string $field) => __('The lines add up to :allocated of :field, the cost is :amount', [
                    'allocated' => $totals['allocated'],
                    'field'     => str_replace('cost_', '', $field),
                    'amount'    => $totals['amount'],
                ]))->values()->all(),
            ]);
        }

        throw ValidationException::withMessages([
            'costing' => __('Every cost in the checklist must be received or marked N/A before the costing can be finished'),
        ]);
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery);
    }

    public function action(StockDelivery $stockDelivery): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, []);

        return $this->handle($stockDelivery);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
