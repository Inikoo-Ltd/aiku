<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 28 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateCosts;
use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class FinishStockDeliveryCosting extends OrgAction
{
    use HasStockDeliveryHydrators;
    use WithActionUpdate;

    public int $hydratorsDelay = 0;

    private StockDelivery $stockDelivery;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDelivery->state !== StockDeliveryStateEnum::BOOKED_IN) {
            $validator->errors()->add('state', __('You can only finish the costing of a booked in stock delivery'));
        }

        if (!$this->stockDelivery->is_costed) {
            $validator->errors()->add('state', __('The costing of this stock delivery has not started yet'));
        }
    }

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        StockDeliveriesHydrateCosts::run($stockDelivery);

        $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->update(['is_costed' => true]);

        $stockDelivery = $this->update($stockDelivery, [
            'state'     => StockDeliveryStateEnum::PLACED,
            'placed_at' => now(),
        ]);

        UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery);

        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery->refresh();
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
