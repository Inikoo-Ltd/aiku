<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
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
    public function handle(Organisation $organisation, bool $dryRun = true, bool $withUnits = false, ?MasterShop $masterShop = null, bool $onlyFlagged = true): array
    {
        $checked    = 0;
        $fixed      = 0;
        $unitsFixed = 0;
        $changes    = [];

        /*
         * Only what is still sold: walking every product of every master means 149k
         * rows on aw's SK organisation, and most of them are discontinued stock or
         * masters nobody sells, which no one is going to act on.
         */
        MasterAsset::whereHas('products', fn ($query) => $query->whereHas('shop', fn ($shop) => $shop->where('organisation_id', $organisation->id)))
            ->when($masterShop, fn ($query) => $query->where('master_shop_id', $masterShop->id))
            ->where('status', true)
            ->where('is_for_sale', true)
            /*
             * Driven by the mismatch flag the detector already wrote: comparing every
             * master again means 149k product comparisons to find the few hundred that
             * drifted. Pass onlyFlagged false after data has changed under the flag.
             */
            ->when($onlyFlagged, fn ($query) => $query->where('mismatch_detected', true))
            ->with('tradeUnits', 'stocks')
            ->chunkById(200, function ($masterAssets) use ($organisation, $dryRun, $withUnits, &$checked, &$fixed, &$unitsFixed, &$changes) {
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
                        ->with('shop', 'tradeUnits', 'orgStocks')
                        ->get();

                    foreach ($products as $product) {
                        $checked++;

                        $compositionDrifted = !$this->followsMaster($masterAsset, $product);
                        $unitsDrifted       = (float)$product->units !== (float)$masterAsset->units;

                        if (!$compositionDrifted && !($unitsDrifted && $withUnits)) {
                            continue;
                        }

                        if ($compositionDrifted) {
                            $fixed++;
                        }
                        if ($unitsDrifted && $withUnits) {
                            $unitsFixed++;
                        }

                        $changes[] = $masterAsset->code.' @ '.$product->shop->code
                            .($unitsDrifted ? ' (units '.(float)$product->units.' → '.(float)$masterAsset->units.')' : '');

                        if ($dryRun) {
                            continue;
                        }

                        if ($compositionDrifted) {
                            SyncProductTradeUnits::run($product, $tradeUnitData);
                        }
                        if ($unitsDrifted && $withUnits) {
                            $this->alignUnits($product, (float)$masterAsset->units);
                        }
                    }
                }
            });

        return [
            'checked'     => $checked,
            'fixed'       => $fixed,
            'units_fixed' => $unitsFixed,
            'changes'     => $changes,
        ];
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
    }

    public function getCommandSignature(): string
    {
        return 'master_product:fix_organisation_composition {organisation} {--master_shop=} {--apply} {--with-units} {--all}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $dryRun       = !$command->option('apply');
        $withUnits    = (bool)$command->option('with-units');
        $masterShop   = $command->option('master_shop')
            ? MasterShop::where('slug', $command->option('master_shop'))->firstOrFail()
            : null;

        if ($withUnits && !$dryRun && !$command->confirm('Aligning units rewrites what existing order lines mean. Continue?')) {
            return 1;
        }

        $result = $this->handle($organisation, $dryRun, $withUnits, $masterShop, !$command->option('all'));

        $command->info(($dryRun ? 'DRY RUN — ' : '').
            $result['checked'].' products checked, '.$result['fixed'].' '.($dryRun ? 'would be' : '').' fixed'.
            ($withUnits ? ', '.$result['units_fixed'].' units aligned' : ''));

        foreach (array_slice($result['changes'], 0, 40) as $change) {
            $command->line('  '.$change);
        }
        if (count($result['changes']) > 40) {
            $command->line('  … and '.(count($result['changes']) - 40).' more');
        }

        return 0;
    }
}
