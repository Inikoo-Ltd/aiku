<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Jun 2023 21:00:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Models\Inventory\OrgStock;
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

        //        $oldQuantityAvailable   = $orgStock->quantity_available;
        //        $oldQuantityInLocations = $orgStock->quantity_in_locations;

        $quantityInLocations = DB::table('location_org_stocks')->where('org_stock_id', $orgStock->id)->sum('quantity');

        $quantityAvailable = $quantityInLocations - $orgStock->quantity_in_submitted_orders - $orgStock->quantity_to_be_picked;

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
        ]);

        if ($orgStock->wasChanged('quantity_available')) {
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

            foreach ($orgStock->products as $product) {
                ProductHydrateAvailableQuantity::run($product);
            }
        }

        if ($orgStock->wasChanged('quantity_in_locations')) {
            OrgStockHydrateValueInLocations::dispatch($orgStock);
            OrgStockHydrateProductsAvailableQuantity::dispatch($orgStock);
        }
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
