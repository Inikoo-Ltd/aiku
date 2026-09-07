<?php

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\GoodsIn\StockDeliveryItem\Traits\WithStockDeliveryItemStatePropagation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Models\GoodsIn\StockDeliveryItem;
use Lorisleiva\Actions\ActionRequest;

class UpdateStateToConfirmedStockDeliveryItem extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithActionUpdate;
    use WithStockDeliveryItemStatePropagation;

    public function handle(StockDeliveryItem $stockDeliveryItem): StockDeliveryItem
    {
        if ($stockDeliveryItem->state !== StockDeliveryItemStateEnum::IN_PROCESS) {
            abort(422, __('Only in process items can be confirmed'));
        }

        $stockDeliveryItem = $this->update($stockDeliveryItem, [
            'state' => StockDeliveryItemStateEnum::CONFIRMED,
            'data'  => ['confirmed_at' => now()->toIso8601String()],
        ], ['data']);

        $this->propagateStockDeliveryItemStateChange($stockDeliveryItem);

        return $stockDeliveryItem;
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): StockDeliveryItem
    {
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
