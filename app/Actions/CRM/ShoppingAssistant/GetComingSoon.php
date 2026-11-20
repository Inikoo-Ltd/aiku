<?php

/*
 * Created: Thu, 19 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * INI-396, Raul 19 Nov 2026: "what is coming soon?" answered from stock on its way to us. A product
 * is coming soon (ProductStatusEnum::COMING_SOON: active, no stock, its stock never in our warehouse)
 * and only counts when its stock is on an open purchase order line submitted in the last
 * GetOnOrderNotListed::RECENT_MONTHS months (HELP-4843: submitted counts, new stock sits there for
 * weeks) or a stock delivery not yet put away: coming-soon products with neither are stale and left out. Products made for one customer
 * (product_has_exclusive_customers) never count (hard rule). Only family names reach the customer, as
 * a line written by code: no dates (deliveries slip), no prices, no quantities, no number of products.
 */
class GetComingSoon
{
    use AsObject;

    public const int MAX_FAMILIES = 8;

    public const array ON_THE_WAY_DELIVERY_STATES = [
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
        StockDeliveryStateEnum::DISPATCHED,
        StockDeliveryStateEnum::RECEIVED,
        StockDeliveryStateEnum::CHECKED,
        StockDeliveryStateEnum::BOOKING_IN,
        StockDeliveryStateEnum::BOOKED_IN,
    ];

    /**
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, fact?: string}
     */
    public function handle(Customer $customer, string $locale, bool $remindersOn): array
    {
        $families = $this->families($customer->shop_id);

        if (!$families) {
            return [
                'content'  => ['found' => false, 'note' => 'Nothing new is on its way to us that we can tell them about right now. Say so honestly in one sentence, without guessing, and offer to show what is new in the range or to look for something they have in mind.'],
                'products' => [],
                'error'    => null,
            ];
        }

        return [
            'content'  => [
                'found'  => true,
                'ranges' => $families,
                'note'   => 'The line under your reply, written by the system, lists new ranges coming to our website: on order with our supplier or on their way to us. In one or two sentences say that these are coming soon and point to the line. Never say when they arrive and never give a date, price, quantity or number of products. '
                    .($remindersOn
                        ? 'Offer an email the moment one lands; if they say yes, call notify_when_back_in_stock with that range name as query and customer_agreed true.'
                        : 'Say they will find it on the website as soon as it lands.'),
            ],
            'products' => [],
            'error'    => null,
            'fact'     => __('Coming soon to our website: :families.', ['families' => implode(', ', $families)], $locale),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function families(int $shopId): array
    {
        $open       = PurchaseOrderStateEnum::openValues();
        $deliveries = array_map(fn (StockDeliveryStateEnum $state) => $state->value, self::ON_THE_WAY_DELIVERY_STATES);
        $finished   = [StockDeliveryItemStateEnum::CANCELLED->value, StockDeliveryItemStateEnum::NOT_RECEIVED->value, StockDeliveryItemStateEnum::PLACED->value];

        return DB::table('products')
            ->join('product_categories as families', 'families.id', '=', 'products.family_id')
            ->where('products.shop_id', $shopId)
            ->where('products.status', ProductStatusEnum::COMING_SOON->value)
            ->where('products.state', ProductStateEnum::ACTIVE->value)
            ->whereNull('products.deleted_at')
            ->whereNull('families.deleted_at')
            ->whereNotNull('families.name')
            ->whereNotExists(fn ($query) => $query->from('product_has_exclusive_customers')->whereColumn('product_has_exclusive_customers.product_id', 'products.id'))
            ->whereExists(fn ($query) => $query->from('product_has_org_stocks')
                ->whereColumn('product_has_org_stocks.product_id', 'products.id')
                ->where(fn ($query) => $query
                    ->whereExists(fn ($query) => $query->from('purchase_order_transactions')
                        ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_transactions.purchase_order_id')
                        ->whereColumn('purchase_order_transactions.org_stock_id', 'product_has_org_stocks.org_stock_id')
                        ->whereNull('purchase_order_transactions.deleted_at')
                        ->whereNotIn('purchase_order_transactions.state', [PurchaseOrderTransactionStateEnum::CANCELLED->value, PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value, PurchaseOrderTransactionStateEnum::SETTLED->value])
                        ->whereNull('purchase_orders.deleted_at')
                        ->whereIn('purchase_orders.state', $open)
                        ->where('purchase_orders.submitted_at', '>=', now()->subMonths(GetOnOrderNotListed::RECENT_MONTHS)))
                    ->orWhereExists(fn ($query) => $query->from('stock_delivery_items')
                        ->join('stock_deliveries', 'stock_deliveries.id', '=', 'stock_delivery_items.stock_delivery_id')
                        ->whereColumn('stock_delivery_items.org_stock_id', 'product_has_org_stocks.org_stock_id')
                        ->whereNull('stock_delivery_items.deleted_at')
                        ->whereNull('stock_deliveries.deleted_at')
                        ->whereNotIn('stock_delivery_items.state', $finished)
                        ->whereIn('stock_deliveries.state', $deliveries))))
            ->groupBy('families.id', 'families.name')
            ->orderByRaw('count(distinct products.id) desc')
            ->orderBy('families.name')
            ->limit(self::MAX_FAMILIES)
            ->pluck('families.name')
            ->all();
    }
}
