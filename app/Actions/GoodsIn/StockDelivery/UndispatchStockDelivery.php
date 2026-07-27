<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\GoodsIn\StockDeliveryItem\Traits\WithStockDeliveryItemStatePropagation;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UndispatchStockDelivery extends OrgAction
{
    use AsAction;
    use HasStockDeliveryHydrators;
    use WithActionUpdate;
    use WithStockDeliveryItemStatePropagation;

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
        if ($this->stockDelivery->state !== StockDeliveryStateEnum::DISPATCHED) {
            $validator->errors()->add('state', __('You can not unmark this stock delivery as dispatched with state :state', ['state' => $this->stockDelivery->state->value]));
        }
    }

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        $stockDelivery->items()
            ->where('state', StockDeliveryItemStateEnum::DISPATCHED)
            ->get()
            ->each(fn (StockDeliveryItem $stockDeliveryItem) => $this->undispatchItem($stockDeliveryItem));

        $stockDelivery->update([
            'state'         => $this->previousStockDeliveryState($stockDelivery),
            'dispatched_at' => null,
        ]);

        UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery);

        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery;
    }

    private function undispatchItem(StockDeliveryItem $stockDeliveryItem): void
    {
        $previousState = $this->previousStockDeliveryItemState($stockDeliveryItem);

        $data = $stockDeliveryItem->data;
        unset($data['dispatched_at']);

        $stockDeliveryItem = $this->update($stockDeliveryItem, [
            'state'         => $previousState,
            'dispatched_at' => null,
            'data'          => $data,
        ]);

        $this->propagateStockDeliveryItemStateChange($stockDeliveryItem);
    }

    private function previousStockDeliveryState(StockDelivery $stockDelivery): StockDeliveryStateEnum
    {
        return match (true) {
            (bool) Arr::get($stockDelivery->data, 'ready_to_ship_at') => StockDeliveryStateEnum::READY_TO_SHIP,
            (bool) Arr::get($stockDelivery->data, 'confirmed_at')     => StockDeliveryStateEnum::CONFIRMED,
            default                                                   => StockDeliveryStateEnum::IN_PROCESS,
        };
    }

    private function previousStockDeliveryItemState(StockDeliveryItem $stockDeliveryItem): StockDeliveryItemStateEnum
    {
        return match (true) {
            (bool) Arr::get($stockDeliveryItem->data, 'ready_to_ship_at') => StockDeliveryItemStateEnum::READY_TO_SHIP,
            (bool) Arr::get($stockDeliveryItem->data, 'confirmed_at')     => StockDeliveryItemStateEnum::CONFIRMED,
            default                                                       => StockDeliveryItemStateEnum::IN_PROCESS,
        };
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

    public function htmlResponse(StockDelivery $stockDelivery): RedirectResponse
    {
        return redirect()->back();
    }
}
