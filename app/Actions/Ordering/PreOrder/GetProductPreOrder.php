<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Catalogue\Product\GetProductIncomingStock;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\PreOrder\PreOrderTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * HELP-3432. A product flagged back-order or made-to-order, in a shop with pre-orders on, can be
 * ordered beyond its stock. Made-to-order wins when both are set: nothing is waited for from a
 * purchase order, the supplier is asked when the customer buys.
 *
 * Lead time: a back-order waits for the earliest open purchase order or stock delivery with a
 * typed date; otherwise, and always for made-to-order, the product's own lead time, else the
 * slowest preferred supplier's pre-order lead time, else the shop default. Customers see a range
 * in weeks, never a date.
 */
class GetProductPreOrder
{
    use AsObject;

    public function type(Product $product): ?PreOrderTypeEnum
    {
        if (!$product->is_made_to_order && !$product->is_back_order) {
            return null;
        }

        if (!$product->is_for_sale
            || $product->exclusive_for_customer_id
            || !in_array($product->state, [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING])
            || !$product->shop->hasPreOrders()) {
            return null;
        }

        return $product->is_made_to_order ? PreOrderTypeEnum::MADE_TO_ORDER : PreOrderTypeEnum::BACK_ORDER;
    }

    /**
     * @return array{type: string, lead_time_days: int, dispatch_from_weeks: int, dispatch_to_weeks: int, label: string, available_label: string, dispatch_label: string, payment_label: string, terms_label: string, max_quantity: int|null, deposit_percentage: float, is_pallet_delivery: bool, terms: array<int, string>}|null
     */
    public function handle(Product $product): ?array
    {
        return $this->byProduct(collect([$product]))[$product->id] ?? null;
    }

    /**
     * For listings built from partial selects: only flagged products are loaded.
     *
     * @param  array<int, int>  $productIds
     *
     * @return array<int, array<string, mixed>>
     */
    public function byProductId(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }

        return $this->byProduct(
            Product::whereIn('id', $productIds)
                ->where(fn ($query) => $query->where('is_back_order', true)->orWhere('is_made_to_order', true))
                ->get()
        );
    }

    /**
     * @param  Collection<int, Product>  $products
     *
     * @return array<int, array<string, mixed>>
     */
    public function byProduct(Collection $products): array
    {
        $products = EloquentCollection::make($products)->loadMissing('shop');
        $types = $products->mapWithKeys(fn (Product $product) => [$product->id => $this->type($product)])->filter();

        if ($types->isEmpty()) {
            return [];
        }

        $productIds = $types->keys()->all();

        $backOrderIds   = $types->filter(fn (PreOrderTypeEnum $type) => $type === PreOrderTypeEnum::BACK_ORDER)->keys()->all();
        $etaByProduct   = GetProductIncomingStock::make()->earliestEtaByProduct($backOrderIds);
        $supplierDays   = $this->supplierLeadTimeDaysByProduct($productIds);

        $preOrders = [];
        foreach ($products->whereIn('id', $productIds) as $product) {
            $type = $types[$product->id];

            $leadTimeDays = null;
            if ($type === PreOrderTypeEnum::BACK_ORDER && isset($etaByProduct[$product->id])) {
                $leadTimeDays = (int) max(1, now()->startOfDay()->diffInDays(Carbon::parse($etaByProduct[$product->id])));
            }
            $leadTimeDays ??= $product->pre_order_lead_time_days
                ?? $supplierDays[$product->id]
                ?? (int) $product->shop->preOrderSetting('default_lead_time_days');

            $preOrders[$product->id] = $this->describe($product, $type, $leadTimeDays);
        }

        return $preOrders;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Product $product, PreOrderTypeEnum $type, int $leadTimeDays): array
    {
        $shop              = $product->shop;
        $texts             = GetPreOrderText::make();
        $fromWeeks         = max(1, (int) ceil($leadTimeDays / 7));
        $toWeeks           = $fromWeeks + (int) $shop->preOrderSetting('dispatch_range_weeks');
        $depositPercentage = $this->depositPercentage($product, $type);

        return [
            'type'                => $type->value,
            'lead_time_days'      => $leadTimeDays,
            'dispatch_from_weeks' => $fromWeeks,
            'dispatch_to_weeks'   => $toWeeks,
            'label'               => $texts->handle($shop, 'label'),
            'available_label'     => $texts->handle($shop, 'available'),
            'dispatch_label'      => $texts->handle($shop, 'dispatch', ['weeks' => $texts->weeks($fromWeeks, $toWeeks)]),
            'payment_label'       => $depositPercentage < 100
                ? $texts->handle($shop, 'pay_deposit', ['deposit_percent' => trimDecimalZeros($depositPercentage)])
                : $texts->handle($shop, 'pay_in_full'),
            'terms_label'         => $texts->handle($shop, 'terms_link'),
            'max_quantity'        => $product->max_quantity_per_order,
            'deposit_percentage'  => $depositPercentage,
            'is_pallet_delivery'  => $isPalletDelivery = $this->isPalletDelivery($product),
            'terms'               => $this->terms($shop, [$type], $isPalletDelivery),
        ];
    }

    /**
     * The note printed under a pre-order line in emails and on invoices, from what the line was
     * sold as, so it does not change when the product does.
     *
     * @param  array<string, mixed>|null  $linePreOrder  transaction data pre_order
     */
    public function lineNote(Shop $shop, ?array $linePreOrder): ?string
    {
        if (!PreOrderTypeEnum::tryFrom((string) ($linePreOrder['type'] ?? ''))) {
            return null;
        }

        $from = (int) ($linePreOrder['dispatch_from_weeks'] ?? 0);
        $to   = (int) ($linePreOrder['dispatch_to_weeks'] ?? $from);

        return GetPreOrderText::make()->handle($shop, 'line_note', ['weeks' => GetPreOrderText::make()->weeks($from, $to)]);
    }

    /**
     * What the customer accepts: shown on the product page, in the basket, ticked at checkout,
     * and repeated in the order confirmation and on the invoice.
     *
     * @param  array<int, PreOrderTypeEnum>  $types
     *
     * @return array<int, string>
     */
    public function terms(Shop $shop, array $types, bool $hasPalletDelivery): array
    {
        $texts          = GetPreOrderText::make();
        $isTrade        = $shop->type === ShopTypeEnum::B2B;
        $hasMadeToOrder = in_array(PreOrderTypeEnum::MADE_TO_ORDER, $types);
        $hasBackOrder   = in_array(PreOrderTypeEnum::BACK_ORDER, $types);

        $terms = [$texts->handle($shop, 'terms_estimate')];

        if (!$isTrade) {
            $terms[] = $texts->handle($shop, 'terms_paid_in_full_retail');
        } else {
            if ($hasBackOrder) {
                $terms[] = $texts->handle($shop, 'terms_paid_in_full');
            }
            if ($hasMadeToOrder) {
                $terms[] = $texts->handle($shop, 'terms_deposit');
                $terms[] = $texts->handle($shop, 'terms_deposit_cancel');
            }
        }

        $terms[] = $texts->handle($shop, 'terms_late');

        if ($hasMadeToOrder) {
            $terms[] = $texts->handle($shop, 'terms_handmade');
        }

        if ($hasPalletDelivery) {
            $terms[] = $texts->handle($shop, 'terms_pallet');
        }

        return $terms;
    }

    /**
     * Back-orders and dropshipping are paid in full at checkout, only trade made-to-order lines
     * take a deposit. The shop's "pay in full below" threshold is applied to the whole order.
     */
    public function depositPercentage(Product $product, PreOrderTypeEnum $type): float
    {
        if ($type !== PreOrderTypeEnum::MADE_TO_ORDER || $product->shop->type !== ShopTypeEnum::B2B) {
            return 100.0;
        }

        return (float) ($product->pre_order_deposit_percentage ?? $product->shop->preOrderSetting('deposit_percentage'));
    }

    public function isPalletDelivery(Product $product): bool
    {
        $weightLimitKg = $product->shop->preOrderSetting('pallet_weight_kg');
        if ($weightLimitKg !== null && $weightLimitKg !== '' && (float) $product->gross_weight / 1000 > (float) $weightLimitKg) {
            return true;
        }

        $longestSideLimitCm = $product->shop->preOrderSetting('pallet_longest_side_cm');
        if ($longestSideLimitCm === null || $longestSideLimitCm === '') {
            return false;
        }

        return $this->longestSideCm($product) > (float) $longestSideLimitCm;
    }

    /**
     * The rough pallet rate the shop typed for a country, shown as an estimate; the real cost
     * is quoted when the goods arrive.
     */
    public function palletEstimate(Shop $shop, ?string $countryCode): ?float
    {
        if (!$countryCode) {
            return null;
        }

        $rate = collect($shop->preOrderSetting('pallet_rates'))->firstWhere('country_code', $countryCode);

        return $rate ? (float) $rate['amount'] : null;
    }

    public function palletEstimateLabel(Shop $shop, ?string $countryCode): ?string
    {
        $estimate = $this->palletEstimate($shop, $countryCode);
        if ($estimate === null) {
            return null;
        }

        return __('Estimated pallet delivery to :country: approx. :amount', [
            'country' => Country::where('code', $countryCode)->value('name') ?? $countryCode,
            'amount'  => $shop->currency->symbol.number_format($estimate, 2),
        ]);
    }

    /**
     * For the customer looking at the website: their delivery country, else their billing one.
     *
     * @param  array<string, mixed>|null  $preOrder
     *
     * @return array<string, mixed>|null
     */
    /**
     * The website shows the dispatch window in weeks, never the supplier's lead time in days.
     */
    public function forWebsite(?array $preOrder): ?array
    {
        return $preOrder ? Arr::except($preOrder, ['lead_time_days']) : null;
    }

    public function withPalletEstimateFor(?array $preOrder, ?Customer $customer): ?array
    {
        $preOrder = $this->forWebsite($preOrder);
        if (!$preOrder || !$preOrder['is_pallet_delivery'] || !$customer) {
            return $preOrder;
        }

        $countryCode = $customer->deliveryAddress?->country_code ?? $customer->address?->country_code;

        return array_merge($preOrder, ['pallet_estimate_label' => $this->palletEstimateLabel($customer->shop, $countryCode)]);
    }

    /**
     * Marketing dimensions are stored in metres, "units" is only how they are displayed.
     */
    private function longestSideCm(Product $product): float
    {
        $dimensions = $product->marketing_dimensions ?? [];

        return 100 * max(0, ...array_map(
            fn ($side) => (float) ($dimensions[$side] ?? 0),
            ['l', 'w', 'h']
        ));
    }

    /**
     * The preferred supplier of each org stock, and of those the slowest one: a product made of
     * several stocks leaves only when the last one arrives.
     *
     * @param  array<int, int>  $productIds
     *
     * @return array<int, int>
     */
    public function supplierLeadTimeDaysByProduct(array $productIds): array
    {
        return DB::table('product_has_org_stocks')
            ->join('org_stock_has_org_supplier_products', function ($join) {
                $join->on('org_stock_has_org_supplier_products.org_stock_id', 'product_has_org_stocks.org_stock_id')
                    ->where('org_stock_has_org_supplier_products.status', true);
            })
            ->join('org_supplier_products', 'org_supplier_products.id', 'org_stock_has_org_supplier_products.org_supplier_product_id')
            ->join('org_suppliers', 'org_suppliers.id', 'org_supplier_products.org_supplier_id')
            ->join('suppliers', 'suppliers.id', 'org_suppliers.supplier_id')
            ->whereIn('product_has_org_stocks.product_id', $productIds)
            ->orderByDesc('org_stock_has_org_supplier_products.local_priority')
            ->get([
                'product_has_org_stocks.product_id',
                'product_has_org_stocks.org_stock_id',
                DB::raw("nullif(suppliers.settings->>'pre_order_lead_time', '')::int as lead_time"),
                DB::raw("suppliers.settings->>'pre_order_lead_time_unit' as lead_time_unit"),
            ])
            ->unique(fn ($row) => $row->product_id.'-'.$row->org_stock_id)
            ->filter(fn ($row) => $row->lead_time)
            ->groupBy('product_id')
            ->map(fn ($rows) => (int) $rows->max(fn ($row) => $row->lead_time * ($row->lead_time_unit == 'weeks' ? 7 : 1)))
            ->all();
    }
}
