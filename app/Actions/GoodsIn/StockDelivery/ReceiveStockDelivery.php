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
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ReceiveStockDelivery extends OrgAction
{
    use AsAction;
    use HasStockDeliveryHydrators;
    use WithActionUpdate;
    use WithStockDeliveryItemStatePropagation;

    public int $hydratorsDelay = 0;

    private StockDelivery $stockDelivery;

    private const RECEIVABLE_STATES = [
        StockDeliveryStateEnum::IN_PROCESS,
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
        StockDeliveryStateEnum::DISPATCHED,
    ];

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function afterValidator(Validator $validator): void
    {
        if (!in_array($this->stockDelivery->state, self::RECEIVABLE_STATES, true)) {
            $validator->errors()->add('state', __('You can not receive this stock delivery with state :state', ['state' => $this->stockDelivery->state->value]));
        }
    }

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        $stockDelivery->update([
            'state'       => StockDeliveryStateEnum::RECEIVED,
            'received_at' => now(),
        ]);

        $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->get()
            ->each(fn (StockDeliveryItem $stockDeliveryItem) => $this->receiveItem($stockDeliveryItem));

        UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery);

        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery;
    }

    private function receiveItem(StockDeliveryItem $stockDeliveryItem): void
    {
        if ($stockDeliveryItem->state === StockDeliveryItemStateEnum::RECEIVED) {
            return;
        }

        $stockDeliveryItem = $this->update($stockDeliveryItem, [
            'state'       => StockDeliveryItemStateEnum::RECEIVED,
            'received_at' => now(),
        ]);

        $this->propagateStockDeliveryItemStateChange($stockDeliveryItem);
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
