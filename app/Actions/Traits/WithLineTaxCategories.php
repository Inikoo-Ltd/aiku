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
use App\Models\Masters\MasterAsset;
use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Support\Facades\DB;
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
    /**
     * The map is read from the transaction's historic asset, where it was frozen when the
     * line was sold - a preset change mints a new historic, so old lines keep their
     * treatment. Historics predating the column carry null and fall back to the live master.
     */
    public function getLineTaxCategoryId(Order|Invoice $parent, ?object $transaction): int
    {
        $map = $transaction?->historicAsset?->tax_category
            ?? $transaction?->asset?->masterAsset?->tax_category
            ?? [];

        return (int)Arr::get($map, $parent->tax_category_id, $parent->tax_category_id);
    }

    /**
     * The reduced type an order category can be discounted to: standard pairs with reduced,
     * special (recargo de equivalencia) with reduced_special.
     */
    public function getReducedCounterpartType(TaxCategory $orderTaxCategory): string
    {
        return $orderTaxCategory->type->value == 'special' ? 'reduced_special' : 'reduced';
    }

    /**
     * The presets staff actually pick from; individual tax codes are never edited by hand.
     * A preset is a UI artifact only - what is stored, and what the money path reads, is the
     * expanded `tax_category` map. Definitions live here in code: a preset is the set of
     * countries whose reduced rate applies.
     *
     * @return array<string, array{label: string, countries: array<int, string>}>
     */
    public function getTaxPresets(): array
    {
        return [
            'food'          => ['label' => __('Food'), 'countries' => ['GBR', 'ESP']],
            'dried_flowers' => ['label' => __('Dried flowers'), 'countries' => ['ESP']],
        ];
    }

    /**
     * Expands a preset into the stored shape: for each active standard or special category of
     * the preset's countries, its reduced counterpart (standard pairs with reduced, special
     * with reduced_special). Today food gives VAT 20% -> 0%, IVA 21% -> 10% and
     * IVA+RE 26.2% -> 11.4%.
     *
     * @return array<int, int>
     */
    public function expandTaxPreset(string $presetName): array
    {
        $countries = Arr::get($this->getTaxPresets(), $presetName.'.countries');
        if (!$countries) {
            return [];
        }

        $countryIds = Country::whereIn('iso3', $countries)->pluck('id');

        $reduced = TaxCategory::whereIn('country_id', $countryIds)
            ->whereIn('type', ['reduced', 'reduced_special'])
            ->orderBy('status')
            ->orderBy('id')
            ->get();

        $map = [];
        foreach (
            TaxCategory::where('status', true)
                ->whereIn('type', ['standard', 'special'])
                ->whereIn('country_id', $countryIds)
                ->orderBy('country_id')
                ->orderBy('id')
                ->get() as $orderTaxCategory
        ) {
            $counterpartType = $this->getReducedCounterpartType($orderTaxCategory);

            /** Ordered above so ->last() is the live counterpart, or failing that the newest. */
            $counterpart = $reduced
                ->filter(fn (TaxCategory $candidate) => $candidate->country_id == $orderTaxCategory->country_id
                    && $candidate->type->value == $counterpartType)
                ->last();

            if ($counterpart) {
                $map[$orderTaxCategory->id] = $counterpart->id;
            }
        }

        return $map;
    }

    /**
     * The cards for the preset selector, each describing the rates it means, so picking
     * "Food" shows exactly what will be charged. "Custom" only appears when the stored map
     * matches no preset; picking it is a no-op.
     *
     * @return array<int, array{value: string, title: string, description: string}>
     */
    public function getTaxPresetOptions(array $currentMap): array
    {
        $options = [[
            'value'       => 'standard',
            'title'       => __('Standard rate'),
            'description' => __("Charged at the order's own rate, no override"),
        ]];

        foreach ($this->getTaxPresets() as $presetName => $preset) {
            $options[] = [
                'value'       => $presetName,
                'title'       => $preset['label'],
                'description' => $this->describeTaxCategoryMap($this->expandTaxPreset($presetName)),
            ];
        }

        if ($this->inferTaxPreset($currentMap) == 'custom') {
            $options[] = [
                'value'       => 'custom',
                'title'       => __('Custom (as imported)'),
                'description' => $this->describeTaxCategoryMap($currentMap),
            ];
        }

        return $options;
    }

    public function describeTaxCategoryMap(array $taxCategoryMap): string
    {
        return collect($taxCategoryMap)
            ->map(function ($lineTaxCategoryId, $orderTaxCategoryId) {
                $order = TaxCategory::find((int)$orderTaxCategoryId);
                $line  = TaxCategory::find((int)$lineTaxCategoryId);

                return $order && $line ? $order->country->iso3.': '.$order->name.' → '.$line->name : null;
            })
            ->filter()
            ->implode('  ·  ');
    }

    /**
     * Which preset a stored map is: 'standard' for no overrides, the preset name on a match,
     * 'custom' for a map no preset produces (hand-set or imported).
     */
    public function inferTaxPreset(array $currentMap): string
    {
        $normalised = array_map('intval', $currentMap);
        ksort($normalised);

        if (empty($normalised)) {
            return 'standard';
        }

        foreach (array_keys($this->getTaxPresets()) as $presetName) {
            $expanded = $this->expandTaxPreset($presetName);
            ksort($expanded);

            if ($expanded == $normalised) {
                return $presetName;
            }
        }

        return 'custom';
    }

    /**
     * The baskets a tax change on these assets will sweep: orders still CREATING, nothing
     * else - submitted orders keep the treatment they were sold under.
     *
     * @param  array<int, int>  $assetIds
     *
     * @return array<int, int>
     */
    public function getTaxSweepBasketOrderIds(array $assetIds): array
    {
        if (empty($assetIds)) {
            return [];
        }

        return DB::table('transactions')
            ->join('orders', 'orders.id', 'transactions.order_id')
            ->where('orders.state', OrderStateEnum::CREATING)
            ->whereNull('orders.deleted_at')
            ->whereNull('transactions.deleted_at')
            ->whereIn('transactions.asset_id', $assetIds)
            ->distinct()
            ->pluck('orders.id')
            ->all();
    }

    public function getTaxChangeAffectedBasketCount(MasterAsset $masterAsset): int
    {
        $assetIds = $masterAsset->products()
            ->whereHas('shop', fn ($query) => $query->whereNot('type', ShopTypeEnum::EXTERNAL))
            ->pluck('asset_id')
            ->filter()
            ->all();

        return count($this->getTaxSweepBasketOrderIds($assetIds));
    }

    /**
     * The tax rows every order summary shows: one per rate with the net it applies to
     * beside it, so zero rated tea appears as its own line instead of blending into a
     * figure labelled with the order's headline rate. External (Faire) orders keep the
     * single payload figure, theirs is not derived from the lines.
     *
     * @return array<int, array{label: string, information: string, price_total: mixed}>
     */
    public function getOrderTaxRows(Order $order): array
    {
        $taxRows = [];
        if ($order->shop->type != ShopTypeEnum::EXTERNAL) {
            $taxBreakdown = $order->taxBreakdown();
            foreach ($taxBreakdown as $taxRow) {
                $taxRows[] = [
                    'label'       => __('Tax').' ('.$taxRow['name'].')',
                    'information' => count($taxBreakdown) > 1
                        ? __('on').' '.$order->currency->symbol.number_format($taxRow['net_amount'], 2)
                        : '',
                    'price_total' => $taxRow['tax_amount'],
                ];
            }
        }

        if (empty($taxRows)) {
            $taxRows[] = [
                'label'       => __('Tax').' ('.$order->taxCategory->getLocalizedName().')',
                'information' => '',
                'price_total' => $order->tax_amount,
            ];
        }

        return $taxRows;
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
            ->with(['historicAsset:id,tax_category', 'asset:id,master_asset_id', 'asset.masterAsset:id,tax_category'])
            ->get(['id', 'order_id', 'asset_id', 'historic_asset_id', 'tax_category_id']);

        foreach ($transactions as $transaction) {
            $taxCategoryId = $this->getLineTaxCategoryId($order, $transaction);

            if ($transaction->tax_category_id !== $taxCategoryId) {
                $transaction->updateQuietly(['tax_category_id' => $taxCategoryId]);
            }
        }
    }

    public function applyInvoiceLineTaxCategories(Invoice $invoice): void
    {
        if ($invoice->shop->type == ShopTypeEnum::EXTERNAL) {
            return;
        }

        $transactions = $invoice->invoiceTransactions()
            ->with(['historicAsset:id,tax_category', 'asset:id,master_asset_id', 'asset.masterAsset:id,tax_category'])
            ->get(['id', 'invoice_id', 'asset_id', 'historic_asset_id', 'tax_category_id']);

        foreach ($transactions as $transaction) {
            $taxCategoryId = $this->getLineTaxCategoryId($invoice, $transaction);

            if ($transaction->tax_category_id !== $taxCategoryId) {
                $transaction->updateQuietly(['tax_category_id' => $taxCategoryId]);
            }
        }

        $invoice->unsetRelation('invoiceTransactions');
    }

    /**
     * @return array<int, array{tax_category_id: int, name: string, rate: float, net_amount: float, tax_amount: float}>
     */
    public function getOrderTaxBreakdown(Order $order): array
    {
        $modelTypes = ['Product', 'Service', 'Charge', 'Adjustment'];
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
        /** Two columns, not 2,500 hydrated models: this runs on every invoice view and pdf. */
        return $this->getTaxBreakdown(
            $invoice->invoiceTransactions()
                ->whereIn('model_type', ['Pallet', 'StoredItem', 'Space', 'Rental', 'Product', 'Service', 'ShippingZone', 'Charge', 'Adjustment'])
                ->get(['tax_category_id', 'net_amount']),
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
