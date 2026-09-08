<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 15:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Actions\Traits\WithMasterAssetTradeUnits;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Final step of retiring the IAL01 "Import Address Labels" hack: takes the label out of the product
 * and master bills of materials, where it was never a component, only a picking instruction.
 *
 * This must not run until `org_stocks.consumables` carries the instruction instead and packers have
 * confirmed they see it, so the command refuses to write while any affected product would be left
 * with no consumable telling anyone to put a label in the box.
 *
 * Order history is never touched. The delivery note items, pickings and stock movements that record
 * labels already shipped stay exactly as they are, as does the label's own trade unit, stock and org
 * stocks, which are discontinued separately.
 */
class RemoveIal01FromBillsOfMaterials
{
    use AsAction;
    use WithMasterAssetTradeUnits;

    private const string CONSUMABLE_CODE = 'IAL01';

    public string $commandSignature = 'catalogue:remove-ial01-from-boms
        {--apply : Remove the lines, without this the command only reports what it would do}
        {--force : Remove even where no consumable replaces the instruction, use only to clean up dormant lines}';

    public function getTradeUnit(): ?TradeUnit
    {
        return TradeUnit::where('code', self::CONSUMABLE_CODE)->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getProductIds(TradeUnit $tradeUnit)
    {
        return DB::table('model_has_trade_units')
            ->where('model_type', 'Product')
            ->where('trade_unit_id', $tradeUnit->id)
            ->distinct()
            ->pluck('model_id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getMasterAssetIds(TradeUnit $tradeUnit)
    {
        return DB::table('model_has_trade_units')
            ->where('model_type', 'MasterAsset')
            ->where('trade_unit_id', $tradeUnit->id)
            ->distinct()
            ->pluck('model_id');
    }

    /**
     * Products that would lose the instruction without gaining a consumable, and whose organisation
     * actually dispatches the label so the instruction matters.
     *
     * Two kinds of product are deliberately not flagged: the label's own product, which is the label
     * rather than something needing one, and products in organisations that have never dispatched a
     * label, whose lines are a master cascade nobody has ever acted on.
     *
     * If no organisation can be shown to dispatch the label the narrowing is skipped rather than
     * applied to an empty list, so an unexpected data shape blocks the removal instead of waving it
     * through.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getUnprotectedProducts(TradeUnit $tradeUnit)
    {
        $labelOrgStockIds = DB::table('model_has_trade_units')
            ->where('model_type', 'OrgStock')
            ->where('trade_unit_id', $tradeUnit->id)
            ->pluck('model_id');

        $dispatchingOrganisationIds = DB::table('delivery_note_items')
            ->whereIn('org_stock_id', $labelOrgStockIds)
            ->distinct()
            ->pluck('organisation_id');

        return DB::table('products')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->whereIn('products.id', $this->getProductIds($tradeUnit))
            ->where('products.code', '<>', self::CONSUMABLE_CODE)
            ->when(
                $dispatchingOrganisationIds->isNotEmpty(),
                fn ($query) => $query->whereIn('products.organisation_id', $dispatchingOrganisationIds)
            )
            ->whereNotExists(function ($query) use ($labelOrgStockIds) {
                $query->select(DB::raw(1))
                    ->from('product_has_org_stocks')
                    ->join('org_stocks', 'org_stocks.id', '=', 'product_has_org_stocks.org_stock_id')
                    ->whereColumn('product_has_org_stocks.product_id', 'products.id')
                    ->whereNotIn('org_stocks.id', $labelOrgStockIds)
                    ->whereNotNull('org_stocks.consumables');
            })
            ->select(['products.id', 'products.code', 'products.state', 'shops.code as shop_code'])
            ->orderBy('shops.code')
            ->orderBy('products.code')
            ->get();
    }

    private function tradeUnitsWithout(iterable $tradeUnits, TradeUnit $tradeUnit): array
    {
        $kept = [];

        foreach ($tradeUnits as $existing) {
            if ($existing->id == $tradeUnit->id) {
                continue;
            }

            $kept[] = [
                'id'       => $existing->id,
                'quantity' => $existing->pivot->quantity,
            ];
        }

        return $kept;
    }

    public function handle(bool $apply = false, bool $force = false, ?callable $onProgress = null): array
    {
        $tradeUnit = $this->getTradeUnit();

        if (!$tradeUnit) {
            return ['products' => 0, 'masters' => 0, 'unprotected' => collect(), 'blocked' => false];
        }

        $productIds  = $this->getProductIds($tradeUnit);
        $masterIds   = $this->getMasterAssetIds($tradeUnit);
        $unprotected = $this->getUnprotectedProducts($tradeUnit);

        if ($apply && $unprotected->isNotEmpty() && !$force) {
            return [
                'products'    => $productIds->count(),
                'masters'     => $masterIds->count(),
                'unprotected' => $unprotected,
                'blocked'     => true,
            ];
        }

        if ($apply) {
            foreach ($masterIds as $masterId) {
                $masterAsset = MasterAsset::find($masterId);
                if ($masterAsset) {
                    $this->processTradeUnits($masterAsset, $this->tradeUnitsWithout($masterAsset->tradeUnits, $tradeUnit));
                }
            }

            foreach ($productIds as $productId) {
                $product = Product::find($productId);
                if ($product) {
                    SyncProductTradeUnits::run($product, $this->tradeUnitsWithout($product->tradeUnits, $tradeUnit));
                }

                if ($onProgress) {
                    $onProgress();
                }
            }
        }

        return [
            'products'    => $productIds->count(),
            'masters'     => $masterIds->count(),
            'unprotected' => $unprotected,
            'blocked'     => false,
        ];
    }

    public function asCommand(Command $command): int
    {
        $tradeUnit = $this->getTradeUnit();

        if (!$tradeUnit) {
            $command->error('Trade unit '.self::CONSUMABLE_CODE.' not found, nothing to do.');

            return Command::FAILURE;
        }

        $apply = (bool) $command->option('apply');
        $force = (bool) $command->option('force');

        $bar = null;
        if ($apply) {
            $bar = $command->getOutput()->createProgressBar($this->getProductIds($tradeUnit)->count());
        }

        $result = $this->handle($apply, $force, $bar ? fn () => $bar->advance() : null);

        if ($bar) {
            $bar->finish();
            $command->newLine(2);
        }

        if ($result['blocked']) {
            $command->error($result['unprotected']->count().' product(s) would lose the label instruction with nothing replacing it.');
            $command->table(
                ['Shop', 'Product', 'State'],
                $result['unprotected']->take(20)->map(fn ($row) => [$row->shop_code, $row->code, $row->state])
            );
            $command->warn('Run inventory:repair-ial01-org-stock-consumables --apply first, or pass --force to remove anyway.');

            return Command::FAILURE;
        }

        $command->info(($apply ? 'Removed' : 'Would remove').' '.self::CONSUMABLE_CODE
            .' from '.$result['products'].' product and '.$result['masters'].' master bill(s) of materials.');

        if ($result['unprotected']->isNotEmpty()) {
            $command->warn($result['unprotected']->count().' of those product(s) have no consumable replacing the instruction:');
            $command->table(
                ['Shop', 'Product', 'State'],
                $result['unprotected']->take(20)->map(fn ($row) => [$row->shop_code, $row->code, $row->state])
            );
        }

        if (!$apply) {
            $command->warn('Dry run, nothing was removed. Re-run with --apply to remove.');
        }

        return Command::SUCCESS;
    }
}
