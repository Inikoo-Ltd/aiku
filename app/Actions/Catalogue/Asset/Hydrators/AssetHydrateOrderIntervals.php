<?php

/*
 * author Arya Permana - Kirin
 * created on 19-12-2024-10h-57m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Asset\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateOrderIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(int $assetID, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($assetID, $intervals, $doPreviousPeriods);
    }

    public function handle(int $assetID, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $asset = Asset::find($assetID);
        if (!$asset) {
            return;
        }

        $stats     = [];
        $queryBase = DB::table('transactions')->whereNull('deleted_at')->where('asset_id', $asset->id)->selectRaw('count(distinct order_id) as  sum_aggregate  ');


        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'orders_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );


        $asset->orderingIntervals()->update($stats);
    }

    public function getCommandSignature(): string
    {
        // Set --only_active to default true
        return 'catalogue:asset:order-intervals {scope_type?} {scope?} {--only_active=true}';
    }

    public function asCommand(Command $command): int
    {
        if ($command->argument('scope_type') == 'asset') {
            $asset = Asset::where('slug', $command->argument('scope_type'))->firstOrFail();
            $this->handle($asset->id);

            return 0;
        }


        $onlyActive = (bool)$command->option('only_active');


        $baseQuery = Asset::query()->select(['id', 'status']);

        $output = $command->getOutput();

        if ($command->argument('scope_type') == 'shop') {

            $shop = Shop::where('slug', $command->argument('scope'))->firstOrFail();
            $output->writeln("<info>Processing assets for shop:</info> $shop->name ($shop->slug)");

            $baseQuery->where('shop_id', $shop->id);
        } else {
            $output->writeln("<info>Processing assets for all assets</info>");
        }


        if ($onlyActive) {
            $baseQuery->where('status', true);
        }

        $baseQuery->orderBy('id');

        $total = (clone $baseQuery)->count();


        $bar = $output->createProgressBar($total);
        $bar->start();

        // Process in chunks of 1000 to keep memory usage low
        $baseQuery->chunkById(1000, function ($assets) use ($onlyActive, $bar) {
            /** @var Asset $asset */
            foreach ($assets as $asset) {
                if ($onlyActive && !$asset->status) {
                    $bar->advance();
                    continue;
                }
                $this->handle($asset->id);
                $bar->advance();
            }
        }, 'id');

        $bar->finish();
        $output->writeln('');


        return 0;
    }

}
