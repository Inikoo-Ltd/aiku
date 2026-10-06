<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Ordering\Order\CalculateOrderDiscounts;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateDiscretionaryOffersData;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Procurement\OrgPartner\GetPartnerLandedCost;
use App\Actions\Procurement\OrgPartner\GetPartnerSellingProduct;
use App\Actions\Procurement\PartnerShoppingListItem\EnsurePartnerOrderPackedInMatches;
use App\Actions\Production\PartnerShippingList\CherryPickPartnerShoppingListItems;
use App\Actions\Production\PartnerShippingList\SendPartnerOrderToWarehouse;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A purchase order to a partner that is not the manufacturing hub becomes an order in the
 * partner's shop: it is sent to the partner's warehouse at once, the delivery note makes the stock
 * delivery here, and the partner's invoice costs it when it is dispatched.
 */
class SendPartnerPurchaseOrderToSeller
{
    use AsAction;

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
            if (!GetPartnerSellingProduct::run($orgPartner, $transaction->stock_id, [$shop->id])) {
                $problems[] = __(':partner does not sell :code', ['partner' => $partner->name, 'code' => $transaction->orgStock->code]);
            }
        }

        return [
            ...$problems,
            ...EnsurePartnerOrderPackedInMatches::make()->mismatches($orgPartner, $transactions->pluck('stock_id')->unique()->all()),
        ];
    }

    /**
     * @throws \Throwable
     */
    public function handle(PurchaseOrder $purchaseOrder): Order
    {
        if ($problems = $this->problems($purchaseOrder)) {
            throw ValidationException::withMessages(['purchase_order' => $problems]);
        }

        /** @var OrgPartner $orgPartner */
        $orgPartner  = $purchaseOrder->parent;
        $cherryPick  = CherryPickPartnerShoppingListItems::make();
        $shop        = Shop::find(Arr::get($orgPartner->partner->settings, 'procurement.shop_id'));
        $customer    = $cherryPick->resolveIntercompanyCustomer($orgPartner, $shop);

        $order = StoreOrder::make()->action($customer, [
            'sales_channel_id'   => $cherryPick->intercompanySalesChannel($customer->group_id)->id,
            'customer_reference' => $purchaseOrder->reference,
        ]);

        $landedCostLines = [];
        foreach ($this->transactions($purchaseOrder) as $transaction) {
            $product        = GetPartnerSellingProduct::run($orgPartner, $transaction->stock_id, [$shop->id]);
            $sellerOrgStock = $product->orgStocks()->first();
            $unitsPerItem   = (float) $product->pivot->quantity * (float) ($sellerOrgStock?->packed_in ?: 1);
            $quantity       = round((float) $transaction->quantity_ordered / $unitsPerItem, 3);
            $amount         = round($quantity * (float) $product->price, 2);

            $orderTransaction = StoreTransaction::make()->action($order, $product->historicAsset, [
                'quantity_ordered' => $quantity,
                'gross_amount'     => $amount,
                'net_amount'       => $amount,
            ]);

            if ($sellerOrgStock && (float) $product->price > 0) {
                $landedCostLines[$orderTransaction->id] = [$sellerOrgStock->id, (float) $product->pivot->quantity / (float) $product->price];
            }
        }

        if (GetPartnerLandedCost::appliesTo($orgPartner)) {
            $this->discountToLandedCost($order, $landedCostLines);
        }

        $order->update(['at_gate_at' => now()]);

        $stockDelivery = SendPartnerOrderToWarehouse::make()->action($order->refresh());
        if ($stockDelivery) {
            $stockDelivery->purchaseOrders()->attach($purchaseOrder->id);
            $stockDelivery->update(['number_purchase_orders' => 1]);
        }

        $purchaseOrder->update(['data' => array_merge($purchaseOrder->data ?? [], ['seller_order_id' => $order->id])]);
        UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());

        return $order;
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
