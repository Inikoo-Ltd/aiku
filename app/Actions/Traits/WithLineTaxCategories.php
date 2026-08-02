<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Helpers\Country;
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
     * Countries that levy a reduced rate, so are worth offering as an override. Only these
     * can appear in a tax map: an override is always the reduced counterpart of the order's
     * own category in the order's own country, never a rate from somewhere else.
     *
     * @return Collection<int, \App\Models\Helpers\Country>
     */
    public function getReducedRateCountries(): Collection
    {
        return Country::whereIn('id', TaxCategory::where('status', true)
            ->whereIn('type', ['standard', 'special'])
            ->whereNotNull('country_id')
            ->pluck('country_id'))
            ->whereIn('id', TaxCategory::whereIn('type', ['reduced', 'reduced_special'])
                ->whereNotNull('country_id')
                ->pluck('country_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * The stored map is one entry per order category, but the decision behind it is per
     * country: "this product takes the reduced rate in Spain". Spain has two live categories
     * (IVA and IVA+RE) so it expands to two entries, the UK to one.
     *
     * @param  array<int, int>  $countryIds
     *
     * @return array<int, int>
     */
    public function expandReducedRateCountries(array $countryIds): array
    {
        if (empty($countryIds)) {
            return [];
        }

        $reduced = TaxCategory::whereIn('country_id', $countryIds)
            ->whereIn('type', ['reduced', 'reduced_special'])
            ->orderBy('status')
            ->orderBy('id')
            ->get();

        $map = [];
        foreach (TaxCategory::whereIn('country_id', $countryIds)->where('status', true)->whereIn('type', ['standard', 'special'])->get() as $taxCategory) {
            /** `type` is cast to an enum, so every comparison here goes through ->value. */
            $reducedType = $taxCategory->type->value == 'special' ? 'reduced_special' : 'reduced';

            /** Ordered above so the last match is the live one, or failing that the newest. */
            $counterpart = $reduced
                ->filter(fn (TaxCategory $candidate) => $candidate->country_id == $taxCategory->country_id
                    && $candidate->type->value == $reducedType)
                ->last();

            if ($counterpart) {
                $map[$taxCategory->id] = $counterpart->id;
            }
        }

        return $map;
    }

    /**
     * @return array<int, int> the countries the stored map already covers
     */
    public function collapseToReducedRateCountries(array $taxCategoryMap): array
    {
        if (empty($taxCategoryMap)) {
            return [];
        }

        return TaxCategory::whereIn('id', array_keys($taxCategoryMap))
            ->whereNotNull('country_id')
            ->pluck('country_id')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * One named choice per combination of reduced rate countries, so the everyday answer
     * ("it's food, don't tax it") is a single pick rather than a row building exercise.
     *
     * ponytail: enumerating subsets, fine while two countries levy a reduced rate. Past
     * four it wants a multi select instead.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getReducedRateOptions(): array
    {
        $countries = $this->getReducedRateCountries();

        $options = [['value' => '', 'label' => __('Standard rate')]];

        $combinations = [[]];
        foreach ($countries as $country) {
            foreach ($combinations as $combination) {
                $combinations[] = array_merge($combination, [$country]);
            }
        }

        foreach ($combinations as $combination) {
            if (empty($combination)) {
                continue;
            }

            $names   = implode(' & ', array_map(fn (Country $country) => $country->name, $combination));
            $isFood  = count($combination) == count($countries);
            $options[] = [
                'value' => implode(',', array_map(fn (Country $country) => $country->id, $combination)),
                'label' => __('Reduced or zero rated in :countries', ['countries' => $names])
                    .($isFood ? ' — '.__('food') : ''),
            ];
        }

        return $options;
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
