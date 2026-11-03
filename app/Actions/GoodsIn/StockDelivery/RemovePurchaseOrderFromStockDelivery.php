<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateDeliveryState;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RemovePurchaseOrderFromStockDelivery extends OrgAction
{
    use AsAction;
    use HasPurchaseOrderHydrators;
    use HasStockDeliveryHydrators;

    private StockDelivery $stockDelivery;
    private PurchaseOrder $purchaseOrder;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return AddPurchaseOrdersToStockDelivery::userCanAdd($this->stockDelivery, $request->user());
    }

    public static function acceptsRemovals(StockDelivery $stockDelivery): bool
    {
        return $stockDelivery->state === StockDeliveryStateEnum::IN_PROCESS && !$stockDelivery->isManagedByPartner();
    }

    public static function itemsOfPurchaseOrder(StockDelivery $stockDelivery, PurchaseOrder $purchaseOrder): HasMany
    {
        $transactions = $purchaseOrder->purchaseOrderTransactions()->get(['id', 'org_stock_id']);

        $orgStockIdsOfOtherPurchaseOrders = DB::table('purchase_order_transactions')
            ->join('purchase_order_stock_delivery', 'purchase_order_stock_delivery.purchase_order_id', 'purchase_order_transactions.purchase_order_id')
            ->where('purchase_order_stock_delivery.stock_delivery_id', $stockDelivery->id)
            ->where('purchase_order_transactions.purchase_order_id', '!=', $purchaseOrder->id)
            ->pluck('purchase_order_transactions.org_stock_id')
            ->all();

        return $stockDelivery->items()->where(function (Builder $items) use ($transactions, $orgStockIdsOfOtherPurchaseOrders) {
            $items->whereIn(DB::raw("(stock_delivery_items.data->>'purchase_order_transaction_id')::bigint"), $transactions->pluck('id')->all())
                ->orWhere(fn (Builder $legacy) => $legacy
                    ->whereRaw("stock_delivery_items.data->>'purchase_order_transaction_id' is null")
                    ->whereIn('stock_delivery_items.org_stock_id', $transactions->pluck('org_stock_id')->filter()->all())
                    ->whereNotIn('stock_delivery_items.org_stock_id', $orgStockIdsOfOtherPurchaseOrders));
        });
    }

    public function afterValidator(Validator $validator): void
    {
        if (!self::acceptsRemovals($this->stockDelivery)) {
            $validator->errors()->add('purchase_order', __('Purchase orders can only be removed from a container in process'));
        }

        if (!$this->stockDelivery->purchaseOrders()->whereKey($this->purchaseOrder->id)->exists()) {
            $validator->errors()->add('purchase_order', __('This purchase order is not in this container'));
        }
    }

    public function handle(StockDelivery $stockDelivery, PurchaseOrder $purchaseOrder): StockDelivery
    {
        DB::transaction(function () use ($stockDelivery, $purchaseOrder) {
            StockDelivery::whereKey($stockDelivery->id)->lockForUpdate()->first();

            self::itemsOfPurchaseOrder($stockDelivery, $purchaseOrder)->get()
                ->each(fn (StockDeliveryItem $stockDeliveryItem) => $stockDeliveryItem->delete());

            $stockDelivery->purchaseOrders()->detach($purchaseOrder->id);
            $stockDelivery->update([
                'number_purchase_orders' => $stockDelivery->purchaseOrders()->count(),
            ]);

            PurchaseOrderHydrateDeliveryState::run($purchaseOrder);
        });

        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);
        $this->purchaseOrderHydrate($purchaseOrder);

        StockDeliveriesHydrateItems::run($stockDelivery);
        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery->refresh();
    }

    public function asController(StockDelivery $stockDelivery, PurchaseOrder $purchaseOrder, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $purchaseOrder);
    }

    public function action(StockDelivery $stockDelivery, PurchaseOrder $purchaseOrder): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($stockDelivery->organisation, []);

        return $this->handle($stockDelivery, $purchaseOrder);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
