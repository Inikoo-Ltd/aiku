<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Jun 2023 21:00:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Actions\Dispatching\FulfilmentGate\ReleaseCoverableOrdersAtGate;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateLowStockAudits;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateOrgStocksBelowMinimumStock;
use App\Actions\Ordering\PreOrder\AllocatePreOrderStock;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateReplenishments;
use App\Actions\Production\PartnerShippingList\ReleaseUncoveredPrePicks;
use App\Actions\Production\RawMaterial\Hydrators\RawMaterialHydrateFromOrgStock;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Production\RawMaterial;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockHydrateQuantityInLocations implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

    public string $commandSignature = 'hydrate:org_stocks_quantity_in_locations {organisations?* : Organisation slugs, all of them when omitted}';

    public function getJobUniqueId(int|null $orgStockId): string
    {
        return $orgStockId ?? 'empty';
    }

    public function handle(int|null $orgStockId): void
    {
        if (!$orgStockId) {
            return;
        }
        $orgStock = OrgStock::find($orgStockId);

        if (!$orgStock) {
            return;
        }

        $oldQuantityAvailable = $orgStock->quantity_available;
        //        $oldQuantityInLocations = $orgStock->quantity_in_locations;

        $quantityInLocations = DB::table('location_org_stocks')->where('org_stock_id', $orgStock->id)->sum('quantity');

        /* Goods out locations are the gathering point for a dispatch: the stock is still ours
           and still counted, but it is spoken for and must not be offered to anybody else. */
        $quantityInGoodsOut = DB::table('location_org_stocks')
            ->join('locations', 'locations.id', 'location_org_stocks.location_id')
            ->where('location_org_stocks.org_stock_id', $orgStock->id)
            ->where('locations.is_goods_out', true)
            ->sum('location_org_stocks.quantity');

        if ($orgStock->organisation->is_aiku_stock_control) {
            $orgStock->quantity_to_be_picked = $this->getQuantityToBePicked($orgStock->id);
        }

        /* Goods that arrived for a pre-order are kept for it while its balance is due (HELP-3432). */
        $quantityAvailable = $quantityInLocations - $quantityInGoodsOut - $orgStock->quantity_in_submitted_orders - $orgStock->quantity_to_be_picked - $orgStock->quantity_reserved_for_pre_orders;

        // The source_ columns mirror Aurora's own reservations and only Aurora's stock
        // locations fetch writes them. Once an organisation runs its stock control in
        // aiku that fetch no longer runs, so the mirrors freeze at whatever Aurora last
        // reserved and would withhold that quantity forever. aiku's own
        // quantity_in_submitted_orders and quantity_to_be_picked already cover it.
        if (!$orgStock->organisation->is_aiku_stock_control) {
            $quantityAvailable = $quantityAvailable - $orgStock->source_quantity_in_submitted_orders - $orgStock->source_quantity_to_be_picked;
        }


        if ($quantityAvailable < 0) {
            $quantityAvailable = 0;
        }


        $orgStock->update([
            'quantity_in_locations' => $quantityInLocations,
            'quantity_available'    => $quantityAvailable,
            'quantity_to_be_picked' => $orgStock->quantity_to_be_picked,
        ]);

        if ($orgStock->wasChanged('quantity_available')) {
            OrgStockHydrateOutOfStockForecast::dispatch($orgStock)->delay(30);

            if ($quantityAvailable > $oldQuantityAvailable && $orgStock->organisation->hasFulfilmentGate()) {
                ReleaseCoverableOrdersAtGate::dispatch($orgStock->organisation_id)->delay(5);
            }

            if ($quantityAvailable > $oldQuantityAvailable && AllocatePreOrderStock::make()->hasWaitingPreOrders($orgStock->id)) {
                AllocatePreOrderStock::dispatch($orgStock->id)->delay(5);
            }

            /* A move takes the stock out of the shelf before it lands in the bay, both inside one
               transaction: judge the promises on the settled stock, not on the half-way drop (HELP-4466). */
            if ($quantityAvailable < $oldQuantityAvailable && $orgStock->organisation->is_manufacturing_hub) {
                DB::afterCommit(function () use ($orgStock) {
                    if ($settledOrgStock = OrgStock::find($orgStock->id)) {
                        ReleaseUncoveredPrePicks::run($settledOrgStock);
                    }
                });
            }

            //            DB::table('debug_stock_updates')->insert([
            //                'org_stock_id'              => $orgStock->id,
            //                'slug'                      => $orgStock->slug,
            //                'old_quantity_available'    => $oldQuantityAvailable,
            //                'new_quantity_available'    => $quantityAvailable,
            //                'old_quantity_in_locations' => $oldQuantityInLocations,
            //                'new_quantity_in_locations' => $quantityInLocations,
            //                'product_count'             => $orgStock->products()->count(),
            //                'created_at'                => now(),
            //                'updated_at'                => now(),
            //            ]);

            foreach ($orgStock->products()->with(['group', 'organisation', 'shop', 'family', 'webpage', 'asset', 'orgStocks'])->get() as $product) {
                ProductHydrateAvailableQuantity::run($product);
            }

            OrgStockHydrateStockValue::dispatch($orgStock);
        } elseif (!$orgStock->is_on_demand) {
            $outOfSyncProducts = $orgStock->products()
                ->where('product_has_org_stocks.quantity', '>', 0)
                ->whereRaw(
                    'products.available_quantity is distinct from floor(?::numeric / product_has_org_stocks.quantity)',
                    [$quantityAvailable]
                )->get();

            foreach ($outOfSyncProducts as $product) {
                ProductHydrateAvailableQuantity::dispatch($product);
            }
        }

        if ($orgStock->wasChanged('quantity_in_locations')) {
            OrgStockHydrateValueInLocations::dispatch($orgStock);
            OrgStockHydrateProductsAvailableQuantity::dispatch($orgStock);

            foreach ($orgStock->organisation->warehouses as $warehouse) {
                WarehouseHydrateLowStockAudits::dispatch($warehouse)->delay(2);
                WarehouseHydrateOrgStocksBelowMinimumStock::dispatch($warehouse)->delay(2);
                WarehouseHydrateReplenishments::dispatch($warehouse)->delay(2);
            }

            foreach (RawMaterial::where('org_stock_id', $orgStock->id)->get() as $rawMaterial) {
                RawMaterialHydrateFromOrgStock::dispatch($rawMaterial);
            }
        }
    }



    /**
     * Stock on a delivery note still waiting to be picked is promised to that customer (HELP-4177).
     * It leaves the location when picked, so only what has not been picked or set as not picked counts.
     */
    public function getQuantityToBePicked(int $orgStockId): float
    {
        $openStates = [
            DeliveryNoteStateEnum::UNASSIGNED->value,
            DeliveryNoteStateEnum::QUEUED->value,
            DeliveryNoteStateEnum::HANDLING->value,
            DeliveryNoteStateEnum::HANDLING_BLOCKED->value,
        ];

        return (float)DB::table('delivery_note_items')
            ->join('delivery_notes', 'delivery_notes.id', 'delivery_note_items.delivery_note_id')
            ->where('delivery_note_items.org_stock_id', $orgStockId)
            ->whereIn('delivery_note_items.state', $openStates)
            ->whereIn('delivery_notes.state', $openStates)
            ->whereNull('delivery_notes.deleted_at')
            ->sum(DB::raw('greatest(delivery_note_items.quantity_required - coalesce(delivery_note_items.quantity_picked, 0) - coalesce(delivery_note_items.quantity_not_picked, 0), 0)'));
    }

    public function asCommand(Command $command): int
    {
        $query = OrgStock::query()->select('org_stocks.id');

        if ($slugs = $command->argument('organisations')) {
            $organisationIds = Organisation::whereIn('slug', $slugs)->pluck('id');

            if ($organisationIds->count() !== count($slugs)) {
                $command->error('Unknown organisation slug in: '.implode(', ', $slugs));

                return 1;
            }

            $query->whereIn('organisation_id', $organisationIds);
        }

        $total = (clone $query)->count();
        $command->info("Rehydrating $total org stocks");

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('debug');
        $bar->start();

        // Chunk by id: handle() writes to the rows being walked, so an offset paginated
        // chunk would skip some of them.
        $query->chunkById(1000, function ($orgStocks) use ($bar) {
            foreach ($orgStocks as $orgStock) {
                $this->handle($orgStock->id);
                $bar->advance();
            }
        }, 'org_stocks.id', 'id');

        $bar->finish();
        $command->line('');

        return 0;
    }
}
