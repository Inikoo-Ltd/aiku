<?php

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\UpdatePurchaseOrdersDeliveryStateFromStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateFromGoodsIn;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Models\GoodsIn\StockDeliveryItem;
use Lorisleiva\Actions\ActionRequest;

class SetStockDeliveryItemCheckedQuantity extends OrgAction
{
    use WithActionUpdate;

    private StockDeliveryItem $stockDeliveryItem;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        return [
            'unit_quantity_checked' => ['required', 'numeric', 'gte:0'],
        ];
    }

    public function handle(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $placed  = (float) $stockDeliveryItem->unit_quantity_placed;
        $checked = max($placed, (float) $modelData['unit_quantity_checked']);

        $stockDeliveryItem = $this->update($stockDeliveryItem, [
            'unit_quantity_checked' => $checked,
            'checked_at'            => $stockDeliveryItem->checked_at ?? now(),
        ]);
        $stockDeliveryItem = CalculateStockDeliveryItemTotalPlaced::run($stockDeliveryItem);

        $stockDelivery = $stockDeliveryItem->stockDelivery;

        StockDeliveriesHydrateItems::run($stockDelivery);
        UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem::run($stockDeliveryItem);
        UpdateStockDeliveryStateFromGoodsIn::run($stockDelivery);
        UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery->refresh());

        return $stockDeliveryItem->refresh();
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

    public function jsonResponse(StockDeliveryItem $stockDeliveryItem): StockDeliveryItemResource
    {
        return new StockDeliveryItemResource($stockDeliveryItem);
    }
}
