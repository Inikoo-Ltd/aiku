<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateLeadTime;
use App\Actions\SupplyChain\SupplierProduct\Hydrators\SupplierProductHydrateLeadTime;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStockDeliveryStateFromGoodsIn
{
    use AsAction;
    use HasStockDeliveryHydrators;

    public int $hydratorsDelay = 0;

    private const GOODS_IN_STATES = [
        StockDeliveryStateEnum::RECEIVED,
        StockDeliveryStateEnum::CHECKED,
        StockDeliveryStateEnum::BOOKING_IN,
        StockDeliveryStateEnum::BOOKED_IN,
    ];

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        if (!in_array($stockDelivery->state, self::GOODS_IN_STATES, true)) {
            return $stockDelivery;
        }

        $counts = $stockDelivery->items()
            ->selectRaw('state, count(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        $settled        = (int) Arr::get($counts, StockDeliveryItemStateEnum::CANCELLED->value, 0)
            + (int) Arr::get($counts, StockDeliveryItemStateEnum::NOT_RECEIVED->value, 0);
        $active         = (int) $counts->sum() - $settled;
        $placed         = (int) Arr::get($counts, StockDeliveryItemStateEnum::PLACED->value, 0);
        $checkedOrAbove = $placed + (int) Arr::get($counts, StockDeliveryItemStateEnum::CHECKED->value, 0);

        $anyPlaced = $stockDelivery->items()
            ->whereNotIn('state', [StockDeliveryItemStateEnum::CANCELLED, StockDeliveryItemStateEnum::NOT_RECEIVED])
            ->where('unit_quantity_placed', '>', 0)
            ->exists();

        if ($active === 0) {
            return $stockDelivery;
        }

        $newState = match (true) {
            $placed === $active                       => StockDeliveryStateEnum::BOOKED_IN,
            $checkedOrAbove === $active && $anyPlaced => StockDeliveryStateEnum::BOOKING_IN,
            $checkedOrAbove >= 1                      => StockDeliveryStateEnum::CHECKED,
            default                                   => StockDeliveryStateEnum::RECEIVED,
        };

        if ($newState === $stockDelivery->state) {
            return $stockDelivery;
        }

        $stockDelivery->update($this->stateTimestamps($stockDelivery, $newState));

        UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery);

        if ($newState === StockDeliveryStateEnum::BOOKED_IN) {
            $this->dispatchLeadTimeHydrators($stockDelivery);
        }

        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery;
    }

    private function dispatchLeadTimeHydrators(StockDelivery $stockDelivery): void
    {
        $transactions = DB::table('purchase_order_transactions')
            ->join('purchase_order_stock_delivery', 'purchase_order_stock_delivery.purchase_order_id', 'purchase_order_transactions.purchase_order_id')
            ->where('purchase_order_stock_delivery.stock_delivery_id', $stockDelivery->id)
            ->select('purchase_order_transactions.org_stock_id', 'purchase_order_transactions.supplier_product_id')
            ->get();

        foreach ($transactions->pluck('org_stock_id')->filter()->unique() as $orgStockId) {
            if ($orgStock = OrgStock::find($orgStockId)) {
                OrgStockHydrateLeadTime::dispatch($orgStock);
            }
        }

        foreach ($transactions->pluck('supplier_product_id')->filter()->unique() as $supplierProductId) {
            if ($supplierProduct = SupplierProduct::find($supplierProductId)) {
                SupplierProductHydrateLeadTime::dispatch($supplierProduct);
            }
        }
    }

    private function stateTimestamps(StockDelivery $stockDelivery, StockDeliveryStateEnum $newState): array
    {
        $sequence = [
            StockDeliveryStateEnum::RECEIVED->value   => 'received_at',
            StockDeliveryStateEnum::CHECKED->value    => 'checked_at',
            StockDeliveryStateEnum::BOOKING_IN->value => 'booking_in_at',
            StockDeliveryStateEnum::BOOKED_IN->value  => 'booked_in_at',
        ];

        $states   = array_keys($sequence);
        $newIndex = array_search($newState->value, $states, true);

        $update = ['state' => $newState];

        foreach ($states as $index => $stateValue) {
            if ($index < $newIndex) {
                continue;
            }

            $field = $sequence[$stateValue];

            $update[$field] = $index === $newIndex
                ? ($stockDelivery->{$field} ?? now())
                : null;
        }

        return $update;
    }

    public function action(StockDelivery $stockDelivery): StockDelivery
    {
        return $this->handle($stockDelivery);
    }
}
