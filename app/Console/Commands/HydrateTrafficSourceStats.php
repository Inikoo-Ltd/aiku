<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCustomers;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use Illuminate\Console\Command;

class HydrateTrafficSourceStats extends Command
{
    protected $signature = 'traffic-source:hydrate-stats {--shop= : Only traffic sources of a specific shop slug}';

    protected $description = 'Rebuild the stats rollup of every traffic source from its attribution';

    /**
     * Source stats are otherwise only refreshed when a touch fires for that source, so a change to how
     * they are counted leaves every quiet channel showing the old figure until this is run. Safe to
     * rerun.
     */
    public function handle(): int
    {
        $query = TrafficSource::query();

        if ($shopSlug = $this->option('shop')) {
            $shop = Shop::where('slug', $shopSlug)->first();

            if (!$shop) {
                $this->error("Shop '{$shopSlug}' not found.");

                return Command::FAILURE;
            }

            $query->where('shop_id', $shop->id);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No traffic sources to hydrate.');

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($trafficSources) use ($bar) {
            foreach ($trafficSources as $trafficSource) {
                TrafficSourceHydrateCustomers::run($trafficSource);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Hydrated {$total} traffic source(s).");

        return Command::SUCCESS;
    }
}
