<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-25 14:10
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\GoodsIn\StockDelivery\AddPurchaseOrdersToStockDelivery;
use App\Actions\GoodsIn\StockDeliveryItem\Traits\WithStockDeliveryItemStatePropagation;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Undoes the last shipping step of an item before the container leaves: ready to ship goes back to confirmed,
 * confirmed goes back to in process.
 */
class StepBackStockDeliveryItemShippingState extends OrgAction
{
    use WithActionUpdate;
    use WithStockDeliveryItemStatePropagation;

    private const array PREVIOUS_STATE = [
        StockDeliveryItemStateEnum::READY_TO_SHIP->value => StockDeliveryItemStateEnum::CONFIRMED,
        StockDeliveryItemStateEnum::CONFIRMED->value     => StockDeliveryItemStateEnum::IN_PROCESS,
    ];

    private const array DELIVERY_STATES_BEFORE_DISPATCH = [
        StockDeliveryStateEnum::IN_PROCESS,
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
    ];

    private StockDeliveryItem $stockDeliveryItem;

    public static function canStepBack(StockDeliveryItem $stockDeliveryItem): bool
    {
        return isset(self::PREVIOUS_STATE[$stockDeliveryItem->state->value])
            && in_array($stockDeliveryItem->stockDelivery?->state, self::DELIVERY_STATES_BEFORE_DISPATCH, true);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return AddPurchaseOrdersToStockDelivery::userCanAdd($this->stockDeliveryItem->stockDelivery, $request->user());
    }

    public function handle(StockDeliveryItem $stockDeliveryItem): StockDeliveryItem
    {
        if (!self::canStepBack($stockDeliveryItem)) {
            abort(422, __('Only confirmed or ready to ship items of a container not yet dispatched can be stepped back'));
        }

        $stockDeliveryItem = $this->update($stockDeliveryItem, [
            'state' => self::PREVIOUS_STATE[$stockDeliveryItem->state->value],
            'data'  => ['shipping_state_stepped_back_at' => now()->toIso8601String()],
        ], ['data']);

        $this->propagateStockDeliveryItemStateChange($stockDeliveryItem);

        return $stockDeliveryItem;
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): StockDeliveryItem
    {
        if ($stockDeliveryItem->stockDelivery->isManagedByPartner()) {
            throw ValidationException::withMessages(['state' => __('This delivery is managed by the partner until you receive it')]);
        }

        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, $request);

        return $this->handle($stockDeliveryItem);
    }

    public function action(StockDeliveryItem $stockDeliveryItem): StockDeliveryItem
    {
        $this->asAction = true;
        $this->initialisation($stockDeliveryItem->organisation, []);

        return $this->handle($stockDeliveryItem);
    }

    public function jsonResponse(StockDeliveryItem $stockDeliveryItem): StockDeliveryItemResource
    {
        return new StockDeliveryItemResource($stockDeliveryItem);
    }
}
