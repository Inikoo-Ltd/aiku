<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Goods;

use App\Actions\Goods\Barcode\Hydrators\GroupHydrateBarcodes;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Models\Helpers\Barcode;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Only ever moves available to used. A barcode marked used that nothing carries today may
 * have been published before, so it is never put back into the pool.
 */
class RepairBarcodesStatus
{
    use AsAction;

    public string $commandSignature = 'repair:barcodes_status {--commit}';

    public function handle(Group $group, bool $commit, ?Command $command = null): int
    {
        $carried = Barcode::where('group_id', $group->id)
            ->where('status', BarcodeStatusEnum::AVAILABLE)
            ->whereNotIn('id', Barcode::where('group_id', $group->id)->free()->select('barcodes.id'));

        $count = (clone $carried)->count();

        $command?->info("$group->slug: $count barcodes marked available are carried by a trade unit, master, product, stock or SKO");
        foreach ((clone $carried)->orderBy('number')->limit(20)->pluck('number') as $number) {
            $command?->line("  $number");
        }

        if ($commit && $count) {
            $carried->update(['status' => BarcodeStatusEnum::USED, 'updated_at' => now()]);
            GroupHydrateBarcodes::run($group);
            $command?->info("$count barcodes set to used");
        } elseif (!$commit) {
            $command?->warn('Dry run, pass --commit to write');
        }

        return $count;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        foreach (Group::all() as $group) {
            $this->handle($group, (bool)$command->option('commit'), $command);
        }

        return 0;
    }
}
