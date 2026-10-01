<?php

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\GoodsIn\Sowing\StoreSowing;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\UpdatePurchaseOrdersDeliveryStateFromStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateFromGoodsIn;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'location_org_stock_id' => ['required_without:location_id', Rule::Exists('location_org_stocks', 'id')->where('org_stock_id', $this->stockDeliveryItem->org_stock_id)],
            'location_id'           => ['required_without:location_org_stock_id', Rule::Exists('locations', 'id')->where('organisation_id', $this->organisation->id)],
            'set_as_picking_location' => ['sometimes', 'boolean'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDeliveryItem->state === StockDeliveryItemStateEnum::CANCELLED || !$this->stockDeliveryItem->stockDelivery->isInGoodsIn()) {
            $validator->errors()->add('quantity', __('Stock can only be placed while the delivery is being booked in'));

            return;
        }

        $remaining = $this->remainingSkos($this->stockDeliveryItem);

        if ($this->exceedsRemaining((float) $this->get('quantity'), $remaining)) {
            $validator->errors()->add('quantity', __('You can not place more than the checked quantity (:remaining remaining)', ['remaining' => round($remaining, 4)]));
        }
    }

    private function remainingSkos(StockDeliveryItem $stockDeliveryItem): float
    {
        return ((float) $stockDeliveryItem->unit_quantity_checked - (float) $stockDeliveryItem->unit_quantity_placed) / $stockDeliveryItem->unitsPerSko();
    }

    private function exceedsRemaining(float $quantity, float $remaining): bool
    {
        return $quantity - $remaining > 0.00005;
    }

    public function handle(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $stockDeliveryItem = DB::transaction(function () use ($modelData, $stockDeliveryItem) {
            $stockDeliveryItem = StockDeliveryItem::lockForUpdate()->findOrFail($stockDeliveryItem->id);

            if ($this->exceedsRemaining((float) $modelData['quantity'], $this->remainingSkos($stockDeliveryItem))) {
                throw ValidationException::withMessages([
                    'quantity' => __('You can not place more than the checked quantity (:remaining remaining)', ['remaining' => round($this->remainingSkos($stockDeliveryItem), 4)]),
                ]);
            }

            $user = auth()->user();
            data_set($modelData, 'sower_user_id', $user?->id);

            $setAsPickingLocation = (bool) Arr::pull($modelData, 'set_as_picking_location', false);

            $locationId = Arr::pull($modelData, 'location_id');
            if ($locationId && !Arr::get($modelData, 'location_org_stock_id')) {
                data_set($modelData, 'location_org_stock_id', $this->findOrAssociateLocation($stockDeliveryItem, $locationId)->id);
            }

            if ($setAsPickingLocation) {
                UpdateLocationOrgStock::make()->action(
                    LocationOrgStock::findOrFail($modelData['location_org_stock_id']),
                    [
                        'set_as_priority_wholesale'    => true,
                        'set_as_priority_dropshipping' => true,
                    ]
                );
            }

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

    private function findOrAssociateLocation(StockDeliveryItem $stockDeliveryItem, int $locationId): LocationOrgStock
    {
        return LocationOrgStock::where('org_stock_id', $stockDeliveryItem->org_stock_id)->where('location_id', $locationId)->first()
            ?? StoreLocationOrgStock::make()->action($stockDeliveryItem->orgStock, Location::findOrFail($locationId), []);
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
