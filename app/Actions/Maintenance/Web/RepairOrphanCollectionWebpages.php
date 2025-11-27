<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Nov 2025 13:00:12 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Web\Webpage\DeleteWebpage;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrphanCollectionWebpages
{
    use AsAction;


    public function handle(bool $forceDelete = false, bool $dryRun = false, Command $command = null): int
    {
        $deleted = 0;
        $scanned = 0;

        Webpage::query()
            ->where('model_type', 'Collection')
            ->orderBy('id')
            ->chunkById(200, function ($webpages) use (&$deleted, &$scanned, $dryRun, $forceDelete, $command) {
                foreach ($webpages as $webpage) {

                    if ($webpage->model) {
                        continue;
                    }

                    $scanned++;
                    $command?->info(($dryRun ? '[DRY] ' : '') . "Orphan webpage: {$webpage->id} {$webpage->slug} - {$webpage->canonical_url}");

                    if ($dryRun) {
                        continue;
                    }

                    DeleteWebpage::make()->action($webpage, $forceDelete);
                    $deleted++;
                }
            });

        $command?->info("Scanned: {$scanned}, Deleted: {$deleted}");

        return $deleted;
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:repair_orphan_collection_webpages {--F|force : Force delete} {--D|dry-run : Do not persist changes, just print}';
    }

    public function asCommand(Command $command): int
    {

        $dry = (bool)$command->option('dry-run');
        $force = (bool)$command->option('force');

        // Safety: default to dry-run when --force is not provided
        if (!$force && !$dry) {
            $command->warn('No --force provided; running in dry-run mode by default. Use --dry-run to silence this warning.');
            $dry = true;
        }

        $this->handle($force, $dry, $command);

        return 0;
    }
}
