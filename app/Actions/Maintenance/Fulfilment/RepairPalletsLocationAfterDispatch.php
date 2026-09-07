<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Fulfilment;

use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Inventory\Location;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairPalletsLocationAfterDispatch
{
    use AsAction;

    public string $commandSignature = 'repair:pallets_location_after_dispatch {--commit : Persist the fixes, dry run otherwise}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $commit = (bool)$command->option('commit');

        $summary = $this->stalePalletsQuery()
            ->selectRaw('status, count(*) as pallets, count(distinct location_id) as locations')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        if ($summary->isEmpty()) {
            $command->info('No pallets with a stale location found');

            return 0;
        }

        $command->table(
            ['Status', 'Pallets', 'Locations'],
            $summary->map(fn ($row) => [$row->status, $row->pallets, $row->locations])->toArray()
        );

        $locationIds = $this->stalePalletsQuery()->distinct()->pluck('location_id');

        if (!$commit) {
            $command->warn("Dry run: {$summary->sum('pallets')} pallets in {$locationIds->count()} locations would be unset. Re-run with --commit to persist");

            return 0;
        }

        $unset = $this->stalePalletsQuery()->update(['location_id' => null]);
        $command->info("Unset location on $unset pallets");

        $command->info("Rehydrating {$locationIds->count()} locations");
        $progressBar = $command->getOutput()->createProgressBar($locationIds->count());
        foreach (Location::whereIn('id', $locationIds)->cursor() as $location) {
            LocationHydratePallets::run($location);
            $progressBar->advance();
        }
        $progressBar->finish();
        $command->newLine();

        return 0;
    }

    private function stalePalletsQuery(): Builder
    {
        return DB::table('pallets')
            ->whereNotNull('location_id')
            ->whereNotIn('status', PalletStatusEnum::occupyingLocation());
    }
}
