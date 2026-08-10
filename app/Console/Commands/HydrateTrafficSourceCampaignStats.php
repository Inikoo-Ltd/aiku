<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceCampaignHydrateStats;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Console\Command;

class HydrateTrafficSourceCampaignStats extends Command
{
    protected $signature = 'traffic-source:hydrate-campaign-stats {--shop= : Only campaigns of a specific shop slug}';

    protected $description = 'Rebuild the stats rollup of every traffic source campaign from its attribution and spend';

    /**
     * Campaign stats are maintained on write, but nothing has ever written them: the table shipped
     * with the wrong columns. This backfills what already exists, and is safe to rerun.
     */
    public function handle(): int
    {
        $query = TrafficSourceCampaign::query();

        if ($shopSlug = $this->option('shop')) {
            $shop = Shop::where('slug', $shopSlug)->first();

            if (!$shop) {
                $this->error("Shop '{$shopSlug}' not found.");

                return Command::FAILURE;
            }

            $query->whereHas('trafficSource', fn ($sources) => $sources->where('shop_id', $shop->id));
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No campaigns to hydrate.');

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($campaigns) use ($bar) {
            foreach ($campaigns as $campaign) {
                TrafficSourceCampaignHydrateStats::run($campaign);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Hydrated {$total} campaign(s).");

        return Command::SUCCESS;
    }
}
