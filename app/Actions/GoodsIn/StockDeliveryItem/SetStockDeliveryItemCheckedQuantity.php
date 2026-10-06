<?php

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\UpdatePurchaseOrdersDeliveryStateFromStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateFromGoodsIn;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class SetStockDeliveryItemCheckedQuantity extends OrgAction
{
    use WithGoodsInBookInAuthorisation;
    use WithActionUpdate;

    private StockDeliveryItem $stockDeliveryItem;

    public function rules(): array
    {
        return [
            'unit_quantity_checked' => ['required_without:sko_quantity_checked', 'numeric', 'gte:0'],
            'sko_quantity_checked'  => ['required_without:unit_quantity_checked', 'numeric', 'gte:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $stockDelivery = $this->stockDeliveryItem->stockDelivery;

        if ($this->stockDeliveryItem->canBeReceivedAfterAll()) {
            return;
        }

        if ($this->stockDeliveryItem->state === StockDeliveryItemStateEnum::CANCELLED
            || !($stockDelivery->isInGoodsIn() || $stockDelivery->state === StockDeliveryStateEnum::BOOKED_IN)) {
            $validator->errors()->add('unit_quantity_checked', __('Items can only be checked while the delivery is being booked in'));
        }
    }

    public function handle(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $placed  = (float) $stockDeliveryItem->unit_quantity_placed;
        $checked = max($placed, (float) (isset($modelData['sko_quantity_checked'])
            ? round($modelData['sko_quantity_checked'] * $stockDeliveryItem->unitsPerSko(), 4)
            : $modelData['unit_quantity_checked']));

        if ($checked > 0 && $stockDeliveryItem->canBeReceivedAfterAll()) {
            $stockDeliveryItem->stockDelivery->update([
                'state'     => StockDeliveryStateEnum::BOOKED_IN,
                'placed_at' => null,
                'is_costed' => false,
            ]);
        }

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

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function jsonResponse(StockDeliveryItem $stockDeliveryItem): StockDeliveryItemResource
    {
        return new StockDeliveryItemResource($stockDeliveryItem);
    }
}
