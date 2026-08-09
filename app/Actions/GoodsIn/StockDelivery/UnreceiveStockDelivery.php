<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
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

class UnreceiveStockDelivery extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use AsAction;
    use HasStockDeliveryHydrators;
    use WithActionUpdate;
    use WithStockDeliveryItemStatePropagation;

    public int $hydratorsDelay = 0;

    private StockDelivery $stockDelivery;

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDelivery->state !== StockDeliveryStateEnum::RECEIVED) {
            $validator->errors()->add('state', __('You can not unmark this stock delivery as received with state :state', ['state' => $this->stockDelivery->state->value]));
        }
    }

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        $stockDelivery->items()
            ->where('state', StockDeliveryItemStateEnum::RECEIVED)
            ->get()
            ->each(fn (StockDeliveryItem $stockDeliveryItem) => $this->unreceiveItem($stockDeliveryItem));

        $stockDelivery->update([
            'state'       => $this->previousStockDeliveryState($stockDelivery),
            'received_at' => null,
        ]);

        UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery);

        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery;
    }

    private function unreceiveItem(StockDeliveryItem $stockDeliveryItem): void
    {
        $previousState = $this->previousStockDeliveryItemState($stockDeliveryItem);

        $stockDeliveryItem = $this->update($stockDeliveryItem, [
            'state'       => $previousState,
            'received_at' => null,
        ]);

        $this->propagateStockDeliveryItemStateChange($stockDeliveryItem);
    }

    private function previousStockDeliveryState(StockDelivery $stockDelivery): StockDeliveryStateEnum
    {
        return match (true) {
            $stockDelivery->dispatched_at !== null   => StockDeliveryStateEnum::DISPATCHED,
            $stockDelivery->ready_to_ship_at !== null => StockDeliveryStateEnum::READY_TO_SHIP,
            $stockDelivery->confirmed_at !== null     => StockDeliveryStateEnum::CONFIRMED,
            default                                   => StockDeliveryStateEnum::IN_PROCESS,
        };
    }

    private function previousStockDeliveryItemState(StockDeliveryItem $stockDeliveryItem): StockDeliveryItemStateEnum
    {
        return match (true) {
            (bool) Arr::get($stockDeliveryItem->data, 'dispatched_at')    => StockDeliveryItemStateEnum::DISPATCHED,
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
