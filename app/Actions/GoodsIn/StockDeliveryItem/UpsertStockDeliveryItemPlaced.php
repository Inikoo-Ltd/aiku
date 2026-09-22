<?php

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\GoodsIn\Sowing\StoreSowing;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\UpdatePurchaseOrdersDeliveryStateFromStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateFromGoodsIn;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Support\Arr;
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
            'location_org_stock_id' => ['required_without:location_id', Rule::Exists('location_org_stocks', 'id')->where('org_stock_id', $this->stockDeliveryItem->org_stock_id)],
            'location_id'           => ['required_without:location_org_stock_id', Rule::Exists('locations', 'id')->where('organisation_id', $this->organisation->id)],
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

            $locationId = Arr::pull($modelData, 'location_id');
            if ($locationId && !Arr::get($modelData, 'location_org_stock_id')) {
                data_set($modelData, 'location_org_stock_id', $this->findOrAssociateLocation($stockDeliveryItem, $locationId)->id);
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
