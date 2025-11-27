<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Nov 2025 13:00:12 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Collection\DeleteCollection;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrphanCollections
{
    use AsAction;

    /**
     * Iterate collections of a shop and delete those without a master_collection reference.
     */
    public function handle(Shop $shop, bool $forceDelete = false, bool $dryRun = false, Command $command = null): int
    {
        $deleted = 0;
        $scanned = 0;

        Collection::query()
            ->where('shop_id', $shop->id)
            ->whereNull('master_collection_id')
            ->orderBy('id')
            ->chunkById(200, function ($collections) use (&$deleted, &$scanned, $dryRun, $forceDelete, $command) {
                foreach ($collections as $collection) {
                    $scanned++;
                    $command?->info(($dryRun ? '[DRY] ' : '') . "Orphan collection: {$collection->id} {$collection->slug} - {$collection->name}");

                    if ($dryRun) {
                        continue;
                    }

                    DeleteCollection::make()->action($collection, $forceDelete);
                    $deleted++;
                }
            });

        $command?->info("Scanned: {$scanned}, Deleted: {$deleted}, Shop: {$shop->slug}");

        return $deleted;
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:repair_orphan_collections {shop : Shop slug} {--F|force : Force delete} {--D|dry-run : Do not persist changes, just print}';
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        $dry = (bool)$command->option('dry-run');
        $force = (bool)$command->option('force');

        // Safety: default to dry-run when --force is not provided
        if (!$force && !$dry) {
            $command->warn('No --force provided; running in dry-run mode by default. Use --dry-run to silence this warning.');
            $dry = true;
        }

        $this->handle($shop, $force, $dry, $command);

        return 0;
    }
}
