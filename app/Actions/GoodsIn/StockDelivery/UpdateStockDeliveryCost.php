<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithStockDeliveryCostingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\GoodsIn\StockDeliveryCost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateStockDeliveryCost extends OrgAction
{
    use WithStockDeliveryCostingEditAuthorisation;
    use WithActionUpdate;

    private StockDeliveryCost $stockDeliveryCost;

    public function rules(): array
    {
        return [
            'label'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'amount'      => ['sometimes', 'nullable', 'numeric', 'gte:0'],
            'received_at' => ['sometimes', 'nullable', 'date'],
            'is_na'       => ['sometimes', 'boolean'],
            'currency_id' => ['sometimes', 'nullable', 'exists:currencies,id'],
            'exchange'    => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->asAction && $this->stockDeliveryCost->stockDelivery->is_costed) {
            $validator->errors()->add('state', __('This stock delivery is costed, an accounting manager can change it with Update costing'));
        }
    }

    public function handle(StockDeliveryCost $stockDeliveryCost, array $modelData): StockDeliveryCost
    {
        $stockDeliveryCost = $this->update($stockDeliveryCost, StoreStockDeliveryCost::withExchange($stockDeliveryCost->stockDelivery, $modelData));

        EvaluateStockDeliveryCosting::run($stockDeliveryCost->stockDelivery);

        return $stockDeliveryCost;
    }

    public function asController(StockDeliveryCost $stockDeliveryCost, ActionRequest $request): StockDeliveryCost
    {
        $this->stockDeliveryCost = $stockDeliveryCost;
        $this->initialisation($stockDeliveryCost->organisation, $request);

        return $this->handle($stockDeliveryCost, $this->validatedData);
    }

    public function action(StockDeliveryCost $stockDeliveryCost, array $modelData): StockDeliveryCost
    {
        $this->asAction          = true;
        $this->stockDeliveryCost = $stockDeliveryCost;
        $this->initialisation($stockDeliveryCost->organisation, $modelData);

        return $this->handle($stockDeliveryCost, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
