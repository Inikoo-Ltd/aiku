<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Ordering\Order\CalculateOrderDiscounts;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateCategoriesData;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateDiscretionaryOffersData;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateTransactions;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Procurement\OrgPartner\GetPartnerLandedCost;
use App\Actions\Procurement\OrgPartner\GetPartnerSellingProduct;
use App\Actions\Procurement\PartnerShoppingListItem\EnsurePartnerOrderPackedInMatches;
use App\Actions\Production\PartnerShippingList\CherryPickPartnerShoppingListItems;
use App\Actions\Production\PartnerShippingList\SendPartnerOrderToWarehouse;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Catalogue\Product;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A purchase order to a partner that is not the manufacturing hub becomes an order in the
 * partner's shop: it is sent to the partner's warehouse at once, the delivery note makes the stock
 * delivery here, and the partner's invoice costs it when it is dispatched.
 */
class SendPartnerPurchaseOrderToSeller
{
    use AsAction;

    /** @var array<string, Product|null> */
    private array $sellingProducts = [];

    public static function appliesTo(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->parent instanceof OrgPartner
            && !$purchaseOrder->parent->partner->is_manufacturing_hub
            && !$purchaseOrder->source_id;
    }

    /**
     * @return array<int, string>
     */
    public function problems(PurchaseOrder $purchaseOrder): array
    {
        /** @var OrgPartner $orgPartner */
        $orgPartner = $purchaseOrder->parent;
        $partner    = $orgPartner->partner;

        $shop = Shop::find(Arr::get($partner->settings, 'procurement.shop_id'));
        if (!$shop) {
            return [__(':partner has no shop set up to sell to the other companies', ['partner' => $partner->name])];
        }
        if (!$orgPartner->organisation->address) {
            return [__(':organisation has no address, :partner cannot deliver to it', ['organisation' => $orgPartner->organisation->name, 'partner' => $partner->name])];
        }

        $transactions = $this->transactions($purchaseOrder);
        $problems     = [];
        foreach ($transactions as $transaction) {
            if (!$this->sellingProduct($orgPartner, $transaction->stock_id, $shop->id)) {
                $problems[] = __(':partner does not sell :code', ['partner' => $partner->name, 'code' => $transaction->orgStock->code]);
            }
        }

        return [
            ...$problems,
            ...EnsurePartnerOrderPackedInMatches::make()->mismatches($orgPartner, $transactions->pluck('stock_id')->unique()->all()),
        ];
    }

    /**
     * Runs in short steps that each commit, so no lock on shop or group wide rows is held while
     * hundreds of lines are added, and a retry carries on from the step that failed: the order is
     * created, then all its lines are added at once, then it goes to the warehouse and the purchase
     * order is confirmed. A lock per purchase order keeps a retry from running alongside a slow run.
     *
     * @throws \Throwable
     */
    public function handle(PurchaseOrder $purchaseOrder): ?Order
    {
        return Cache::lock("send-partner-purchase-order:$purchaseOrder->id", 900)
            ->block(900, fn () => $this->send($purchaseOrder));
    }

    /**
     * @throws \Throwable
     */
    private function send(PurchaseOrder $purchaseOrder): ?Order
    {
        $purchaseOrder->refresh();
        if ($purchaseOrder->state !== PurchaseOrderStateEnum::SUBMITTED) {
            return null;
        }

        /** @var OrgPartner $orgPartner */
        $orgPartner = $purchaseOrder->parent;
        $shop       = Shop::find(Arr::get($orgPartner->partner->settings, 'procurement.shop_id'));

        $order = Order::find(data_get($purchaseOrder->data, 'seller_order_id'));
        if (!$order || ($order->state === OrderStateEnum::CREATING && !$order->transactions()->exists())) {
            if ($this->problems($purchaseOrder)) {
                UpdatePurchaseOrderStateToInProcess::make()->action($purchaseOrder);

                return null;
            }

            $order ??= $this->storeOrder($purchaseOrder, $orgPartner, $shop);
            $this->addLines($purchaseOrder, $orgPartner, $shop, $order);
        }

        $order->refresh();
        if ($order->state === OrderStateEnum::CREATING) {
            DB::transaction(fn () => SendPartnerOrderToWarehouse::make()->action($order));
        }

        DB::transaction(function () use ($purchaseOrder, $order) {
            $stockDelivery = StockDelivery::whereIn('delivery_note_id', $order->deliveryNotes()->select('delivery_notes.id'))->first();
            if (!$stockDelivery) {
                throw new \RuntimeException("Seller order $order->id for $purchaseOrder->reference has no stock delivery");
            }
            if (!$stockDelivery->purchaseOrders()->whereKey($purchaseOrder->id)->exists()) {
                $stockDelivery->purchaseOrders()->attach($purchaseOrder->id);
                $stockDelivery->update(['number_purchase_orders' => 1]);
            }

            UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());
        });

        return $order->refresh();
    }

    private function addLines(PurchaseOrder $purchaseOrder, OrgPartner $orgPartner, Shop $shop, Order $order): void
    {
        DB::transaction(function () use ($purchaseOrder, $orgPartner, $shop, $order) {
            $order = Order::lockForUpdate()->find($order->id);
            if ($order->transactions()->exists()) {
                return;
            }

            $landedCostLines = [];
            $orderLines      = [];
            foreach ($this->transactions($purchaseOrder) as $transaction) {
                $product        = $this->sellingProduct($orgPartner, $transaction->stock_id, $shop->id);
                $sellerOrgStock = $product->orgStocks()->first();
                $unitsPerItem   = (float) $product->pivot->quantity * (float) ($sellerOrgStock?->packed_in ?: 1);
                $quantity       = round((float) $transaction->quantity_ordered / $unitsPerItem, 3);
                $amount         = round($quantity * (float) $product->price, 2);

                $orderTransaction = StoreTransaction::make()->action($order, $product->historicAsset, [
                    'quantity_ordered' => $quantity,
                    'gross_amount'     => $amount,
                    'net_amount'       => $amount,
                ], strict: false);
                $orderLines[$transaction->id] = $orderTransaction->id;

                if ($sellerOrgStock && (float) $product->price > 0) {
                    $landedCostLines[$orderTransaction->id] = [$sellerOrgStock->id, (float) $product->pivot->quantity / (float) $product->price];
                }
            }

            $order->refresh();
            OrderHydrateCategoriesData::run($order);
            CalculateOrderTotalAmounts::run($order);
            OrderHydrateTransactions::run($order);

            if (GetPartnerLandedCost::appliesTo($orgPartner)) {
                $this->discountToLandedCost($order, $landedCostLines);
            }

            $this->matchPurchaseOrderToSellerOrder($purchaseOrder, $order, $orderLines);

            $order->update(['at_gate_at' => now()]);
        });
    }

    /**
     * The seller invoices its order, so once it is priced each purchase order line takes the amount
     * the seller charges for it, and the purchase order total is what will be paid.
     *
     * @param  array<int, int>  $orderLines  purchase order transaction id => seller order transaction id
     */
    private function matchPurchaseOrderToSellerOrder(PurchaseOrder $purchaseOrder, Order $order, array $orderLines): void
    {
        if ($order->currency_id !== $purchaseOrder->currency_id) {
            return;
        }

        $sellerNetAmounts = Transaction::whereIn('id', $orderLines)->pluck('net_amount', 'id');
        foreach ($this->transactions($purchaseOrder) as $transaction) {
            $netAmount = $sellerNetAmounts[$orderLines[$transaction->id] ?? null] ?? null;
            if ($netAmount === null || (float) $transaction->quantity_ordered <= 0) {
                continue;
            }

            $transaction->update([
                'net_amount' => $netAmount,
                'unit_cost'  => round((float) $netAmount / (float) $transaction->quantity_ordered, 6),
            ]);
        }

        CalculatePurchaseOrderTotalAmounts::make()->handle($purchaseOrder);
    }

    private function sellingProduct(OrgPartner $orgPartner, int $stockId, int $shopId): ?Product
    {
        $key = "$orgPartner->id:$stockId:$shopId";
        if (!array_key_exists($key, $this->sellingProducts)) {
            $this->sellingProducts[$key] = GetPartnerSellingProduct::run($orgPartner, $stockId, [$shopId]);
        }

        return $this->sellingProducts[$key];
    }

    /**
     * Committed on its own like any basket, together with the link on the purchase order, so the shop
     * wide counters it bumps are not held locked while the lines are added.
     */
    private function storeOrder(PurchaseOrder $purchaseOrder, OrgPartner $orgPartner, Shop $shop): Order
    {
        $cherryPick = CherryPickPartnerShoppingListItems::make();
        $customer   = $cherryPick->resolveIntercompanyCustomer($orgPartner, $shop);

        return DB::transaction(function () use ($purchaseOrder, $customer, $cherryPick) {
            $order = StoreOrder::make()->action($customer, [
                'sales_channel_id'   => $cherryPick->intercompanySalesChannel($customer->group_id)->id,
                'customer_reference' => $purchaseOrder->reference,
            ]);

            $purchaseOrder->update(['data' => array_merge($purchaseOrder->data ?? [], ['seller_order_id' => $order->id])]);

            return $order;
        });
    }

    /**
     * The line keeps the product's list price as gross, so the historic asset, sales and invoices stay
     * as for any order, and a discretionary discount takes it down to what the stock cost the seller.
     *
     * @param  array<int, array{0: int, 1: float}>  $landedCostLines  order transaction id => [seller org stock id, SKOs per unit of list price]
     */
    private function discountToLandedCost(Order $order, array $landedCostLines): void
    {
        $landedCosts = GetPartnerLandedCost::run(array_unique(array_column($landedCostLines, 0)));

        foreach ($landedCostLines as $transactionId => [$sellerOrgStockId, $skosPerPrice]) {
            if (!isset($landedCosts[$sellerOrgStockId])) {
                continue;
            }

            Transaction::where('id', $transactionId)->update([
                'discretionary_offer'       => round(max(0.0, min(1.0, 1 - $landedCosts[$sellerOrgStockId] * $skosPerPrice)), 4),
                'discretionary_offer_label' => __('Intercompany at landed cost'),
            ]);
        }

        OrderHydrateDiscretionaryOffersData::run($order);
        CalculateOrderDiscounts::run($order->refresh());
    }

    private function transactions(PurchaseOrder $purchaseOrder): \Illuminate\Database\Eloquent\Collection
    {
        return $purchaseOrder->purchaseOrderTransactions()
            ->whereIn('state', [PurchaseOrderTransactionStateEnum::IN_PROCESS, PurchaseOrderTransactionStateEnum::SUBMITTED])
            ->with('orgStock')
            ->get();
    }
}
