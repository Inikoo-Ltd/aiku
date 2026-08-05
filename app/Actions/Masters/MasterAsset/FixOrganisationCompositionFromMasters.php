<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Actions\Catalogue\Product\UpdateOrdersInBasketsAfterProductUpdated;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Forces every shop product of one organisation back onto its master's composition.
 *
 * All shops of an organisation pick from the same warehouse, so two shops of that
 * organisation selling the same master must pick the same things: where they differ
 * it is drift, not intent. On aw's SK organisation the assortment masters had up to
 * six different picking lists across eleven shops — a transposed stock code here, a
 * missing component there — which means the warehouse packs a different box depending
 * on which country ordered.
 *
 * Units are left alone unless asked for: re-meaning a product's units rewrites what
 * existing order lines mean, so that is a decision per product, not a bulk sweep.
 */
class FixOrganisationCompositionFromMasters
{
    use AsAction;

    /**
     * @return array{checked: int, fixed: int, units_fixed: int, changes: list<string>}
     */
    public function handle(Organisation $organisation, bool $dryRun = true, bool $withUnits = true, ?MasterShop $masterShop = null, bool $onlyFlagged = false, bool $withPrices = true, ?callable $report = null, ?callable $onMasterDone = null): array
    {
        $checked            = 0;
        $fixed              = 0;
        $unitsFixed     = 0;
        $pricesFixed        = 0;
        $pricesFixed    = 0;
        $changes        = [];
        $ordersToReview     = [];
        $ordersToReview = [];

        /*
         * Only what is still sold: walking every product of every master means 149k
         * rows on aw's SK organisation, and most of them are discontinued stock or
         * masters nobody sells, which no one is going to act on.
         */
        /*
         * Only what is still sold: walking every product of every master means 149k
         * rows on aw's SK organisation, and most of them are discontinued stock or
         * masters nobody sells, which no one is going to act on.
         */
        $masterQuery = fn () => MasterAsset::whereHas('products', fn ($query) => $query->whereHas('shop', fn ($shop) => $shop->where('organisation_id', $organisation->id)))
            ->when($masterShop, fn ($query) => $query->where('master_shop_id', $masterShop->id))
            ->where('status', true)
            ->where('is_for_sale', true)
            /*
             * Driven by the mismatch flag the detector already wrote: comparing every
             * master again means 149k product comparisons to find the few hundred that
             * drifted. Pass onlyFlagged false after data has changed under the flag.
             */
            ->when($onlyFlagged, fn ($query) => $query->where('mismatch_detected', true))
            ->where('is_for_sale', true)
            /*
             * Everything is compared unless --flagged is asked for. The flag is only as
             * fresh as the last hydrate, and trusting a stale one once reported four
             * products to fix when six hundred and ninety two had drifted.
             */
            ->when($onlyFlagged, fn ($query) => $query->where('mismatch_detected', true))
            ->with('tradeUnits', 'stocks')
        ;

        $total = $masterQuery()->count();
        $onMasterDone && $onMasterDone(0, $total);

        $masterQuery()
            ->chunkById(200, function ($masterAssets) use ($organisation, $dryRun, $withUnits, $withPrices, $report, $onMasterDone, &$checked, &$fixed, &$unitsFixed, &$pricesFixed, &$changes, &$ordersToReview) {
                foreach ($masterAssets as $masterAsset) {
                    $tradeUnitData = $masterAsset->tradeUnits->map(fn ($tradeUnit) => [
                        'id'       => $tradeUnit->id,
                        'quantity' => data_get($tradeUnit, 'pivot.quantity'),
                    ])->all();

                    if (!$tradeUnitData) {
                        continue;
                    }

                    /*
                     * Only shops attached to this master's own master shop: an organisation
                     * also holds shops that follow a different one (SK carries a dropshipping
                     * shop on 'ds' and a specialty shop on 'ac'), and those are separate
                     * catalogues, not drifted copies of this one.
                     */
                    $products = $masterAsset->products()
                        ->whereHas('shop', fn ($shop) => $shop
                            ->where('organisation_id', $organisation->id)
                            ->where('master_shop_id', $masterAsset->master_shop_id))
                        ->where('products.status', '!=', ProductStatusEnum::DISCONTINUED)
                        ->where('is_for_sale', true)
                        ->where('not_follow_master_trade_units', false)
                        ->with('shop.currency', 'family', 'tradeUnits', 'orgStocks')
                        ->get();

                    foreach ($products as $product) {
                        $checked++;

                        $compositionDrifted = !$this->followsMaster($masterAsset, $product);
                        $unitsDrifted       = $withUnits && (float)$product->units !== (float)$masterAsset->units;
                        $priceDrift         = $withPrices ? $this->priceDrift($masterAsset, $product) : [];

                        if (!$compositionDrifted && !$unitsDrifted && !$priceDrift) {
                            continue;
                        }

                        $note = [];
                        if ($compositionDrifted) {
                            $fixed++;
                            $note[] = 'composition';
                        }
                        if ($unitsDrifted) {
                            $unitsFixed++;
                            $note[] = 'units '.(float)$product->units.'→'.(float)$masterAsset->units;
                        }
                        if ($priceDrift) {
                            $pricesFixed++;
                            $note[] = implode(', ', array_map(
                                fn ($value, $field) => $field.' '.$product->{$field}.'→'.$value,
                                $priceDrift,
                                array_keys($priceDrift)
                            ));
                        }

                        $changes[] = $masterAsset->code.' @ '.$product->shop->code.' ('.implode('; ', $note).')';

                        if ($dryRun) {
                            continue;
                        }

                        if ($compositionDrifted) {
                            SyncProductTradeUnits::run($product, $tradeUnitData);
                        }
                        if ($unitsDrifted) {
                            $ordersToReview = array_merge($ordersToReview, $this->submittedOrdersHolding($product));
                            $this->alignUnits($product, (float)$masterAsset->units);
                        }
                        if ($priceDrift) {
                            UpdateProduct::make()->action($product, $priceDrift);
                        }
                    }
                    $onMasterDone && $onMasterDone(1, null);
                }
            });

        return [
            'checked'      => $checked,
            'fixed'        => $fixed,
            'units_fixed'  => $unitsFixed,
            'prices_fixed' => $pricesFixed,
            'changes'      => $changes,
            'orders_to_review' => array_values(array_unique($ordersToReview)),
        ];
    }

    /**
     * @return array{price?: float, rrp?: float} only the values that actually differ
     */
    private function priceDrift(MasterAsset $masterAsset, Product $product): array
    {
        $shopFollows = data_get($product->shop->settings, 'catalog.follow_master_pricing', true);

        if (!$shopFollows || $product->not_follow_master_prices || $product->family?->not_follow_master_prices) {
            return [];
        }

        $drift       = [];
        $masterPrice = $masterAsset->getPriceFromCurrency($product->shop->currency);
        $masterRrp   = $masterAsset->getRrpFromCurrency($product->shop->currency);

        if ($masterPrice && abs((float)$product->price - $masterPrice) > 0.005) {
            $drift['price'] = $masterPrice;
        }
        if ($masterRrp && $product->rrp !== null && abs((float)$product->rrp - $masterRrp) > 0.005) {
            $drift['rrp'] = $masterRrp;
        }

        return $drift;
    }

    private function followsMaster(MasterAsset $masterAsset, Product $product): bool
    {
        return GetMasterAssetAnomalies::make()->productFollowsComposition($masterAsset, $product);
    }

    private function alignUnits(Product $product, float $masterUnits): void
    {
        $old = (float)$product->units;
        $product->updateQuietly(['units' => $masterUnits]);

        $product->auditEvent     = 'units_aligned_to_master';
        $product->isCustomEvent  = true;
        $product->auditCustomOld = ['units' => $old];
        $product->auditCustomNew = ['units' => $masterUnits];
        Event::dispatch(new AuditCustom($product));

        /*
         * Baskets hold lines whose meaning just changed, so they are repriced — the
         * action only touches orders still being built. Orders already submitted are
         * deliberately left as they were and reported instead.
         */
        UpdateOrdersInBasketsAfterProductUpdated::dispatch($product->id);
    }

    /**
     * Orders past the basket that hold this product: their lines were agreed under the
     * old units and are not rewritten, but somebody has to know they exist.
     *
     * @return list<string>
     */
    private function submittedOrdersHolding(Product $product): array
    {
        return DB::table('transactions')
            ->join('orders', 'orders.id', '=', 'transactions.order_id')
            ->where('transactions.model_type', 'Product')
            ->where('transactions.model_id', $product->id)
            ->whereNotIn('orders.state', ['creating', 'cancelled', 'dispatched'])
            ->distinct()
            ->pluck('orders.reference')
            ->map(fn ($reference) => $product->code.' @ '.$product->shop->code.' → order '.$reference)
            ->all();
    }

    public function getCommandSignature(): string
    {
        return 'master_product:fix_organisation_composition {organisation} {--master_shop=} {--apply} {--skip-units} {--skip-prices} {--flagged}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $dryRun       = !$command->option('apply');
        $withUnits    = !$command->option('skip-units');
        $withPrices   = !$command->option('skip-prices');
        $masterShop   = $command->option('master_shop')
            ? MasterShop::where('slug', $command->option('master_shop'))->firstOrFail()
            : null;

        if ($withUnits && !$dryRun && !$command->confirm('Aligning units rewrites what existing order lines mean. Continue?')) {
            return 1;
        }

        $bar = null;

        $result = $this->handle(
            $organisation,
            $dryRun,
            $withUnits,
            $masterShop, !$command->option('all'), $withPrices,
            (bool)$command->option('flagged'),
            $withPrices,
            function (string $line) use ($command, &$bar) {
                // The bar owns the last line, so it steps aside while a change is printed.
                $bar?->clear();
                $command->line('  '.$line);
                $bar?->display();
            },
            function (int $advance, ?int $total) use ($command, &$bar) {
                if ($total !== null) {
                    $bar = $command->getOutput()->createProgressBar($total);
                    $bar->setFormat('debug');
                    $bar->start();

                    return;
                }

                $bar?->advance($advance);
            }
        );

        $bar?->finish();
        $command->newLine(2);

        $command->info(($dryRun ? 'DRY RUN — ' : '').
            $result['checked'].' products checked, '.$result['fixed'].' composition '.($dryRun ? 'would be ' : '').'fixed, '.
            $result['units_fixed'].' units, '.$result['prices_fixed'].' prices');

        if ($result['orders_to_review']) {
            $command->newLine();
            $command->warn(count($result['orders_to_review']).' submitted orders hold a product whose units changed and were left untouched:');
            foreach (array_slice($result['orders_to_review'], 0, 40) as $line) {
                $command->line('  '.$line);
            }
        }

        return 0;
    }
}
