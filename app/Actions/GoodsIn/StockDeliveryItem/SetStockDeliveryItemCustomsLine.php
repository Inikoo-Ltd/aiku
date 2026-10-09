<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\GoodsIn\StockDelivery\EvaluateStockDeliveryCosting;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithStockDeliveryCostingEditAuthorisation;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class SetStockDeliveryItemCustomsLine extends OrgAction
{
    use WithStockDeliveryCostingEditAuthorisation;

    private StockDeliveryItem $stockDeliveryItem;

    public function handle(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $stockDeliveryItem->update(['stock_delivery_customs_line_id' => $modelData['stock_delivery_customs_line_id']]);

        $stockDelivery = $stockDeliveryItem->stockDelivery;
        if ($stockDelivery->costs()->exists()) {
            EvaluateStockDeliveryCosting::run($stockDelivery);
        }

        return $stockDeliveryItem;
    }

    public function rules(): array
    {
        return [
            'stock_delivery_customs_line_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('stock_delivery_customs_lines', 'id')->where('stock_delivery_id', $this->stockDeliveryItem->stock_delivery_id),
            ],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDeliveryItem->stockDelivery->is_costed) {
            $validator->errors()->add('stock_delivery_customs_line_id', __('The delivery is costed: reopen the costing before changing the customs lines'));
        }
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): StockDeliveryItem
    {
        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, $request);

        return $this->handle($stockDeliveryItem, $this->validatedData);
    }

    public function action(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $this->asAction          = true;
        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, $modelData);

        return $this->handle($stockDeliveryItem, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
