<?php

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\GoodsIn\Sowing\StoreSowing;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\UpdatePurchaseOrdersDeliveryStateFromStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateFromGoodsIn;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpsertStockDeliveryItemPlaced extends OrgAction
{
    use WithProcurementEditAuthorisation;
    private StockDeliveryItem $stockDeliveryItem;

    public function rules(): array
    {
        return [
            'quantity'              => ['required', 'numeric', 'gt:0'],
            'location_org_stock_id' => ['sometimes', Rule::Exists('location_org_stocks', 'id')],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $remaining = (float) $this->stockDeliveryItem->unit_quantity_checked - (float) $this->stockDeliveryItem->unit_quantity_placed;

        if ((float) $this->get('quantity') > $remaining) {
            $validator->errors()->add('quantity', __('You can not place more than the checked quantity (:remaining remaining)', ['remaining' => $remaining]));
        }
    }

    public function handle(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $stockDeliveryItem = DB::transaction(function () use ($modelData, $stockDeliveryItem) {
            $user = auth()->user();
            data_set($modelData, 'sower_user_id', $user?->id);

            StoreSowing::make()->action($stockDeliveryItem, $user, $modelData);

            $stockDeliveryItem = CalculateStockDeliveryItemTotalPlaced::run($stockDeliveryItem);

            $stockDelivery = $stockDeliveryItem->stockDelivery;

            StockDeliveriesHydrateItems::run($stockDelivery);
            UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem::run($stockDeliveryItem);
            UpdateStockDeliveryStateFromGoodsIn::run($stockDelivery);
            UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery->refresh());

            return $stockDeliveryItem;
        });

        return $stockDeliveryItem;
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
