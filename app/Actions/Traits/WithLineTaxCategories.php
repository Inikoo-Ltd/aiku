<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Helpers\TaxCategory;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * A line's tax category is the order's, unless the line's master asset overrides it.
 *
 * `master_assets.tax_category` maps the order's tax category id to the one the product
 * actually attracts, e.g. `{"50": 54}` reads as "on a VAT 20% order this is VAT 0%",
 * which is how UK loose leaf tea is zero rated as food.
 */
trait WithLineTaxCategories
{
    public function getLineTaxCategoryId(Order $order, ?object $asset): int
    {
        $map = $asset?->masterAsset?->tax_category ?? [];

        return (int)Arr::get($map, $order->tax_category_id, $order->tax_category_id);
    }

    /**
     * Faire and other external shops are invoiced with the tax their marketplace charged,
     * so their lines always keep the order's category.
     */
    public function applyLineTaxCategories(Order $order): void
    {
        if ($order->shop->type == ShopTypeEnum::EXTERNAL) {
            return;
        }

        $transactions = $order->transactions()
            ->with(['asset:id,master_asset_id', 'asset.masterAsset:id,tax_category'])
            ->get(['id', 'order_id', 'asset_id', 'tax_category_id']);

        foreach ($transactions as $transaction) {
            $taxCategoryId = $this->getLineTaxCategoryId($order, $transaction->asset);

            if ($transaction->tax_category_id !== $taxCategoryId) {
                $transaction->updateQuietly(['tax_category_id' => $taxCategoryId]);
            }
        }
    }

    /**
     * @return array<int, array{tax_category_id: int, name: string, rate: float, net_amount: float, tax_amount: float}>
     */
    public function getOrderTaxBreakdown(Order $order): array
    {
        $modelTypes = ['Product', 'Charge'];
        if (!$order->collection_address_id) {
            $modelTypes[] = 'ShippingZone';
        }

        return $this->getTaxBreakdown(
            $order->transactions()->whereIn('model_type', $modelTypes)->get(['tax_category_id', 'net_amount']),
            $order->amount_off
        );
    }

    /**
     * @return array<int, array{tax_category_id: int, name: string, rate: float, net_amount: float, tax_amount: float}>
     */
    public function getInvoiceTaxBreakdown(Invoice $invoice): array
    {
        return $this->getTaxBreakdown(
            $invoice->invoiceTransactions->whereIn('model_type', ['Pallet', 'StoredItem', 'Space', 'Rental', 'Product', 'Service', 'ShippingZone', 'Charge']),
            $invoice->amount_off
        );
    }

    /**
     * @param  Collection  $transactions  rows carrying `tax_category_id` and `net_amount`
     *
     * @return array<int, array{tax_category_id: int, name: string, rate: float, net_amount: float, tax_amount: float}>
     */
    public function getTaxBreakdown(Collection $transactions, float $amountOff = 0): array
    {
        $netPerCategory = $transactions
            ->groupBy('tax_category_id')
            ->map(fn (Collection $lines) => round($lines->sum('net_amount'), 2));

        $totalNet     = $netPerCategory->sum();
        $lastCategory = $netPerCategory->keys()->last();
        $pendingOff   = round($amountOff, 2);

        $breakdown = [];
        foreach ($netPerCategory as $taxCategoryId => $net) {
            /**
             * The discount is split across the rates in proportion to their net, with the
             * last one taking whatever pennies are left so the parts still add up to the whole.
             */
            $off = ($taxCategoryId === $lastCategory || $totalNet == 0)
                ? $pendingOff
                : round($amountOff * $net / $totalNet, 2);

            $pendingOff = round($pendingOff - $off, 2);
            $net        = round($net - $off, 2);

            $taxCategory = TaxCategory::find($taxCategoryId);

            $breakdown[] = [
                'tax_category_id' => (int)$taxCategoryId,
                'name'            => $taxCategory->name,
                'rate'            => (float)$taxCategory->rate,
                'net_amount'      => $net,
                'tax_amount'      => round($net * $taxCategory->rate, 2),
            ];
        }

        return $breakdown;
    }
}
